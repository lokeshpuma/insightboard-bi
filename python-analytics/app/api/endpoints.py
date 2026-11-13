from datetime import datetime, timezone
from fastapi import APIRouter, Depends, File, Form, HTTPException, UploadFile, status
from app.core.config import settings
from app.core.security import verify_internal_token
from app.schemas.analytics import AnalyzeResponse, HealthResponse
from app.services.profiler import profile_csv

router = APIRouter(prefix="/internal/v1")

@router.get("/health", response_model=HealthResponse)
def health_check():
    return HealthResponse(
        status="ok",
        version="1.0.0",
        timestamp=datetime.now(timezone.utc).isoformat(),
    )

@router.post("/analyze", response_model=AnalyzeResponse)
async def analyze_csv(
    file: UploadFile = File(...),
    delimiter: str = Form(default=","),
    encoding: str = Form(default="utf-8"),
    max_categories: int = Form(default=20),
    correlation_column_limit: int = Form(default=10),
    authenticated: bool = Depends(verify_internal_token),
):
    # Validate bounded limits
    if max_categories < 1 or max_categories > 100:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail="max_categories must be between 1 and 100",
        )
    if correlation_column_limit < 1 or correlation_column_limit > 50:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail="correlation_column_limit must be between 1 and 50",
        )

    # Read file content safely in chunks to enforce size limit
    max_bytes = settings.max_upload_size_mb * 1024 * 1024
    content = bytearray()
    
    while chunk := await file.read(1024 * 1024):  # 1MB chunks
        content.extend(chunk)
        if len(content) > max_bytes:
            raise HTTPException(
                status_code=status.HTTP_413_REQUEST_ENTITY_TOO_LARGE,
                detail=f"Uploaded file exceeds maximum limit of {settings.max_upload_size_mb} MB",
            )

    if len(content) == 0:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail="The uploaded CSV file is empty.",
        )

    try:
        result = profile_csv(
            file_bytes=bytes(content),
            delimiter=delimiter,
            encoding=encoding,
            max_categories=max_categories,
            correlation_column_limit=correlation_column_limit,
        )

        if result.row_count > settings.max_rows:
            raise HTTPException(
                status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
                detail=f"Dataset exceeds configured limit of {settings.max_rows} rows (received {result.row_count}).",
            )

        if result.column_count > settings.max_columns:
            raise HTTPException(
                status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
                detail=f"Dataset exceeds configured limit of {settings.max_columns} columns (received {result.column_count}).",
            )

        return result
    except HTTPException:
        raise
    except ValueError as e:
        raise HTTPException(status_code=status.HTTP_422_UNPROCESSABLE_ENTITY, detail=str(e))
    except Exception as e:
        # Never leak internal stack traces
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="An error occurred while profiling the CSV file. Please verify delimiter and file structure.",
        )
