import io
import re

SUPPORTED = {"pdf", "docx", "txt", "csv", "md"}

# Characters that never belong in readable text (NUL and other control codes; newline and tab are fine)
_CONTROL = re.compile(r"[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]")

MIN_LETTERS = 20


class UnreadableFile(ValueError):
    """The file or text has nothing the AI can safely learn from."""


# File ke andar ka likha nikaalo (PDF/Word/text). Taaki AI usey bhi padh sake.
def extract_text(filename: str, data: bytes) -> str:
    ext = filename.lower().rsplit(".", 1)[-1] if "." in filename else ""

    if ext not in SUPPORTED:
        raise UnreadableFile(f"Ye file type nahi padh sakte: .{ext or '?'}")

    try:
        if ext == "pdf":
            from pypdf import PdfReader

            reader = PdfReader(io.BytesIO(data))
            raw = "\n".join((page.extract_text() or "") for page in reader.pages)
        elif ext == "docx":
            from docx import Document

            document = Document(io.BytesIO(data))
            raw = "\n".join(p.text for p in document.paragraphs)
        else:
            # txt, csv, md: sirf sahi UTF-8 text; kisi aur cheez ko text samajh kar mat padho
            raw = data.decode("utf-8-sig")
    except UnicodeDecodeError as error:
        raise UnreadableFile("File sahi text (UTF-8) nahi hai.") from error
    except Exception as error:
        raise UnreadableFile("File kharab hai ya khul nahi rahi.") from error

    return clean_text(raw)


# Kachra hata ke saaf text lautao; padhne layak kuch na ho to UnreadableFile
def clean_text(text: str) -> str:
    text = _CONTROL.sub("", (text or "").replace("\r\n", "\n").replace("\r", "\n"))
    text = re.sub(r"[ \t]+", " ", text)
    text = re.sub(r"\n{3,}", "\n\n", text).strip()

    letters = sum(char.isalpha() for char in text)
    if letters < MIN_LETTERS:
        raise UnreadableFile("Padhne layak text nahi mila (file khaali ya scan ki hui lagti hai).")
    if letters < len(text) * 0.3:
        raise UnreadableFile("Ye text kachra jaisa lag raha hai, padhne layak nahi.")

    return text
