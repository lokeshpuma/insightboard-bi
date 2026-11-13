import hmac
from fastapi import HTTPException, Security, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer
from fastapi.security.api_key import APIKeyHeader
from app.core.config import settings

bearer_scheme = HTTPBearer(auto_error=False)
header_scheme = APIKeyHeader(name="X-Analytics-Token", auto_error=False)

def verify_internal_token(
    bearer: HTTPAuthorizationCredentials | None = Security(bearer_scheme),
    api_key: str | None = Security(header_scheme),
) -> bool:
    if not settings.service_token:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Server configuration error: ANALYTICS_SERVICE_TOKEN must be configured in environment",
        )

    token = None
    if bearer and bearer.credentials:
        token = bearer.credentials
    elif api_key:
        token = api_key

    if not token:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Unauthorized: Missing authentication token",
        )

    # Constant time comparison to prevent timing attacks
    if not hmac.compare_digest(token.strip(), settings.service_token.strip()):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Forbidden: Authentication token mismatch",
        )

    return True
