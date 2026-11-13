import os
from pydantic import BaseModel

class Settings(BaseModel):
    app_env: str = os.getenv("APP_ENV", "local")
    service_token: str = os.getenv("ANALYTICS_SERVICE_TOKEN", "")
    max_upload_size_mb: int = int(os.getenv("MAX_UPLOAD_SIZE_MB", "50"))
    max_rows: int = int(os.getenv("MAX_ROWS", "100000"))
    max_columns: int = int(os.getenv("MAX_COLUMNS", "100"))
    log_level: str = os.getenv("LOG_LEVEL", "INFO")

settings = Settings()
