"""The Operations agent: answers price and availability from the business's real product list, and takes orders and bookings."""
import json
import re
from datetime import date

from app.llm import chat_json

SYSTEM = (
    "You are the orders assistant of a business, chatting with a customer on WhatsApp. "
    "Answer in the language the customer writes in, short and friendly. Today is {today} ({weekday}).\n"
    "These are ALL the products and services the business sells (id | name | price | stock):\n{products}\n"
    "Rules: tell prices and availability ONLY from this list. Never invent a product, a price, a discount or a stock number. "
    "If the customer asks for something not in the list, say the owner will tell them. Stock 'not counted' means it is available. "
    "When the customer clearly wants to buy or book specific products from the list and you know the quantity "
    "(a booking also needs a date and time), set ready to true and fill items. Otherwise ask for the ONE thing that is missing "
    "and set ready to false. Do not say the order is confirmed: the owner confirms it. "
    'Reply with ONLY a JSON object: {{"reply": "<the message to send>", "items": [{{"product_id": <id>, "quantity": <whole number>}}], '
    '"booking_at": "<YYYY-MM-DD HH:MM or null>", "address": "<the delivery address the customer gave, or null>", '
    '"ready": <true or false>}}. Never add explanations.'
)

# Added when the business delivers to the customer's door (a booking needs no address)
DELIVERY = (
    " This business delivers. For an order of products you must have the customer's full delivery address before you set ready "
    "to true: ask for it if it is missing."
)


def parse(reply: str) -> dict | None:
    """The model's reply -> {reply, items, booking_at, ready}; None when it is not usable."""
    cleaned = re.sub(r"^```(?:json)?|```$", "", reply.strip(), flags=re.MULTILINE).strip()

    try:
        data = json.loads(cleaned)
        text = str(data.get("reply", "")).strip()
    except (ValueError, AttributeError):
        return None

    if not text:
        return None

    items = []
    for item in data.get("items") or []:
        try:
            quantity = int(item["quantity"])
            if quantity > 0:
                items.append({"product_id": int(item["product_id"]), "quantity": quantity})
        except (KeyError, ValueError, TypeError):
            continue  # an item we cannot read is dropped, not guessed

    booking_at = data.get("booking_at")

    return {
        "reply": text,
        "items": items,
        "booking_at": str(booking_at).strip() if booking_at and str(booking_at).lower() != "null" else None,
        "address": _address(data.get("address")),
        "ready": data.get("ready") is True and bool(items or booking_at),
    }


def _address(value) -> str | None:
    text = str(value).strip() if value else ""

    return text[:500] if text and text.lower() != "null" else None


def describe(products: list[dict]) -> str:
    """The product list as the model reads it."""
    lines = []
    for product in products:
        stock = "not counted" if product.get("stock") is None else product["stock"]
        unit = f" per {product['unit']}" if product.get("unit") else ""
        lines.append(f"{product['id']} | {product['name']} | Rs {product['price']}{unit} | {stock}")

    return "\n".join(lines)


async def respond(products: list[dict], transcript: str, today: date, needs_address: bool = False, booking_rules: str | None = None, ask=chat_json) -> dict | None:
    """`products` = [{id, name, price, unit, stock}]. `ask` = the model call (replaceable in tests)."""
    system = SYSTEM.format(today=today.isoformat(), weekday=today.strftime("%A"), products=describe(products))
    system += DELIVERY if needs_address else ""
    system += f"\n{booking_rules}" if booking_rules else ""

    return parse(await ask(system, transcript))
