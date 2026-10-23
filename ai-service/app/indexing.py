from app import db
from app.chunking import chunk_text
from app.embeddings import embed


# Diye gaye text ko tukde -> embed -> store. Purane chunks hata ke naye. Chunk count lautaata hai.
async def learn(source_id: int, workspace_id: int, text: str) -> int:
    chunks = chunk_text(text)

    with db.connect() as conn, conn.cursor() as cur:
        cur.execute("DELETE FROM knowledge_chunks WHERE knowledge_source_id = %s", (source_id,))

        for chunk in chunks:
            vector = db.to_vector(await embed(chunk))
            cur.execute(
                """
                INSERT INTO knowledge_chunks (knowledge_source_id, workspace_id, content, embedding)
                VALUES (%s, %s, %s, %s::vector)
                """,
                (source_id, workspace_id, chunk, vector),
            )

        cur.execute(
            "UPDATE knowledge_sources SET status = 'ready', updated_at = now() WHERE id = %s",
            (source_id,),
        )
        conn.commit()

    return len(chunks)
