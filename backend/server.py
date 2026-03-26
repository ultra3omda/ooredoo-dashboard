import httpx
import os
from fastapi import FastAPI, Request
from fastapi.responses import StreamingResponse, Response
from contextlib import asynccontextmanager
import subprocess
import time
import signal
import asyncio

php_process = None

@asynccontextmanager
async def lifespan(app: FastAPI):
    global php_process
    # Start PHP artisan serve on port 8002
    php_process = subprocess.Popen(
        ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8002"],
        cwd="/app",
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE
    )
    # Wait for PHP to start
    await asyncio.sleep(2)
    print(f"PHP artisan serve started on port 8002 (PID: {php_process.pid})")
    yield
    # Cleanup
    if php_process:
        php_process.terminate()
        php_process.wait()

app = FastAPI(lifespan=lifespan)

PHP_BASE_URL = "http://127.0.0.1:8002"

@app.api_route("/{path:path}", methods=["GET", "POST", "PUT", "DELETE", "PATCH", "OPTIONS", "HEAD"])
async def proxy(request: Request, path: str):
    """Proxy all requests to PHP Laravel"""
    # Build target URL
    target_url = f"{PHP_BASE_URL}/{path}"
    if request.url.query:
        target_url += f"?{request.url.query}"
    
    # Get request body
    body = await request.body()
    
    # Build headers (forward most headers)
    headers = dict(request.headers)
    headers.pop("host", None)
    headers.pop("transfer-encoding", None)
    
    try:
        async with httpx.AsyncClient(timeout=120.0, follow_redirects=False) as client:
            response = await client.request(
                method=request.method,
                url=target_url,
                headers=headers,
                content=body,
            )
            
            # Build response headers
            resp_headers = dict(response.headers)
            resp_headers.pop("transfer-encoding", None)
            resp_headers.pop("content-encoding", None)
            resp_headers.pop("content-length", None)
            
            return Response(
                content=response.content,
                status_code=response.status_code,
                headers=resp_headers,
            )
    except httpx.ConnectError:
        return Response(
            content="PHP server not ready. Please wait...",
            status_code=503,
        )
    except Exception as e:
        return Response(
            content=f"Proxy error: {str(e)}",
            status_code=502,
        )

@app.get("/")
async def root(request: Request):
    return await proxy(request, "")
