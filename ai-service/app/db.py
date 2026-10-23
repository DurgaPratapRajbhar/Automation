import psycopg

from app.config import settings


def connect() -> psycopg.Connection:
    return psycopg.connect(settings.database_url)


# Vector table ai-service khud banata hai (Laravel me AI/vector kuch nahi).
# Source ya workspace delete -> chunks apne aap delete (ON DELETE CASCADE).
def ensure_schema() -> None:
    with connect() as conn, conn.cursor() as cur:
        cur.execute("CREATE EXTENSION IF NOT EXISTS vector")
        cur.execute(
            f"""
            CREATE TABLE IF NOT EXISTS knowledge_chunks (
                id bigserial PRIMARY KEY,
                knowledge_source_id bigint NOT NULL
                    REFERENCES knowledge_sources(id) ON DELETE CASCADE,
                workspace_id bigint NOT NULL
                    REFERENCES workspaces(id) ON DELETE CASCADE,
                content text NOT NULL,
                embedding vector({settings.embed_dim}),
                created_at timestamptz NOT NULL DEFAULT now()
            )
            """
        )
        cur.execute(
            "CREATE INDEX IF NOT EXISTS knowledge_chunks_workspace_idx "
            "ON knowledge_chunks (workspace_id)"
        )
        conn.commit()
