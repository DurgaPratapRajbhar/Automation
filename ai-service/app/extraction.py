import io


# File ke andar ka likha nikaalo (PDF/Word/text). Taaki AI usey bhi padh sake.
def extract_text(filename: str, data: bytes) -> str:
    ext = filename.lower().rsplit(".", 1)[-1] if "." in filename else ""

    if ext == "pdf":
        from pypdf import PdfReader

        reader = PdfReader(io.BytesIO(data))
        return "\n".join((page.extract_text() or "") for page in reader.pages)

    if ext == "docx":
        from docx import Document

        document = Document(io.BytesIO(data))
        return "\n".join(p.text for p in document.paragraphs)

    # txt, csv, md (aur .doc best-effort) - plain text
    try:
        return data.decode("utf-8")
    except UnicodeDecodeError:
        return data.decode("latin-1", errors="ignore")
