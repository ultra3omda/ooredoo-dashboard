import httpx
import os
from fastapi import FastAPI, Request
from fastapi.responses import Response, JSONResponse
from contextlib import asynccontextmanager
import asyncio
from dotenv import load_dotenv

load_dotenv()

@asynccontextmanager
async def lifespan(app: FastAPI):
    print("Backend proxy started - ensuring PHP-FPM and Nginx are running...")
    import subprocess
    # Ensure PHP-FPM is running
    result = subprocess.run(["pgrep", "-f", "php-fpm"], capture_output=True)
    if result.returncode != 0:
        print("Starting PHP-FPM...")
        subprocess.run(["mkdir", "-p", "/run/php"], check=False)
        # Try multiple PHP-FPM binary locations
        for fpm_bin in ["/usr/sbin/php-fpm8.2", "php-fpm8.2", "php-fpm"]:
            try:
                subprocess.run([fpm_bin, "--daemonize"], check=False)
                print(f"Started PHP-FPM via {fpm_bin}")
                break
            except FileNotFoundError:
                continue
        else:
            # Fallback: try service command
            subprocess.run(["service", "php8.2-fpm", "start"], check=False, capture_output=True)
            print("Started PHP-FPM via service command")
    # Ensure Nginx has the Laravel config and is serving port 8002
    result = subprocess.run(["curl", "-s", "-o", "/dev/null", "-w", "%{http_code}", "http://127.0.0.1:8002/"], capture_output=True, text=True)
    if result.stdout.strip() != "200":
        print("Reloading Nginx...")
        subprocess.run(["nginx", "-s", "reload"], check=False, capture_output=True)
    # Fix storage permissions
    subprocess.run(["chmod", "-R", "777", "/app/storage/logs/", "/app/storage/framework/"], check=False, capture_output=True)
    print("Backend proxy ready - proxying to Nginx+PHP-FPM on port 8002")
    yield

app = FastAPI(lifespan=lifespan)

PHP_BASE_URL = "http://127.0.0.1:8002"
EXTERNAL_HOST = os.environ.get("APP_URL", "").replace("https://", "").replace("http://", "")

@app.post("/api/report-ai-suggestions")
async def report_ai_suggestions(request: Request):
    try:
        from emergentintegrations.llm.chat import LlmChat, UserMessage
        body = await request.json()
        prompt = body.get("prompt", "")
        report_type = body.get("report_type", "ceo")

        if not prompt:
            return JSONResponse({"suggestions": ""})

        api_key = os.environ.get("EMERGENT_LLM_KEY", "")
        if not api_key:
            return JSONResponse({"suggestions": "Clé API non configurée"}, status_code=500)

        chat = LlmChat(
            api_key=api_key,
            session_id=f"report-{report_type}-{id(request)}",
            system_message="Tu es un analyste business senior expert en programmes de fidelite, marketing digital et data science en Tunisie. Tu analyses des KPIs hebdomadaires enrichis de predictions ML (machine learning) - segments clients, probabilites de churn, scores d'engagement et de lifetime value. Tu fournis des recommandations strategiques precises, actionables et prioritisees basees sur ces insights data-driven. Reponds toujours en francais. Format: liste numerotee avec priorite (P0/P1/P2) et impact estime."
        )
        chat.with_model("openai", "gpt-4o")

        user_message = UserMessage(text=prompt)
        response = await chat.send_message(user_message)

        return JSONResponse({"suggestions": response})
    except Exception as e:
        return JSONResponse({"suggestions": f"Suggestions IA indisponibles: {str(e)}"}, status_code=200)

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
