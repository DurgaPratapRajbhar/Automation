"""Translate one WhatsApp message into a chosen language, and say which language it was written in."""
import json
import re

from app.llm import chat_json

SYSTEM = (
    "You translate WhatsApp messages between a business and its customers. "
    "Translate the message into {target}. Keep the meaning, the tone, names, numbers, links and emojis exactly. "
    "Write naturally and politely, the way a professional business would. "
    "Pick one wording; never write alternatives with slashes (for example not दूँगा/दूँगी). "
    'Reply with ONLY a JSON object with two keys, in this order: '
    '{{"original_language": "<ISO 639-1 code of the language the ORIGINAL message is written in, '
    'for example en or hi. It is NOT the language you translate into>", '
    '"translation": "<the translated message>"}}. '
    "If the message is already in {target}, return it unchanged. Never add explanations."
)


def parse(reply: str) -> dict:
    """The model's reply -> {"translation": str, "language": str | None}. Falls back to the plain text."""
    cleaned = re.sub(r"^```(?:json)?|```$", "", reply.strip(), flags=re.MULTILINE).strip()

    try:
        data = json.loads(cleaned)
        translation = str(data.get("translation", "")).strip()
        language = str(data.get("original_language") or data.get("language") or "").strip().lower() or None
    except (ValueError, AttributeError):
        translation, language = cleaned, None

    return {"translation": translation, "language": language}


async def translate(text: str, target: str, ask=chat_json) -> dict:
    """`target` is the language's name in English, e.g. "Hindi". `ask` = the model call (replaceable in tests)."""
    return parse(await ask(SYSTEM.format(target=target), text))
