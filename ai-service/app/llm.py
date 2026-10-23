import httpx

from app.config import settings


# Abhi Ollama chat (qwen2.5). Provider badlega to yahीn Claude/OpenAI aayega.
async def chat(system: str, user: str) -> str:
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
