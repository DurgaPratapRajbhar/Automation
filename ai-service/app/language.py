"""Which language and script a customer writes in, decided in code so the model does not have to guess."""
import re

ENGLISH = "english"
HINDI = "hindi"        # Hindi in Devanagari
HINGLISH = "hinglish"  # Hindi in English (Roman) letters

DEVANAGARI = re.compile(r"[ऀ-ॿ]")
WORD = re.compile(r"[a-z]+")

# Words that only show up when Hindi is typed in English letters
HINGLISH_WORDS = {
    "hai", "hain", "ho", "hoon", "hu", "tha", "thi", "kya", "kyu", "kyun", "kaise", "kaisa", "kitna", "kitne", "kitni",
    "kab", "kahan", "kaha", "kaun", "kon", "nahi", "nahin", "haan", "han", "ji", "mujhe", "muje", "mera", "meri", "mere",
    "aap", "aapka", "aapki", "aapko", "apna", "apne", "tum", "hum", "hamara", "karo", "karna", "karwao", "karwana",
    "karte", "karta", "karenge", "chahiye", "chaiye", "batao", "bataiye", "bataye", "banwana", "banana", "banaoge",
    "theek", "thik", "accha", "acha", "bhai", "bhaiya", "aur", "bhi", "sirf", "abhi", "kal", "paisa", "padega", "milega",
    "dijiye", "dena", "lena", "wala", "wali", "liye", "mein", "kuch", "sab", "baat", "samajh", "pata", "dobara",
}

LABELS = {
    ENGLISH: "English",
    HINDI: "Hindi in Devanagari script (हिंदी)",
    HINGLISH: "Hinglish: Hindi written in English (Roman) letters, the way people type on WhatsApp",
}


def detect(text: str) -> str | None:
    """english / hindi / hinglish, or None when the text gives no clue (an emoji, 'ok', a number)."""
    if DEVANAGARI.search(text):
        return HINDI

    words = WORD.findall(text.lower())
    if not words:
        return None
    if any(word in HINGLISH_WORDS for word in words):
        return HINGLISH
    # One or two words such as "ok" or "thanks" say nothing about the language
    return ENGLISH if len(words) >= 3 else None


def of_customer(question: str, history: list) -> str:
    """The language to reply in: the latest message's, else the customer's last clear one, else English."""
    found = detect(question)
    if found:
        return found

    for line in reversed(history):
        if line.get("from") == "customer":
            found = detect(str(line.get("text", "")))
            if found:
                return found

    return ENGLISH


def label(language: str) -> str:
    return LABELS[language]
