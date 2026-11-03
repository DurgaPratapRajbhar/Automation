"""The Marketing agent: writes a WhatsApp offer message (a template for Meta to approve) from a few words and the real products."""
import json
import re

from app.llm import chat_json

SYSTEM = (
    "You write WhatsApp marketing messages for a small business in India. The business is {business}. "
    "The owner's request: the message must be about this. Write it in friendly Hinglish (Roman Hindi mixed with simple English) "
    "unless the request asks for another language. Short: 5 to 8 lines, a few emojis, one clear call to action "
    "(reply on WhatsApp to order or book). Start with 'Namaste {{{{1}}}}!' where {{{{1}}}} is the customer's name placeholder "
    "(write it exactly like that, once, only in the greeting).\n"
    "The business sells (name | price):\n{products}\n"
    "Rules: mention ONLY products from this list, with prices ONLY as listed. Never invent a discount, a free gift, a deadline or a "
    "delivery promise that the request did not mention. If the request names no product, pick the 2 or 3 most useful ones. "
    'Reply with ONLY a JSON object: {{"name": "<short template name: small letters, numbers and underscores, e.g. sunday_offer>", '
    '"language": "<hi or en>", "body": "<the message>"}}. Never add explanations.'
)


def parse(reply: str) -> dict | None:
    """The model's reply -> {name, language, body}; None when it is not usable."""
    cleaned = re.sub(r"^```(?:json)?|```$", "", reply.strip(), flags=re.MULTILINE).strip()

    try:
        data = json.loads(cleaned)
        body = str(data.get("body", "")).strip()
    except (ValueError, AttributeError):
        return None

    if not body:
        return None

    name = re.sub(r"[^a-z0-9_]+", "_", str(data.get("name", "")).strip().lower()).strip("_")[:40] or "offer"
    language = str(data.get("language", "hi")).strip().lower()

    return {"name": name, "language": language if language in ("hi", "en") else "hi", "body": body}


def describe(products: list[dict]) -> str:
    """The product list as the model reads it."""
    return "\n".join(f"{p['name']} | Rs {p['price']}" + (f" per {p['unit']}" if p.get("unit") else "") for p in products) or "(no products listed)"


async def draft(business: str, products: list[dict], brief: str, ask=chat_json) -> dict | None:
    """`ask` = the model call (replaceable in tests)."""
    return parse(await ask(SYSTEM.format(business=business, products=describe(products)), brief))
