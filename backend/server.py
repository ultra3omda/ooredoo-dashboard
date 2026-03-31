import httpx
import os
import json
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

@app.post("/api/merchant-recommendations")
async def merchant_recommendations(request: Request):
    """ML-powered merchant recommendations for a given client."""
    try:
        import sys
        sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'ml_models'))
        from predict_merchant import get_recommendations
        
        body = await request.json()
        client_id = body.get("client_id")
        if client_id is None:
            return JSONResponse({"success": False, "error": "client_id requis"}, status_code=400)
        
        top_k = body.get("top_k", 10)
        category_id = body.get("category_id")
        exclude_visited = body.get("exclude_visited", False)
        
        recommendations = get_recommendations(
            client_id=int(client_id),
            top_k=int(top_k),
            category_id=int(category_id) if category_id else None,
            exclude_visited=bool(exclude_visited)
        )
        
        return JSONResponse({
            "success": True,
            "client_id": int(client_id),
            "count": len(recommendations),
            "recommendations": recommendations,
        })
    except Exception as e:
        import traceback
        traceback.print_exc()
        return JSONResponse({
            "success": False,
            "error": f"Erreur recommandations: {str(e)}"
        }, status_code=500)

@app.get("/api/merchant-recommendations/health")
async def merchant_recommendations_health():
    """Health check for merchant recommendation engine."""
    model_exists = os.path.exists(os.path.join(os.path.dirname(__file__), '..', 'ml_models', 'merchant_recommender.joblib'))
    fallback_exists = os.path.exists(os.path.join(os.path.dirname(__file__), '..', 'ml_models', 'merchant_fallback_popular.json'))
    
    metrics_path = os.path.join(os.path.dirname(__file__), '..', 'ml_models', 'merchant_recommender_metrics.json')
    metrics = {}
    if os.path.exists(metrics_path):
        with open(metrics_path) as f:
            metrics = json.load(f)
    
    return JSONResponse({
        "status": "ready" if model_exists else "fallback_only",
        "model_loaded": model_exists,
        "fallback_available": fallback_exists,
        "trained_at": metrics.get("trained_at"),
        "n_train_samples": metrics.get("n_train_samples"),
        "eval_results": metrics.get("eval_results", {}),
    })

@app.post("/api/merchant-recommendations/retrain")
async def merchant_recommendations_retrain(request: Request):
    """Trigger model retraining."""
    try:
        import subprocess
        result = subprocess.run(
            ["python3", os.path.join(os.path.dirname(__file__), '..', 'ml_models', 'train_merchant_recommender.py')],
            capture_output=True, text=True, timeout=300
        )
        return JSONResponse({
            "success": result.returncode == 0,
            "output": result.stdout[-2000:] if result.stdout else "",
            "errors": result.stderr[-500:] if result.stderr else "",
        })
    except Exception as e:
        return JSONResponse({"success": False, "error": str(e)}, status_code=500)

@app.post("/api/merchant-recommendations/track")
async def track_interaction(request: Request):
    """Track user interaction with a merchant recommendation for feedback loop."""
    try:
        body = await request.json()
        client_id = body.get("client_id")
        partner_id = body.get("partner_id")
        interaction_type = body.get("interaction_type", "click")
        source = body.get("source", "recommendation")
        promotion_id = body.get("promotion_id")
        recommendation_id = body.get("recommendation_id")
        recommendation_score = body.get("recommendation_score")
        recommendation_rank = body.get("recommendation_rank")
        
        if not client_id or not partner_id:
            return JSONResponse({"success": False, "error": "client_id et partner_id requis"}, status_code=400)
        
        valid_types = ['impression', 'click', 'redeem', 'dismiss', 'share']
        valid_sources = ['recommendation', 'organic', 'search', 'category_browse']
        
        if interaction_type not in valid_types:
            return JSONResponse({"success": False, "error": f"interaction_type invalide. Valeurs: {valid_types}"}, status_code=400)
        if source not in valid_sources:
            return JSONResponse({"success": False, "error": f"source invalide. Valeurs: {valid_sources}"}, status_code=400)
        
        import pymysql
        sys_path = os.path.join(os.path.dirname(__file__), '..', 'ml_models')
        import sys
        sys.path.insert(0, sys_path)
        from predict_merchant import get_db_connection
        
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("""
            INSERT INTO cp_user_offer_interactions 
                (client_id, partner_id, promotion_id, interaction_type, source,
                 recommendation_id, recommendation_score, recommendation_rank, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
        """, (client_id, partner_id, promotion_id, interaction_type, source,
              recommendation_id, recommendation_score, recommendation_rank))
        conn.commit()
        conn.close()
        
        return JSONResponse({"success": True, "tracked": True})
    except Exception as e:
        return JSONResponse({"success": False, "error": str(e)}, status_code=500)

@app.get("/api/merchant-recommendations/stats")
async def recommendation_stats():
    """Get recommendation usage statistics for monitoring."""
    try:
        import sys
        sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'ml_models'))
        from predict_merchant import get_db_connection
        
        conn = get_db_connection()
        cursor = conn.cursor()
        
        cursor.execute("""
            SELECT 
                interaction_type,
                source,
                COUNT(*) as cnt,
                COUNT(DISTINCT client_id) as unique_users,
                COUNT(DISTINCT partner_id) as unique_merchants
            FROM cp_user_offer_interactions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY interaction_type, source
            ORDER BY cnt DESC
        """)
        interactions = cursor.fetchall()
        
        cursor.execute("SELECT COUNT(*) as total FROM cp_user_offer_interactions")
        total = cursor.fetchone()['total']
        
        cursor.execute("SELECT COUNT(*) as cnt FROM cp_merchants_catalog WHERE is_active = 1")
        active_merchants = cursor.fetchone()['cnt']
        
        cursor.execute("SELECT COUNT(*) as cnt FROM cp_user_profile")
        profiled_users = cursor.fetchone()['cnt']
        
        conn.close()
        
        return JSONResponse({
            "total_interactions": total,
            "last_7_days": interactions,
            "active_merchants": active_merchants,
            "profiled_users": profiled_users,
        })
    except Exception as e:
        return JSONResponse({"error": str(e)}, status_code=500)

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
