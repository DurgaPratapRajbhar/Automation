import httpx

from app.config import settings
from app.retry import post_with_retry


# Provider .env ke AI_PROVIDER se chunta hai. Dono embed_dim (768) size ka vector dete hain.
# Provider badla to purani knowledge dobara index karni padegi (alag model ke vector milte nahi).
async def embed(text: str) -> list[float]:
    if settings.ai_provider == "gemini":
        return await _gemini_embed(text)
    return await _ollama_embed(text)


# /api/embed naya endpoint hai: tez aur normalized vector (cosine search ke liye behtar).
async def _ollama_embed(text: str) -> list[float]:
    async with httpx.AsyncClient(timeout=120) as client:
        response = await client.post(
            f"{settings.ollama_url}/api/embed",
            json={"model": settings.ollama_embed_model, "input": text},
        )
        response.raise_for_status()
        return response.json()["embeddings"][0]


async def _gemini_embed(text: str) -> list[float]:
    async with httpx.AsyncClient(timeout=60) as client:
        response = await post_with_retry(
            client,
            f"{settings.gemini_url}/models/{settings.gemini_embed_model}:embedContent",
            headers={"x-goog-api-key": settings.gemini_api_key},
            json={
                "content": {"parts": [{"text": text}]},
                "outputDimensionality": settings.embed_dim,
            },
        )
        response.raise_for_status()
        return response.json()["embedding"]["values"]
