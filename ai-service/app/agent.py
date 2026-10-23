from typing import TypedDict

from langgraph.graph import END, START, StateGraph

from app.llm import chat
from app.retrieval import search

# Ek baar rewrite karke dobara dhundhne ka mauka
MAX_RETRIES = 1

# Is score se upar maano jaankari milti-julti hai (nomic cosine ~0 se 1)
RELEVANCE_THRESHOLD = 0.45

ANSWER_SYSTEM = (
    "You are a helpful assistant for a shop, answering the shop's customers on WhatsApp. "
    "Answer using ONLY the shop information given. "
    "If the answer is not in it, politely say you are not sure and offer to connect them to the shop. "
    "Keep replies short and friendly, and reply in the same language the customer used."
)

REWRITE_SYSTEM = (
    "Rewrite the customer's question into a short search query (keywords) "
    "to look up a shop's knowledge base. Reply with only the query."
)

FALLBACK = "Sorry, I'm not sure about that. Let me connect you to the shop so they can help."


class State(TypedDict):
    workspace_id: int
    question: str
    query: str
    docs: list
    attempts: int
    relevant: bool
    answer: str | None


def context_of(docs: list) -> str:
    return "\n\n".join(f"- {doc['content']}" for doc in docs)


def best_score(docs: list) -> float:
    return max((doc["score"] for doc in docs), default=0.0)


async def retrieve(state: State) -> dict:
    return {"docs": await search(state["workspace_id"], state["query"])}


# Deterministic grade: top chunk ka score dekh ke decide (flaky LLM yes/no nahi)
def grade(state: State) -> dict:
    return {"relevant": best_score(state["docs"]) >= RELEVANCE_THRESHOLD}


async def rewrite(state: State) -> dict:
    query = await chat(REWRITE_SYSTEM, state["question"])
    return {"query": query.strip(), "attempts": state["attempts"] + 1}


async def generate(state: State) -> dict:
    user = f"Shop information:\n{context_of(state['docs'])}\n\nCustomer question: {state['question']}"
    return {"answer": await chat(ANSWER_SYSTEM, user)}


def fallback(state: State) -> dict:
    return {"answer": FALLBACK}


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


async def answer_question(workspace_id: int, question: str) -> dict:
    result = await agent.ainvoke({
        "workspace_id": workspace_id,
        "question": question,
        "query": question,
        "docs": [],
        "attempts": 0,
        "relevant": False,
        "answer": None,
    })
    return {
        "answer": result["answer"],
        "used": result["docs"],
        "attempts": result["attempts"],
        "relevant": result["relevant"],
    }
