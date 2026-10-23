from app import db
from app.embeddings import embed


# Sawaal ke sabse milte-julte chunks us workspace (dukaan) se nikaalo.
# pgvector cosine distance (<=>); nomic-embed normalized hai to cosine theek rehta hai.
async def search(workspace_id: int, query: str, k: int = 4) -> list[dict]:
    vector = db.to_vector(await embed(query))

    with db.connect() as conn, conn.cursor() as cur:
        cur.execute(
            """
            SELECT content, 1 - (embedding <=> %s::vector) AS score
            FROM knowledge_chunks
            WHERE workspace_id = %s
            ORDER BY embedding <=> %s::vector
            LIMIT %s
            """,
            (vector, workspace_id, vector, k),
        )
        rows = cur.fetchall()

    return [{"content": content, "score": float(score)} for content, score in rows]
