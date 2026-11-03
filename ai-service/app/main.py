from contextlib import asynccontextmanager

import httpx
from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from pydantic import BaseModel

from app import db, indexing
from app.agent import answer_question
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


class AskRequest(BaseModel):
    workspace_id: int
    question: str
    reply_style: str = "auto"  # auto | hinglish


class TranslateRequest(BaseModel):
    text: str
    target: str  # the language's name in English, e.g. "Hindi"


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
    return await answer_question(request.workspace_id, request.question, request.reply_style)


# Translate one message (Hindi <-> English...) and tell which language it was written in
@app.post("/translate")
async def translate_message(request: TranslateRequest):
    return await translate(request.text, request.target)
