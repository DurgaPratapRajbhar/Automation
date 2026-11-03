import httpx

from app.config import settings
from app.retry import post_with_retry


# Provider .env ke AI_PROVIDER se chunta hai: local me Ollama, production me Gemini.
async def chat(system: str, user: str, json_mode: bool = False) -> str:
    if settings.ai_provider == "gemini":
        return await _gemini_chat(system, user, json_mode)
    return await _ollama_chat(system, user, json_mode)


# For the agents that must answer in JSON: the model is told to produce valid JSON only
async def chat_json(system: str, user: str) -> str:
    return await chat(system, user, json_mode=True)


async def _ollama_chat(system: str, user: str, json_mode: bool = False) -> str:
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
                **({"format": "json"} if json_mode else {}),
            },
        )
        response.raise_for_status()
        return response.json()["message"]["content"].strip()


async def _gemini_chat(system: str, user: str, json_mode: bool = False) -> str:
    async with httpx.AsyncClient(timeout=60) as client:
        response = await post_with_retry(
            client,
            f"{settings.gemini_url}/models/{settings.gemini_chat_model}:generateContent",
            headers={"x-goog-api-key": settings.gemini_api_key},
            json={
                "systemInstruction": {"parts": [{"text": system}]},
                "contents": [{"role": "user", "parts": [{"text": user}]}],
                "generationConfig": {"temperature": 0.2, **({"responseMimeType": "application/json"} if json_mode else {})},
            },
        )
        response.raise_for_status()
        parts = response.json()["candidates"][0]["content"]["parts"]
        return "".join(part.get("text", "") for part in parts).strip()
