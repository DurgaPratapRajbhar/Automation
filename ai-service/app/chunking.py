# Lambe text ko chhote tukdon me todo taaki embedding + search theek chale.
# Paragraph ke hisab se todte hain, phir ~chunk_size tak jodte hain, thoda overlap ke saath.
def chunk_text(text: str, chunk_size: int = 800, overlap: int = 100) -> list[str]:
    text = (text or "").strip()
    if not text:
        return []

    paragraphs = [p.strip() for p in text.split("\n") if p.strip()]

    chunks: list[str] = []
    current = ""

    for paragraph in paragraphs:
        if len(current) + len(paragraph) + 1 <= chunk_size:
            current = f"{current}\n{paragraph}".strip()
            continue

        if current:
            chunks.append(current)

        # Agar ek hi paragraph bada hai to usko tukdon me kaat do
        if len(paragraph) > chunk_size:
            for i in range(0, len(paragraph), chunk_size - overlap):
                chunks.append(paragraph[i:i + chunk_size])
            current = ""
        else:
            current = paragraph

    if current:
        chunks.append(current)

    return chunks
