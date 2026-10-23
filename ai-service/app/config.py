from pydantic_settings import BaseSettings, SettingsConfigDict


# Saari AI settings ek hi jagah. Local me Ollama, production me Claude/OpenAI —
# sirf AI_PROVIDER aur .env badalne se switch ho jata hai. Koi confusion nahi.
class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    # ollama (local) | anthropic | openai (production)
    ai_provider: str = "ollama"

    # Local Ollama (Docker se host tak)
    ollama_url: str = "http://host.docker.internal:11434"
    ollama_chat_model: str = "qwen2.5:7b"
    ollama_embed_model: str = "nomic-embed-text"

    # nomic-embed-text = 768 dimensions
    embed_dim: int = 768

    # Postgres (pgvector) - vector table sirf yahीn (ai-service) manage hoti hai
    database_url: str = "postgresql://postgres:123@postgres:5432/automation"

    # Production keys (local me khali rehte hain)
    anthropic_api_key: str = ""
    openai_api_key: str = ""


settings = Settings()
