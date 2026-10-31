from pydantic_settings import BaseSettings, SettingsConfigDict


# Saari AI settings ek hi jagah. Local me Ollama, production me Gemini —
# sirf AI_PROVIDER aur .env badalne se switch ho jata hai. Koi confusion nahi.
class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    # ollama (local) | gemini (production)
    ai_provider: str = "ollama"

    # Local Ollama (Docker se host tak)
    ollama_url: str = "http://host.docker.internal:11434"
    ollama_chat_model: str = "qwen2.5:7b"
    ollama_embed_model: str = "nomic-embed-text"

    # Google Gemini (production)
    gemini_api_key: str = ""
    gemini_url: str = "https://generativelanguage.googleapis.com/v1beta"
    gemini_chat_model: str = "gemini-3-flash-preview"
    gemini_embed_model: str = "gemini-embedding-001"

    # nomic-embed-text = 768 dimensions; Gemini embeddings are asked for the same size
    embed_dim: int = 768

    # Postgres (pgvector) - vector table sirf yahीn (ai-service) manage hoti hai
    database_url: str = "postgresql://postgres:123@postgres:5432/automation"


settings = Settings()
