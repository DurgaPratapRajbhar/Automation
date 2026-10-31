import httpx

from app.config import settings


# Provider .env ke AI_PROVIDER se chunta hai: local me Ollama, production me Gemini.
async def chat(system: str, user: str) -> str:
    if settings.ai_provider == "gemini":
        return await _gemini_chat(system, user)
    return await _ollama_chat(system, user)


async def _ollama_chat(system: str, user: str) -> str:
    async with httpx.AsyncClient(timeout=180) as client:
        response = await client.post(
            f"{settings.ollama_url}/api/chat",
            json={
                "model": settings.ollama_chat_model,
                "messages": [
                    {"role": "system", "content": system},
                    {"role": "user", "content": user},
                ],
                "stream": False,
                "options": {"temperature": 0.2},
            },
        )
        response.raise_for_status()
        return response.json()["message"]["content"].strip()


async def _gemini_chat(system: str, user: str) -> str:
    async with httpx.AsyncClient(timeout=60) as client:
        response = await client.post(
            f"{settings.gemini_url}/models/{settings.gemini_chat_model}:generateContent",
            headers={"x-goog-api-key": settings.gemini_api_key},
            json={
                "systemInstruction": {"parts": [{"text": system}]},
                "contents": [{"role": "user", "parts": [{"text": user}]}],
                "generationConfig": {"temperature": 0.2},
            },
        )
        response.raise_for_status()
        parts = response.json()["candidates"][0]["content"]["parts"]
        return "".join(part.get("text", "") for part in parts).strip()
