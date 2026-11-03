"""The Sales agent: talks to a new lead, asks the owner's questions one at a time, and says how ready the lead is."""
import json
import re

from app.llm import chat_json

SYSTEM = (
    "You are the sales assistant of a business, chatting with a new customer on WhatsApp. "
    "Answer in the language the customer writes in, short and friendly. "
    "The owner wants to know these things, in this order:\n{questions}\n"
    "Read the whole chat. Note every question the customer has already answered. "
    "Then write the next WhatsApp message: first answer what the customer asked (only if you really know; never invent prices "
    "or promises, say the owner will tell), then ask the FIRST question that is still unanswered. Ask only one question. "
    "When every question is answered, thank the customer and say the owner will contact them soon. "
    'Reply with ONLY a JSON object: {{"answers": [<one string per question, or null if not answered yet>], '
    '"reply": "<the message to send>", "done": <true when every question is answered>, '
    '"score": "<hot if the customer is ready to buy soon, warm if interested, cold if not interested>", '
    '"summary": "<one short line for the owner, in English: who needs what>"}}. Never add explanations.'
)

SCORES = ("hot", "warm", "cold")

# What a model writes instead of a real null
NOT_ANSWERED = ("", "null", "none", "n/a", "na", "unknown", "not answered", "not answered yet")


def parse(reply: str, count: int) -> dict | None:
    """The model's reply -> {answers, reply, done, score, summary}; None when it is not usable."""
    cleaned = re.sub(r"^```(?:json)?|```$", "", reply.strip(), flags=re.MULTILINE).strip()

    try:
        data = json.loads(cleaned)
        text = str(data.get("reply", "")).strip()
        answers = data.get("answers")
    except (ValueError, AttributeError):
        return None

    if not text or not isinstance(answers, list):
        return None

    answers = [None if str(a).strip().lower() in NOT_ANSWERED else str(a).strip() for a in answers[:count]]
    answers += [None] * (count - len(answers))
    score = str(data.get("score", "")).strip().lower()

    return {
        "answers": answers,
        "reply": text,
        "done": all(answers),  # trust the answers, not the model's own "done"
        "score": score if score in SCORES else "warm",
        "summary": str(data.get("summary", "")).strip(),
    }


async def qualify(questions: list[str], transcript: str, ask=chat_json) -> dict | None:
    """`transcript` = the chat so far, one line per message. `ask` = the model call (replaceable in tests)."""
    listed = "\n".join(f"{number}. {question}" for number, question in enumerate(questions, start=1))

    return parse(await ask(SYSTEM.format(questions=listed), transcript), len(questions))
