import httpx
from fastapi import FastAPI

from app.config import settings

app = FastAPI(title="Automation AI service")


@app.get("/health")
def health():
    return {"status": "ok", "provider": settings.ai_provider}


# Kya ye service Ollama tak pahunch paa rahi hai, aur kaunse model hain
@app.get("/health/ollama")
async def health_ollama():
    try:
        async with httpx.AsyncClient(timeout=5) as client:
            response = await client.get(f"{settings.ollama_url}/api/tags")
            response.raise_for_status()
            models = [model["name"] for model in response.json().get("models", [])]

        # Ollama "name" aur "name:latest" ko same maano
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
