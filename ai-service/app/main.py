from contextlib import asynccontextmanager
from datetime import date

import httpx
from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from pydantic import BaseModel, Field

from app import db, indexing
from app.agent import answer_question
from app.agents.finance import find_promises
from app.agents.marketing import draft as marketing_draft
from app.agents.operations import respond as operations_respond
from app.agents.sales import qualify
from app.config import settings
from app.extraction import UnreadableFile, clean_text, extract_text
from app.translation import translate


@asynccontextmanager
async def lifespan(app: FastAPI):
    # Vector table yahीn (ai-service) banti/ensure hoti hai
    db.ensure_schema()
    yield


app = FastAPI(title="Automation AI service", lifespan=lifespan)


class LearnRequest(BaseModel):
    source_id: int
    workspace_id: int
    content: str


class ChatLine(BaseModel):
    # "customer" or "business" (bot or staff)
    sender: str = Field(alias="from")
    text: str

    def as_dict(self) -> dict:
        return {"from": self.sender, "text": self.text}


class AskRequest(BaseModel):
    workspace_id: int
    question: str
    reply_style: str = "auto"  # auto | hinglish
    # The chat before the question, oldest first (WhatsApp); the website chatbot sends none
    history: list[ChatLine] = []


class TranslateRequest(BaseModel):
    text: str
    target: str  # the language's name in English, e.g. "Hindi"


class PromisesRequest(BaseModel):
    transcript: str  # the last messages, one line per message
    today: date  # in the business's time zone


class ProductLine(BaseModel):
    id: int
    name: str
    price: str
    unit: str | None = None
    stock: int | None = None  # None = not counted


class OperationsRequest(BaseModel):
    products: list[ProductLine]
    transcript: str  # the chat so far, one line per message
    today: date  # in the business's time zone
    needs_address: bool = False  # the business delivers: the agent must get the address
    booking_rules: str | None = None  # hours, closed days and taken times, when the business takes bookings


class MarketingProduct(BaseModel):
    name: str
    price: str
    unit: str | None = None


class CampaignDraftRequest(BaseModel):
    business: str
    products: list[MarketingProduct]
    brief: str  # what the owner wants to say, in a few words


class QualifyRequest(BaseModel):
    questions: list[str]
    transcript: str  # the chat so far, one line per message


@app.get("/health")
def health():
    return {"status": "ok", "provider": settings.ai_provider}


@app.get("/health/ollama")
async def health_ollama():
    try:
        async with httpx.AsyncClient(timeout=5) as client:
            response = await client.get(f"{settings.ollama_url}/api/tags")
            response.raise_for_status()
            models = [model["name"] for model in response.json().get("models", [])]

        def ready(name: str) -> bool:
            return name in models or f"{name}:latest" in models

        return {
            "reachable": True,
            "url": settings.ollama_url,
            "models": models,
            "chat_model_ready": ready(settings.ollama_chat_model),
            "embed_model_ready": ready(settings.ollama_embed_model),
        }
    except Exception as error:
        return {"reachable": False, "url": settings.ollama_url, "error": str(error)}


# Text source ko "yaad" karo
@app.post("/learn")
async def learn(request: LearnRequest):
    try:
        text = clean_text(request.content)
    except UnreadableFile as error:
        indexing.mark_failed(request.source_id)
        raise HTTPException(status_code=422, detail=str(error))

    chunks = await indexing.learn(request.source_id, request.workspace_id, text)
    return {"source_id": request.source_id, "chunks": chunks, "status": "ready"}


# File source ko "yaad" karo: andar ka text nikaalo, phir wahi learn
@app.post("/learn-file")
async def learn_file(
    source_id: int = Form(...),
    workspace_id: int = Form(...),
    file: UploadFile = File(...),
):
    try:
        text = extract_text(file.filename or "", await file.read())
    except UnreadableFile as error:
        indexing.mark_failed(source_id)
        raise HTTPException(status_code=422, detail=str(error))

    chunks = await indexing.learn(source_id, workspace_id, text)
    return {"source_id": source_id, "chunks": chunks, "status": "ready"}


# Agentic RAG (LangGraph): retrieve -> grade -> (rewrite+retry | generate | fallback)
@app.post("/ask")
async def ask(request: AskRequest):
    return await answer_question(request.workspace_id, request.question, request.reply_style, [line.as_dict() for line in request.history])


# Translate one message (Hindi <-> English...) and tell which language it was written in
@app.post("/translate")
async def translate_message(request: TranslateRequest):
    return await translate(request.text, request.target)


# The Sales agent: next message to a new lead + what they have answered so far
@app.post("/qualify")
async def qualify_lead(request: QualifyRequest):
    result = await qualify(request.questions, request.transcript)

    if result is None:
        raise HTTPException(status_code=502, detail="The model gave no usable answer")

    return result


# The Finance agent: promises with a date that the customer made in the chat
@app.post("/promises")
async def promises(request: PromisesRequest):
    found = await find_promises(request.transcript, request.today)

    if found is None:
        raise HTTPException(status_code=502, detail="The model gave no usable answer")

    return {"promises": found}


# The Operations agent: price, availability, orders and bookings from the real product list
@app.post("/operations")
async def operations(request: OperationsRequest):
    result = await operations_respond([p.model_dump() for p in request.products], request.transcript, request.today, request.needs_address, request.booking_rules)

    if result is None:
        raise HTTPException(status_code=502, detail="The model gave no usable answer")

    return result


# The Marketing agent: an offer message written from the owner's few words and the real products
@app.post("/campaign-draft")
async def campaign_draft(request: CampaignDraftRequest):
    result = await marketing_draft(request.business, [p.model_dump() for p in request.products], request.brief)

    if result is None:
        raise HTTPException(status_code=502, detail="The model gave no usable answer")

    return result
