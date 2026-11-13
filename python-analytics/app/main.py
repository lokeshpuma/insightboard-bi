from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse
from app.api.endpoints import router
from app.core.config import settings

app = FastAPI(
    title="InsightBoard Analytics Service",
    description="Internal statistical profiling service for CSV datasets",
    version="1.0.0",
    docs_url=None if settings.app_env == "production" else "/docs",
    redoc_url=None,
)

@app.exception_handler(Exception)
async def global_exception_handler(request: Request, exc: Exception):
    return JSONResponse(
        status_code=500,
        content={"detail": "Internal analytics service error"},
    )

app.include_router(router)

