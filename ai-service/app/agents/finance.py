"""The Finance agent: finds promises with a date in a chat ("kal payment kar dunga", "send it by Friday")."""
import json
import re
from datetime import date

from app.llm import chat_json

KINDS = ("payment", "documents", "callback", "other")

SYSTEM = (
    "You read a WhatsApp chat between a business and a customer. Find promises the CUSTOMER made in their latest message "
    "that have a date or a time: to pay, to send documents or things, to call back or come, or anything else. "
    "Today is {today} ({weekday}). Turn every date the customer says (tomorrow, kal, parso, Friday, next week, 10 Nov) "
    "into a real date YYYY-MM-DD. Skip promises without any time, questions, and what the business promised. "
    "Also skip a customer's REQUEST to the business (to book an appointment or a table, to order something, to deliver): "
    "that is not a promise by the customer. "
    'Reply with ONLY a JSON object: {{"promises": [{{"kind": "payment|documents|callback|other", '
    '"quote": "<the customer\'s own words>", "amount": <number in rupees or null>, "due_on": "YYYY-MM-DD"}}]}}. '
    'If there is no such promise reply {{"promises": []}}. Never add explanations.'
)


def parse(reply: str) -> list | None:
    """The model's reply -> a list of {kind, quote, amount, due_on} (maybe empty); None when it is not usable."""
    cleaned = re.sub(r"^```(?:json)?|```$", "", reply.strip(), flags=re.MULTILINE).strip()

    try:
        items = json.loads(cleaned)["promises"]
    except (ValueError, KeyError, TypeError):
        return None

    if not isinstance(items, list):
        return None

    promises = []
    for item in items:
        try:
            due = date.fromisoformat(str(item["due_on"]))
            amount = item.get("amount")
            promises.append({
                "kind": item.get("kind") if item.get("kind") in KINDS else "other",
                "quote": str(item["quote"]).strip()[:500],
                "amount": float(amount) if isinstance(amount, (int, float)) and amount > 0 else None,
                "due_on": due.isoformat(),
            })
        except (KeyError, ValueError, TypeError):
            continue  # a promise without a real date is not a promise we can track

    return promises


async def find_promises(transcript: str, today: date, ask=chat_json) -> list | None:
    """`transcript` = the last messages, one line per message. `ask` = the model call (replaceable in tests)."""
    system = SYSTEM.format(today=today.isoformat(), weekday=today.strftime("%A"))

    return parse(await ask(system, transcript))
