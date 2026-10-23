import httpx

from app.config import settings


# Abhi Ollama (local). Baad me provider badlega to yahीn OpenAI/Claude ka embed aayega.
# /api/embed naya endpoint hai: tez aur normalized vector (cosine search ke liye behtar).
async def embed(text: str) -> list[float]:
    async with httpx.AsyncClient(timeout=120) as client:
        response = await client.post(
            f"{settings.ollama_url}/api/embed",
            json={"model": settings.ollama_embed_model, "input": text},
        )
        response.raise_for_status()
        return response.json()["embeddings"][0]
