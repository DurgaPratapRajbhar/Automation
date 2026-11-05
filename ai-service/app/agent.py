import json
import re
from typing import TypedDict

from langgraph.graph import END, START, StateGraph

from app import language
from app.llm import chat, chat_json
from app.retrieval import search

# Ek baar rewrite karke dobara dhundhne ka mauka
MAX_RETRIES = 1

# Is score se upar maano jaankari milti-julti hai (nomic cosine ~0 se 1)
RELEVANCE_THRESHOLD = 0.45

# How many of the customer's earlier messages join the search, so "haan" or "aur?" still finds the right topic
SEARCH_CONTEXT_LINES = 2

ANSWER_SYSTEM = (
    "You are the WhatsApp assistant of a business, talking to one of its customers. Talk like a good, polite member of "
    "the shop's staff: warm, natural and to the point.\n"
    "Facts: use ONLY the shop information given. Never invent a price, a service, a time or a promise.\n"
    "Conversation: read the chat so far and answer the customer's LATEST message in that context. A short reply such as "
    "'haan', 'yes', 'ok', 'karwao', 'theek hai' answers YOUR last question or offer: act on it, do not start again.\n"
    "Rules:\n"
    "- Greet only if this is the start of the chat. Never begin with 'Namaste' or 'Hello' once you have already talked.\n"
    "- Answer only what was asked, in 1 to 3 short sentences. Do not list everything you know.\n"
    "- Do not repeat something you already told the customer in this chat unless they ask for it again.\n"
    "- Write ONLY in the reply language given below, even if the shop information is in another language.\n"
    "- Always speak for the business as 'we' (hum / हम). Never say 'I' or 'main' / 'मैं', so you never need a he or she "
    "form (never 'karta hoon' or 'karti hoon'; say 'hum karte hain').\n"
    "- When asked what the business offers, name every service in the shop information, briefly.\n"
    "- If the shop information does not say whether the business offers something, never say it does not: say the "
    "owner will confirm, and set handoff to true.\n"
    "- If the shop information does not have the answer, say so honestly and offer to have the owner reply.\n"
    "Handoff: set handoff to true when the customer agrees to talk to the owner, asks for a person or a call, "
    "is upset or has to repeat themselves, or wants something only the owner can decide (a custom quote, a complaint). "
    "With handoff true, tell them the owner has been told and will reply here in this chat; do not promise a time.\n"
    'Reply with ONLY a JSON object: {"reply": "<the message to send>", "handoff": <true or false>}.'
)


REWRITE_SYSTEM = (
    "Rewrite the customer's question into a short search query (keywords) "
    "to look up a shop's knowledge base. Reply with only the query."
)

# The shop has taught the bot nothing (or the model failed): the owner has to answer, in the customer's language
FALLBACKS = {
    language.ENGLISH: "Sorry, we do not have that information here. We have told the owner, who will reply to you in this chat.",
    language.HINGLISH: "Maaf kijiye, yeh jaankari abhi humare paas nahi hai. Humne owner ko bata diya hai, woh isi chat me aapko reply karenge.",
    language.HINDI: "माफ़ कीजिए, यह जानकारी अभी हमारे पास नहीं है। हमने owner को बता दिया है, वे इसी चैट में आपको जवाब देंगे।",
}


class State(TypedDict):
    workspace_id: int
    reply_style: str
    question: str
    history: list
    query: str
    docs: list
    attempts: int
    relevant: bool
    answer: str | None
    handoff: bool


def context_of(docs: list) -> str:
    return "\n\n".join(f"- {doc['content']}" for doc in docs)


def transcript_of(history: list) -> str:
    """Earlier messages of the chat, oldest first, as 'Customer:' / 'Business:' lines."""
    lines = []
    for line in history:
        text = str(line.get("text", "")).strip()
        if text:
            lines.append(f"{'Customer' if line.get('from') == 'customer' else 'Business'}: {text}")
    return "\n".join(lines)


def search_query(question: str, history: list) -> str:
    """The latest message plus the customer's last few, so a short follow-up still searches the right topic."""
    earlier = [str(line.get("text", "")).strip() for line in history if line.get("from") == "customer"]
    return " ".join([*[text for text in earlier if text][-SEARCH_CONTEXT_LINES:], question]).strip()


def reply_language(state: State) -> str:
    """The customer's own language, or Hinglish always when the shop chose that reply style."""
    if state["reply_style"] == "hinglish":
        return language.HINGLISH
    return language.of_customer(state["question"], state["history"])


def parse(reply: str) -> dict | None:
    """The model's reply -> {reply, handoff}; plain text (a model that ignored JSON) is used as the reply."""
    cleaned = re.sub(r"^```(?:json)?|```$", "", reply.strip(), flags=re.MULTILINE).strip()

    try:
        data = json.loads(cleaned)
    except ValueError:
        return {"reply": cleaned, "handoff": False} if cleaned and not cleaned.startswith("{") else None

    if not isinstance(data, dict):
        return None

    text = str(data.get("reply", "")).strip()

    return {"reply": text, "handoff": data.get("handoff") is True} if text else None


def best_score(docs: list) -> float:
    return max((doc["score"] for doc in docs), default=0.0)


async def retrieve(state: State) -> dict:
    return {"docs": await search(state["workspace_id"], state["query"])}


# Deterministic grade: top chunk ka score dekh ke decide (flaky LLM yes/no nahi)
def grade(state: State) -> dict:
    return {"relevant": best_score(state["docs"]) >= RELEVANCE_THRESHOLD}


async def rewrite(state: State) -> dict:
    query = await chat(REWRITE_SYSTEM, state["query"])
    return {"query": query.strip(), "attempts": state["attempts"] + 1}


def system_for(reply_in: str) -> str:
    """The language rule goes first: small models follow what they read first."""
    return (
        f"IMPORTANT: write your reply ONLY in {language.label(reply_in)}. "
        "Never say the business does not offer something unless the shop information says so.\n\n"
        + ANSWER_SYSTEM
    )


async def generate(state: State) -> dict:
    reply_in = reply_language(state)
    chat_so_far = transcript_of(state["history"]) or "(this is the first message)"
    user = (
        f"Shop information:\n{context_of(state['docs'])}\n\n"
        f"Chat so far:\n{chat_so_far}\n\n"
        f"Customer's latest message: {state['question']}\n\n"
        f"Reply language: {language.label(reply_in)}"
    )
    result = parse(await chat_json(system_for(reply_in), user))

    # Checked in code: a reply in the wrong language is written once more, with the mistake pointed out
    if result and language.detect(result["reply"]) not in (reply_in, None):
        retry = user + f"\n\nYour last reply was not in {language.label(reply_in)}. Write it again, in {language.label(reply_in)} only."
        result = parse(await chat_json(system_for(reply_in), retry)) or result

    if result is None:
        return {"answer": FALLBACKS[reply_in], "handoff": True}

    return {"answer": result["reply"], "handoff": result["handoff"]}


def fallback(state: State) -> dict:
    return {"answer": FALLBACKS[reply_language(state)], "handoff": True}


def route(state: State) -> str:
    if state["relevant"]:
        return "generate"
    # Score kam: pehle ek baar behtar query se dobara dhundho
    if state["docs"] and state["attempts"] < MAX_RETRIES:
        return "rewrite"
    # Kuch docs hain (kam score) -> LLM ko hi grounded jawaab/decline karne do
    if state["docs"]:
        return "generate"
    # Dukaan ne kuch seekha hi nahi
    return "fallback"


def build_agent():
    graph = StateGraph(State)
    graph.add_node("retrieve", retrieve)
    graph.add_node("grade", grade)
    graph.add_node("rewrite", rewrite)
    graph.add_node("generate", generate)
    graph.add_node("fallback", fallback)

    graph.add_edge(START, "retrieve")
    graph.add_edge("retrieve", "grade")
    graph.add_conditional_edges("grade", route, {
        "generate": "generate",
        "rewrite": "rewrite",
        "fallback": "fallback",
    })
    graph.add_edge("rewrite", "retrieve")
    graph.add_edge("generate", END)
    graph.add_edge("fallback", END)

    return graph.compile()


agent = build_agent()


async def answer_question(workspace_id: int, question: str, reply_style: str = "auto", history: list | None = None) -> dict:
    history = history or []
    result = await agent.ainvoke({
        "workspace_id": workspace_id,
        "reply_style": reply_style,
        "question": question,
        "history": history,
        "query": search_query(question, history),
        "docs": [],
        "attempts": 0,
        "relevant": False,
        "answer": None,
        "handoff": False,
    })
    return {
        "answer": result["answer"],
        "handoff": result["handoff"],
        "used": result["docs"],
        "attempts": result["attempts"],
        "relevant": result["relevant"],
    }
