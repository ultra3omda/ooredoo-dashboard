import httpx
import os
from fastapi import FastAPI, Request
from fastapi.responses import Response
from contextlib import asynccontextmanager
import asyncio

@asynccontextmanager
async def lifespan(app: FastAPI):
    print("Backend proxy started - proxying to Nginx+PHP-FPM on port 8002")
    yield

app = FastAPI(lifespan=lifespan)

PHP_BASE_URL = "http://127.0.0.1:8002"
EXTERNAL_HOST = os.environ.get("APP_URL", "").replace("https://", "").replace("http://", "")

@app.api_route("/{path:path}", methods=["GET", "POST", "PUT", "DELETE", "PATCH", "OPTIONS", "HEAD"])
async def proxy(request: Request, path: str):
    target_url = f"{PHP_BASE_URL}/{path}"
    if request.url.query:
        target_url += f"?{request.url.query}"
    
    body = await request.body()
    
    headers = {}
    for key, value in request.headers.items():
        lower_key = key.lower()
        if lower_key not in ("host", "transfer-encoding", "connection"):
            headers[key] = value
    
    # Add forwarding headers
    ext_host = EXTERNAL_HOST or request.headers.get("host", "localhost")
    headers["Host"] = ext_host
    headers["X-Forwarded-For"] = request.client.host if request.client else "127.0.0.1"
    headers["X-Forwarded-Proto"] = "https"
    headers["X-Forwarded-Host"] = ext_host
    headers["X-Forwarded-Port"] = "443"
    
    try:
        async with httpx.AsyncClient(timeout=300.0, follow_redirects=False) as client:
            response = await client.request(
                method=request.method,
                url=target_url,
                headers=headers,
                content=body,
            )
            
            raw_response = Response(
                content=response.content,
                status_code=response.status_code,
            )
            
            for key, value in response.headers.multi_items():
                lower_key = key.lower()
                if lower_key in ("transfer-encoding", "content-encoding", "content-length"):
                    continue
                if lower_key == "set-cookie":
                    raw_response.headers.append("set-cookie", value)
                elif lower_key == "location":
                    new_location = value.replace("http://127.0.0.1:8002", "").replace("http://localhost:8002", "")
                    raw_response.headers[key] = new_location
                else:
                    raw_response.headers[key] = value
            
            return raw_response
            
    except httpx.ConnectError:
        return Response(content="PHP server not ready.", status_code=503)
    except Exception as e:
        return Response(content=f"Proxy error: {str(e)}", status_code=502)

@app.get("/")
async def root(request: Request):
    return await proxy(request, "")
