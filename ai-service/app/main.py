from contextlib import asynccontextmanager

import httpx
from fastapi import FastAPI
from pydantic import BaseModel

from app import db
from app.agent import answer_question
from app.chunking import chunk_text
from app.config import settings
from app.embeddings import embed


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


# Ek knowledge source ko "yaad" karo: tukde -> embed -> store. Dobara bheja to purane tukde hata ke naye.
@app.post("/learn")
async def learn(request: LearnRequest):
    chunks = chunk_text(request.content)

    with db.connect() as conn, conn.cursor() as cur:
        cur.execute(
            "DELETE FROM knowledge_chunks WHERE knowledge_source_id = %s",
            (request.source_id,),
        )

        for chunk in chunks:
            vector = db.to_vector(await embed(chunk))
            cur.execute(
                """
                INSERT INTO knowledge_chunks (knowledge_source_id, workspace_id, content, embedding)
                VALUES (%s, %s, %s, %s::vector)
                """,
                (request.source_id, request.workspace_id, chunk, vector),
            )

        # Source ko 'ready' mark karo (status knowledge_sources me Laravel ke liye)
        cur.execute(
            "UPDATE knowledge_sources SET status = 'ready', updated_at = now() WHERE id = %s",
            (request.source_id,),
        )
        conn.commit()

    return {"source_id": request.source_id, "chunks": len(chunks), "status": "ready"}


# Agentic RAG (LangGraph): retrieve -> grade -> (rewrite+retry | generate | fallback)
@app.post("/ask")
async def ask(request: AskRequest):
    return await answer_question(request.workspace_id, request.question)
