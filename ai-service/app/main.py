from contextlib import asynccontextmanager

import httpx
from fastapi import FastAPI, File, Form, UploadFile
from pydantic import BaseModel

from app import db, indexing
from app.agent import answer_question
from app.config import settings
from app.extraction import extract_text


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
    chunks = await indexing.learn(request.source_id, request.workspace_id, request.content)
    return {"source_id": request.source_id, "chunks": chunks, "status": "ready"}


# File source ko "yaad" karo: andar ka text nikaalo, phir wahi learn
@app.post("/learn-file")
async def learn_file(
    source_id: int = Form(...),
    workspace_id: int = Form(...),
    file: UploadFile = File(...),
):
    text = extract_text(file.filename or "", await file.read())
    chunks = await indexing.learn(source_id, workspace_id, text)
    return {"source_id": source_id, "chunks": chunks, "status": "ready"}


# Agentic RAG (LangGraph): retrieve -> grade -> (rewrite+retry | generate | fallback)
@app.post("/ask")
async def ask(request: AskRequest):
    return await answer_question(request.workspace_id, request.question)
