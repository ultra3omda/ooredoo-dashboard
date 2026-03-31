import httpx
from datetime import datetime
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
    try:
        # Ensure PHP-FPM is running
        result = subprocess.run(["pgrep", "-f", "php-fpm"], capture_output=True)
        if result.returncode != 0:
            print("Starting PHP-FPM...")
            subprocess.run(["mkdir", "-p", "/run/php"], check=False)
            for fpm_bin in ["/usr/sbin/php-fpm8.2", "php-fpm8.2", "php-fpm"]:
                try:
                    subprocess.run([fpm_bin, "--daemonize"], check=False)
                    print(f"Started PHP-FPM via {fpm_bin}")
                    break
                except FileNotFoundError:
                    continue
            else:
                subprocess.run(["service", "php8.2-fpm", "start"], check=False, capture_output=True)
                print("Started PHP-FPM via service command")
        # Ensure Nginx has the Laravel config and is serving port 8002
        result = subprocess.run(["curl", "-s", "-o", "/dev/null", "-w", "%{http_code}", "http://127.0.0.1:8002/"], capture_output=True, text=True)
        if result.stdout.strip() != "200":
            print("Reloading Nginx...")
            subprocess.run(["nginx", "-s", "reload"], check=False, capture_output=True)
        # Fix storage permissions
        subprocess.run(["chmod", "-R", "777", "/app/storage/logs/", "/app/storage/framework/"], check=False, capture_output=True)
    except FileNotFoundError:
        print("Some system commands not found (pgrep/nginx) - skipping startup checks (VPS mode)")
    except Exception as e:
        print(f"Startup checks skipped: {e}")
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
        
        result = get_recommendations(
            client_id=int(client_id),
            top_k=int(top_k),
            category_id=int(category_id) if category_id else None,
            exclude_visited=bool(exclude_visited)
        )
        
        # Handle both old (2-tuple) and new (3-tuple with user_context) return formats
        if len(result) == 3:
            recommendations, source, user_context = result
        else:
            recommendations, source = result
            user_context = None
        
        response = {
            "success": True,
            "client_id": int(client_id),
            "count": len(recommendations),
            "source": source,
            "recommendations": recommendations,
        }
        if user_context:
            response["user_context"] = user_context
        
        return JSONResponse(response)
    except Exception as e:
        import traceback
        traceback.print_exc()
        return JSONResponse({
            "success": False,
            "error": f"Erreur recommandations: {str(e)}"
        }, status_code=500)

@app.get("/api/merchant-recommendations/explain/{client_id}")
async def merchant_recommendations_explain(client_id: int, request: Request):
    """
    HTML visual report explaining all recommendations for a client.
    Inspired by AWS Personalize campaign reports.
    """
    from fastapi.responses import HTMLResponse
    try:
        import sys
        sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'ml_models'))
        from predict_merchant import get_recommendations

        top_k = int(request.query_params.get('top_k', '10'))
        category_id = request.query_params.get('category_id')
        exclude_visited = request.query_params.get('exclude_visited', 'false').lower() == 'true'

        result = get_recommendations(
            client_id=client_id,
            top_k=top_k,
            category_id=int(category_id) if category_id else None,
            exclude_visited=exclude_visited
        )
        recommendations, source, user_context = result if len(result) == 3 else (result[0], result[1], {})

        html = _build_explain_html(client_id, recommendations, source, user_context, top_k)
        return HTMLResponse(content=html)

    except Exception as e:
        import traceback
        traceback.print_exc()
        return HTMLResponse(content=f"<html><body><h1>Erreur</h1><pre>{str(e)}</pre></body></html>", status_code=500)


def _build_explain_html(client_id, recommendations, source, user_context, top_k):
    """Generate a beautiful standalone HTML report."""
    now = datetime.now().strftime('%d/%m/%Y %H:%M')
    uc = user_context or {}

    # Type colors
    type_colors = {
        'DISCOVERY': ('#3b82f6', '#eff6ff', 'A decouvrir'),
        'RE_ENGAGEMENT': ('#f59e0b', '#fffbeb', 'A re-visiter'),
        'LOYALTY': ('#10b981', '#ecfdf5', 'Favori'),
        'TRENDING': ('#8b5cf6', '#f5f3ff', 'Tendance'),
        'COLD_START': ('#6b7280', '#f9fafb', 'Nouveau client'),
    }

    # Build recommendation cards HTML
    cards_html = ''
    for r in recommendations:
        rt = r.get('recommendation_type', 'DISCOVERY')
        tc, tbg, tlabel = type_colors.get(rt, type_colors['DISCOVERY'])
        sn = r.get('score_normalized', 0)
        sc = '#10b981' if sn >= 80 else '#f59e0b' if sn >= 40 else '#6b7280'
        ex = r.get('explanation', {})

        # Factors HTML
        factors_html = ''
        for f in ex.get('factors', []):
            factors_html += f'<div style="padding:4px 0;font-size:13px;color:#374151;">&#8594; {f}</div>'

        # Details HTML
        details_html = ''
        for d in ex.get('details', []):
            details_html += f'<div style="padding:2px 0;font-size:12px;color:#6b7280;font-style:italic;">&#9733; {d}</div>'

        # Because you visited HTML
        because_html = ''
        because = r.get('because_you_visited', [])
        if because:
            links = ''.join([
                f'''<div style="display:inline-flex;align-items:center;gap:6px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:4px 10px;font-size:12px;">
                    <span style="font-weight:600;color:#166534;">{b["partner_name"]}</span>
                    <span style="color:#6b7280;">({b["visit_count"]} visites, {b["link_reason"]})</span>
                </div>''' for b in because
            ])
            because_html = f'''
            <div style="margin-top:10px;padding:10px 14px;background:#f0fdf4;border-radius:8px;border-left:3px solid #10b981;">
                <div style="font-size:12px;font-weight:600;color:#166534;margin-bottom:6px;">Parce que vous avez visite :</div>
                <div style="display:flex;flex-wrap:wrap;gap:6px;">{links}</div>
            </div>'''

        # Collaborative signal
        collab_html = ''
        sim = r.get('similar_users_count', 0)
        if sim > 0:
            collab_html = f'''
            <div style="margin-top:8px;padding:8px 12px;background:#eff6ff;border-radius:6px;font-size:12px;color:#1e40af;">
                <strong>{sim}</strong> clients avec des preferences similaires visitent aussi ce marchand
            </div>'''

        # Visited badge
        visited_badge = ''
        if r.get('already_visited'):
            visited_badge = f'<span style="background:#ecfdf5;color:#059669;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:500;">{r["visit_count"]} visites</span>'
        else:
            visited_badge = '<span style="background:#eff6ff;color:#3b82f6;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:500;">Jamais visite</span>'

        cards_html += f'''
        <div style="border:1px solid #e5e7eb;border-radius:12px;padding:20px;margin-bottom:16px;background:white;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
            <div style="display:flex;align-items:flex-start;gap:16px;">
                <div style="min-width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;color:white;background:{'linear-gradient(135deg,#fbbf24,#f59e0b)' if r['rank'] == 1 else 'linear-gradient(135deg,#d1d5db,#9ca3af)' if r['rank'] == 2 else 'linear-gradient(135deg,#d97706,#b45309)' if r['rank'] == 3 else '#e5e7eb;color:#6b7280'};">
                    {r['rank']}
                </div>
                <div style="flex:1;">
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <span style="font-size:18px;font-weight:700;color:#111827;">{r['partner_name']}</span>
                        <span style="background:{tbg};color:{tc};padding:3px 10px;border-radius:10px;font-size:11px;font-weight:600;text-transform:uppercase;">{tlabel}</span>
                        {visited_badge}
                    </div>
                    <div style="font-size:13px;color:#6b7280;margin-top:4px;">
                        {r['category_name']} · {r.get('active_promotions', 0)} promos actives · Remise moy. {r.get('avg_discount', 0):.0f}% (max {r.get('max_discount', 0):.0f}%) · {r.get('location_count', 0)} point(s) de vente
                    </div>
                </div>
                <div style="text-align:center;min-width:80px;">
                    <div style="font-size:28px;font-weight:800;color:{sc};">{sn:.0f}</div>
                    <div style="font-size:11px;color:#9ca3af;">/100</div>
                    <div style="margin-top:6px;height:6px;background:#f3f4f6;border-radius:3px;overflow:hidden;">
                        <div style="height:100%;width:{sn}%;background:{sc};border-radius:3px;"></div>
                    </div>
                </div>
            </div>

            <div style="margin-top:14px;padding:12px 16px;background:#f9fafb;border-radius:8px;">
                <div style="font-size:13px;font-weight:600;color:#111827;margin-bottom:6px;">{ex.get('summary', '')}</div>
                {factors_html}
                {details_html}
            </div>

            {because_html}
            {collab_html}

            <div style="margin-top:8px;font-size:11px;color:#9ca3af;">
                {ex.get('score_interpretation', '')} · Modele: {ex.get('model_type', 'LightGBM')}
            </div>
        </div>'''

    # Stats summary
    type_counts = {}
    for r in recommendations:
        rt = r.get('recommendation_type', '?')
        type_counts[rt] = type_counts.get(rt, 0) + 1

    type_pills = ''
    for t, count in type_counts.items():
        tc, tbg, tlabel = type_colors.get(t, type_colors['DISCOVERY'])
        type_pills += f'<span style="background:{tbg};color:{tc};padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;">{tlabel}: {count}</span> '

    # Build full HTML
    return f'''<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recommandations Client #{client_id} — Club Privileges</title>
    <style>
        * {{ margin: 0; padding: 0; box-sizing: border-box; }}
        body {{ font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; color: #111827; line-height: 1.5; }}
        .container {{ max-width: 900px; margin: 0 auto; padding: 24px 16px; }}
        @media print {{
            body {{ background: white; }}
            .no-print {{ display: none !important; }}
            .container {{ max-width: 100%; padding: 0; }}
        }}
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div style="background:linear-gradient(135deg,#1e3a5f,#2d5f8a);color:white;border-radius:16px;padding:28px 32px;margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <div style="font-size:24px;font-weight:800;">Rapport de Recommandations</div>
                    <div style="font-size:14px;opacity:0.8;margin-top:4px;">Club Privileges — Moteur ML inspire d'AWS Personalize</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:13px;opacity:0.7;">Genere le {now}</div>
                    <div style="font-size:13px;opacity:0.7;">Source: {source.replace('_', ' ').title()}</div>
                </div>
            </div>
        </div>

        <!-- Profile Card -->
        <div style="background:white;border-radius:12px;padding:20px 24px;margin-bottom:20px;border:1px solid #e5e7eb;">
            <div style="font-size:16px;font-weight:700;color:#111827;margin-bottom:14px;">Profil Client #{client_id}</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(140px, 1fr));gap:12px;">
                <div style="background:#f9fafb;border-radius:8px;padding:12px;text-align:center;">
                    <div style="font-size:22px;font-weight:700;color:#1e3a5f;">{uc.get('total_visits', 0)}</div>
                    <div style="font-size:11px;color:#6b7280;">Visites totales</div>
                </div>
                <div style="background:#f9fafb;border-radius:8px;padding:12px;text-align:center;">
                    <div style="font-size:22px;font-weight:700;color:#1e3a5f;">{uc.get('unique_merchants', 0)}</div>
                    <div style="font-size:11px;color:#6b7280;">Marchands visites</div>
                </div>
                <div style="background:#f9fafb;border-radius:8px;padding:12px;text-align:center;">
                    <div style="font-size:22px;font-weight:700;color:#1e3a5f;">{uc.get('unique_categories', 0)}</div>
                    <div style="font-size:11px;color:#6b7280;">Categories</div>
                </div>
                <div style="background:#f9fafb;border-radius:8px;padding:12px;text-align:center;">
                    <div style="font-size:22px;font-weight:700;color:#1e3a5f;">{uc.get('loyalty_score', 0):.1f}</div>
                    <div style="font-size:11px;color:#6b7280;">Score fidelite /10</div>
                </div>
                <div style="background:#f9fafb;border-radius:8px;padding:12px;text-align:center;">
                    <div style="font-size:22px;font-weight:700;color:#1e3a5f;">{uc.get('subscription_type', 'N/A')}</div>
                    <div style="font-size:11px;color:#6b7280;">Abonnement</div>
                </div>
                <div style="background:#f9fafb;border-radius:8px;padding:12px;text-align:center;">
                    <div style="font-size:22px;font-weight:700;color:#1e3a5f;">{uc.get('days_since_last_activity', 0)}j</div>
                    <div style="font-size:11px;color:#6b7280;">Derniere activite</div>
                </div>
            </div>
        </div>

        <!-- Type Distribution -->
        <div style="background:white;border-radius:12px;padding:16px 24px;margin-bottom:20px;border:1px solid #e5e7eb;">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <span style="font-size:13px;font-weight:600;color:#374151;">Repartition:</span>
                {type_pills}
                <span style="font-size:12px;color:#9ca3af;margin-left:auto;">{len(recommendations)} recommandations sur {top_k} demandees</span>
            </div>
        </div>

        <!-- Legend -->
        <div style="background:white;border-radius:12px;padding:16px 24px;margin-bottom:20px;border:1px solid #e5e7eb;">
            <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:8px;">Legende des types de recommandation</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:8px;font-size:12px;">
                <div><span style="color:#3b82f6;font-weight:600;">DISCOVERY</span> — Marchand jamais visite, fort potentiel</div>
                <div><span style="color:#f59e0b;font-weight:600;">RE-ENGAGEMENT</span> — Deja visite mais pas recemment</div>
                <div><span style="color:#10b981;font-weight:600;">LOYALTY</span> — Marchand favori, visites frequentes</div>
                <div><span style="color:#8b5cf6;font-weight:600;">TRENDING</span> — Populaire avec beaucoup de promos</div>
            </div>
            <div style="font-size:11px;color:#9ca3af;margin-top:8px;">
                Score /100 = pertinence relative. 100 = meilleure correspondance parmi tous les marchands actifs.
                Le modele utilise 28 features (profil client + caracteristiques marchand + historique) avec un poids d'exploration de 15%.
            </div>
        </div>

        <!-- Recommendation Cards -->
        {cards_html}

        <!-- Footer -->
        <div style="text-align:center;padding:20px;font-size:11px;color:#9ca3af;">
            Club Privileges — Moteur de Recommandation ML v2.0 (LightGBM LambdaRank + Exploration/Exploitation)<br>
            Inspire d'AWS Personalize · {len(recommendations)} recommandations · Client #{client_id}
        </div>
    </div>
</body>
</html>'''

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
    """Trigger model retraining (synchronous, waits for completion)."""
    import asyncio as _asyncio
    def _retrain():
        import subprocess
        return subprocess.run(
            ["python3", os.path.join(os.path.dirname(__file__), '..', 'ml_models', 'train_merchant_recommender.py')],
            capture_output=True, text=True, timeout=600
        )
    try:
        result = await _asyncio.to_thread(_retrain)
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

@app.get("/api/merchant-recommendations/stats/timeline")
async def recommendation_timeline(request: Request):
    """Get daily interaction counts for the last N days (30/60/90)."""
    try:
        import sys
        sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'ml_models'))
        from predict_merchant import get_db_connection

        days = int(request.query_params.get('days', '30'))
        if days not in (30, 60, 90):
            days = 30

        conn = get_db_connection()
        cursor = conn.cursor()

        cursor.execute(f"""
            SELECT DATE(created_at) as day, interaction_type, COUNT(*) as cnt
            FROM cp_user_offer_interactions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL {days} DAY)
            GROUP BY DATE(created_at), interaction_type
            ORDER BY day ASC
        """)
        rows = cursor.fetchall()

        timeline = [{"day": str(r['day']) if r['day'] else None, "interaction_type": r['interaction_type'], "cnt": r['cnt']} for r in rows]

        cursor.execute(f"""
            SELECT mc.category_name, COUNT(*) as cnt, COUNT(DISTINCT uoi.client_id) as unique_users
            FROM cp_user_offer_interactions uoi
            JOIN cp_merchants_catalog mc ON uoi.partner_id = mc.partner_id
            WHERE uoi.created_at >= DATE_SUB(NOW(), INTERVAL {days} DAY)
            GROUP BY mc.category_name
            ORDER BY cnt DESC LIMIT 10
        """)
        categories = cursor.fetchall()

        # Source breakdown (ML vs popularity)
        cursor.execute(f"""
            SELECT source, interaction_type, COUNT(*) as cnt,
                   COUNT(DISTINCT client_id) as unique_users,
                   COUNT(DISTINCT partner_id) as unique_merchants
            FROM cp_user_offer_interactions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL {days} DAY)
            GROUP BY source, interaction_type
            ORDER BY cnt DESC
        """)
        source_breakdown = cursor.fetchall()

        conn.close()

        return JSONResponse({
            "timeline": timeline,
            "categories": categories,
            "source_breakdown": source_breakdown,
            "period_days": days,
        })
    except Exception as e:
        return JSONResponse({"error": str(e)}, status_code=500)

@app.get("/api/merchant-recommendations/categories")
async def get_categories():
    """Get available merchant categories."""
    try:
        import sys
        sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'ml_models'))
        from predict_merchant import get_db_connection
        
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("""
            SELECT DISTINCT category_id, category_name 
            FROM cp_merchants_catalog 
            WHERE is_active = 1 AND category_name IS NOT NULL
            ORDER BY category_name
        """)
        cats = cursor.fetchall()
        conn.close()
        
        return JSONResponse({"categories": cats})
    except Exception as e:
        return JSONResponse({"error": str(e)}, status_code=500)

# ═══════════════════════════════════════════════════════════════════════════
# P2: CLIENT-FACING RECOMMENDATION WIDGET
# ═══════════════════════════════════════════════════════════════════════════

@app.get("/api/merchant-recommendations/widget/{client_id}")
async def widget_recommendations(client_id: int, request: Request):
    """Lightweight recommendation widget for mobile/web app integration."""
    try:
        import sys
        sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'ml_models'))
        from predict_merchant import get_recommendations

        top_k = int(request.query_params.get('top_k', '5'))
        category_id = request.query_params.get('category_id')
        exclude_visited = request.query_params.get('exclude_visited', 'false').lower() == 'true'

        result = get_recommendations(
            client_id=client_id, top_k=top_k,
            category_id=int(category_id) if category_id else None,
            exclude_visited=exclude_visited
        )
        recommendations, source, user_context = result if len(result) == 3 else (result[0], result[1], {})

        items = [{
            'id': r['partner_id'],
            'name': r['partner_name'],
            'category': r['category_name'],
            'score': r['score_normalized'],
            'type': r.get('recommendation_type', 'DISCOVERY'),
            'reason': r['reason'],
            'promos': r['active_promotions'],
            'discount': r['avg_discount'],
            'visited': r.get('already_visited', False),
            'visits': r.get('visit_count', 0),
        } for r in recommendations]

        return JSONResponse({
            'client_id': client_id,
            'source': source,
            'items': items,
            'count': len(items),
        })
    except Exception as e:
        return JSONResponse({'error': str(e)}, status_code=500)


@app.get("/api/merchant-recommendations/widget/{client_id}/html")
async def widget_recommendations_html(client_id: int, request: Request):
    """Embeddable HTML widget for mobile/web app."""
    from fastapi.responses import HTMLResponse
    try:
        import sys
        sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'ml_models'))
        from predict_merchant import get_recommendations

        top_k = int(request.query_params.get('top_k', '5'))
        result = get_recommendations(client_id=client_id, top_k=top_k)
        recommendations, source, _ = result if len(result) == 3 else (result[0], result[1], {})

        type_styles = {
            'DISCOVERY': ('Decouvrir', '#3b82f6', '#eff6ff'),
            'RE_ENGAGEMENT': ('Re-visiter', '#f59e0b', '#fffbeb'),
            'LOYALTY': ('Favori', '#10b981', '#ecfdf5'),
            'TRENDING': ('Tendance', '#8b5cf6', '#f5f3ff'),
        }

        cards = ''
        for r in recommendations:
            rt = r.get('recommendation_type', 'DISCOVERY')
            label, color, bg = type_styles.get(rt, type_styles['DISCOVERY'])
            sn = r.get('score_normalized', 0)
            sc = '#10b981' if sn >= 80 else '#f59e0b' if sn >= 40 else '#9ca3af'
            because = r.get('because_you_visited', [])
            because_text = f' · Parce que: {because[0]["partner_name"]}' if because else ''

            cards += f'''<div style="display:flex;gap:12px;padding:12px;border-radius:12px;border:1px solid #e5e7eb;margin-bottom:8px;background:#fff;">
  <div style="width:36px;height:36px;border-radius:50%;background:{bg};color:{color};display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0;">{r["rank"]}</div>
  <div style="flex:1;min-width:0;">
    <div style="font-weight:600;font-size:14px;color:#111;">{r["partner_name"]}</div>
    <div style="font-size:11px;color:#6b7280;margin-top:2px;">
      {r["category_name"]} · <span style="background:{bg};color:{color};padding:1px 6px;border-radius:4px;font-weight:600;">{label}</span>
      {f' · {r["active_promotions"]} promos' if r["active_promotions"] else ''}
    </div>
    <div style="font-size:10px;color:#1e3a5f;margin-top:3px;">{r["reason"]}{because_text}</div>
  </div>
  <div style="text-align:center;min-width:40px;">
    <div style="font-size:18px;font-weight:800;color:{sc};">{sn:.0f}</div>
    <div style="font-size:9px;color:#9ca3af;">/100</div>
  </div>
</div>'''

        html = f'''<!DOCTYPE html>
<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>*{{margin:0;padding:0;box-sizing:border-box}}body{{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f9fafb;padding:12px}}</style>
</head><body>
<div style="max-width:400px;margin:0 auto;">
  <div style="font-size:15px;font-weight:700;color:#1e3a5f;margin-bottom:12px;">Recommande pour vous</div>
  {cards}
  <div style="text-align:center;font-size:10px;color:#9ca3af;margin-top:8px;">Club Privileges ML · {source}</div>
</div>
</body></html>'''
        return HTMLResponse(content=html)
    except Exception as e:
        return HTMLResponse(content=f"<p>Erreur: {e}</p>", status_code=500)


# ═══════════════════════════════════════════════════════════════════════════
# MERCHANT INTELLIGENCE ENGINE
# ═══════════════════════════════════════════════════════════════════════════

@app.get("/api/merchant-intelligence/analyze")
async def merchant_intelligence_analyze(request: Request):
    """Analyze merchant traffic patterns and detect anomalies."""
    try:
        import sys
        sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'ml_models'))
        from merchant_intelligence import analyze_merchant_traffic

        partner_id = request.query_params.get('partner_id')
        days = int(request.query_params.get('days', '30'))

        results = analyze_merchant_traffic(
            partner_id=int(partner_id) if partner_id else None,
            days=days
        )
        return JSONResponse({
            'success': True,
            'count': len(results),
            'period_days': days,
            'merchants': results,
        })
    except Exception as e:
        import traceback; traceback.print_exc()
        return JSONResponse({'success': False, 'error': str(e)}, status_code=500)


@app.get("/api/merchant-intelligence/digest")
async def merchant_intelligence_digest(request: Request):
    """Get merchant intelligence digest: boost/watch/performers."""
    try:
        import sys
        sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'ml_models'))
        from merchant_intelligence import get_top_merchants_to_boost

        limit = int(request.query_params.get('limit', '10'))
        data = get_top_merchants_to_boost(limit=limit)
        return JSONResponse({'success': True, **data})
    except Exception as e:
        import traceback; traceback.print_exc()
        return JSONResponse({'success': False, 'error': str(e)}, status_code=500)


@app.post("/api/merchant-intelligence/report")
async def merchant_intelligence_report(request: Request):
    """Generate AI-powered merchant intelligence report."""
    try:
        import sys
        sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'ml_models'))
        from merchant_intelligence import get_top_merchants_to_boost, generate_merchant_intelligence_report

        api_key = os.environ.get("EMERGENT_LLM_KEY", "")
        if not api_key:
            return JSONResponse({'success': False, 'error': 'Cle API non configuree'}, status_code=500)

        body = {}
        try:
            body = await request.json()
        except Exception:
            pass

        model_provider = body.get('provider', 'gemini')
        model_name = body.get('model', 'gemini-2.5-flash')

        merchants_data = get_top_merchants_to_boost(limit=10)
        report = await generate_merchant_intelligence_report(
            merchants_data, api_key,
            model_provider=model_provider,
            model_name=model_name
        )

        return JSONResponse({
            'success': True,
            'report': report,
            'data': merchants_data.get('stats', {}),
        })
    except Exception as e:
        import traceback; traceback.print_exc()
        return JSONResponse({'success': False, 'error': str(e)}, status_code=500)


@app.get("/api/merchant-intelligence/report/html")
async def merchant_intelligence_report_html(request: Request):
    """Generate HTML intelligence report."""
    from fastapi.responses import HTMLResponse
    try:
        import sys
        sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'ml_models'))
        from merchant_intelligence import get_top_merchants_to_boost, generate_merchant_intelligence_report

        api_key = os.environ.get("EMERGENT_LLM_KEY", "")
        merchants_data = get_top_merchants_to_boost(limit=10)

        ai_report = None
        if api_key:
            try:
                ai_report = await generate_merchant_intelligence_report(
                    merchants_data, api_key,
                    model_provider='gemini', model_name='gemini-2.5-flash'
                )
            except Exception as e:
                ai_report = {'executive_summary': f'Analyse IA indisponible: {str(e)}', 'boost_recommendations': []}

        html = _build_intelligence_html(merchants_data, ai_report)
        return HTMLResponse(content=html)
    except Exception as e:
        import traceback; traceback.print_exc()
        return HTMLResponse(content=f"<html><body><h1>Erreur</h1><pre>{str(e)}</pre></body></html>", status_code=500)


def _build_intelligence_html(data, ai_report):
    """Build HTML intelligence report."""
    now = datetime.now().strftime('%d/%m/%Y %H:%M')
    stats = data.get('stats', {})
    total = stats.get('performant', 0) + stats.get('a_surveiller', 0) + stats.get('a_booster', 0)

    summary = ''
    if ai_report and ai_report.get('executive_summary'):
        summary = f'''<div style="background:#fffbeb;border:1px solid #fbbf24;border-radius:12px;padding:20px;margin-bottom:20px;">
            <div style="font-weight:700;color:#92400e;margin-bottom:8px;">Resume executif (Gemini AI)</div>
            <div style="font-size:14px;color:#78350f;line-height:1.6;">{ai_report["executive_summary"]}</div>
        </div>'''

    boost_html = ''
    recs = ai_report.get('boost_recommendations', []) if ai_report else []
    for rec in recs:
        actions = ''.join([f'<li style="margin:4px 0;">{a}</li>' for a in rec.get('actions', [])])
        priority_color = {'P0': '#ef4444', 'P1': '#f59e0b', 'P2': '#3b82f6'}.get(rec.get('priority', 'P1'), '#6b7280')
        boost_html += f'''<div style="border:1px solid #e5e7eb;border-radius:10px;padding:16px;margin-bottom:12px;background:white;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                <span style="font-weight:700;font-size:15px;">{rec.get("partner_name", "")}</span>
                <span style="background:{priority_color};color:white;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;">{rec.get("priority", "P1")}</span>
            </div>
            <div style="font-size:13px;color:#374151;margin-bottom:8px;"><strong>Diagnostic:</strong> {rec.get("diagnostic", "")}</div>
            <div style="margin-bottom:6px;"><strong style="font-size:12px;">Actions commerciales:</strong><ul style="font-size:12px;padding-left:20px;color:#374151;">{actions}</ul></div>
            <div style="font-size:12px;color:#1e40af;margin-bottom:4px;"><strong>Strategie promo:</strong> {rec.get("promo_strategy", "")}</div>
            <div style="font-size:12px;color:#059669;"><strong>Strategie digitale:</strong> {rec.get("digital_strategy", "")}</div>
        </div>'''

    status_colors = {'PERFORMANT': '#10b981', 'A_SURVEILLER': '#f59e0b', 'A_BOOSTER': '#ef4444'}

    def merchant_row(m):
        sc = status_colors.get(m['status'], '#6b7280')
        trend = m['trend_7d_pct']
        trend_icon = '&#9650;' if trend > 0 else '&#9660;' if trend < 0 else '&#9644;'
        trend_color = '#10b981' if trend > 5 else '#ef4444' if trend < -5 else '#6b7280'
        return f'''<tr>
            <td style="padding:8px;font-weight:600;">{m["partner_name"]}</td>
            <td style="padding:8px;font-size:12px;">{m["category"]}</td>
            <td style="padding:8px;text-align:center;"><span style="background:{sc}20;color:{sc};padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;">{m["status"].replace("_"," ")}</span></td>
            <td style="padding:8px;text-align:center;font-weight:700;">{m["health_score"]}</td>
            <td style="padding:8px;text-align:center;">{m["avg_daily_tx"]}</td>
            <td style="padding:8px;text-align:center;color:{trend_color};font-weight:600;">{trend_icon} {trend:+.1f}%</td>
            <td style="padding:8px;text-align:center;">{m["active_promos"]}</td>
            <td style="padding:8px;text-align:center;font-size:12px;">{m["best_day"][:3]}</td>
        </tr>'''

    boost_rows = ''.join([merchant_row(m) for m in data.get('to_boost', [])[:10]])
    watch_rows = ''.join([merchant_row(m) for m in data.get('to_watch', [])[:10]])
    perf_rows = ''.join([merchant_row(m) for m in data.get('top_performers', [])[:5]])

    table_header = '''<tr style="background:#f9fafb;">
        <th style="padding:8px;text-align:left;">Marchand</th><th style="padding:8px;text-align:left;">Categorie</th>
        <th style="padding:8px;text-align:center;">Statut</th><th style="padding:8px;text-align:center;">Score</th>
        <th style="padding:8px;text-align:center;">Tx/jour</th><th style="padding:8px;text-align:center;">Tendance 7j</th>
        <th style="padding:8px;text-align:center;">Promos</th><th style="padding:8px;text-align:center;">Meilleur jour</th>
    </tr>'''

    patterns_html = ''
    patterns = ai_report.get('success_patterns', []) if ai_report else []
    if patterns:
        patterns_items = ''.join([f'<li style="margin:4px 0;font-size:13px;">{p}</li>' for p in patterns])
        patterns_html = f'''<div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:16px;margin-bottom:20px;">
            <div style="font-weight:700;color:#065f46;margin-bottom:8px;">Patterns de succes a repliquer</div>
            <ul style="padding-left:20px;color:#047857;">{patterns_items}</ul>
        </div>'''

    return f'''<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Intelligence Marchands — Club Privileges</title>
<style>*{{margin:0;padding:0;box-sizing:border-box}}body{{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f3f4f6;color:#111;line-height:1.5}}.container{{max-width:1000px;margin:0 auto;padding:24px 16px}}table{{width:100%;border-collapse:collapse}}th,td{{border-bottom:1px solid #e5e7eb}}@media print{{body{{background:#fff}}.no-print{{display:none!important}}}}</style>
</head><body><div class="container">
<div style="background:linear-gradient(135deg,#7c2d12,#c2410c);color:white;border-radius:16px;padding:28px 32px;margin-bottom:20px;">
    <div style="font-size:24px;font-weight:800;">Intelligence Marchands</div>
    <div style="font-size:14px;opacity:0.8;margin-top:4px;">Club Privileges — Analyse de performance + Recommandations commerciales IA</div>
    <div style="font-size:12px;opacity:0.6;margin-top:6px;">Genere le {now}</div>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px;">
    <div style="background:white;border-radius:10px;padding:16px;text-align:center;border:1px solid #e5e7eb;"><div style="font-size:28px;font-weight:800;color:#1e3a5f;">{total}</div><div style="font-size:11px;color:#6b7280;">Marchands analyses</div></div>
    <div style="background:white;border-radius:10px;padding:16px;text-align:center;border:1px solid #e5e7eb;"><div style="font-size:28px;font-weight:800;color:#10b981;">{stats.get("performant", 0)}</div><div style="font-size:11px;color:#6b7280;">Performants</div></div>
    <div style="background:white;border-radius:10px;padding:16px;text-align:center;border:1px solid #e5e7eb;"><div style="font-size:28px;font-weight:800;color:#f59e0b;">{stats.get("a_surveiller", 0)}</div><div style="font-size:11px;color:#6b7280;">A surveiller</div></div>
    <div style="background:white;border-radius:10px;padding:16px;text-align:center;border:1px solid #e5e7eb;"><div style="font-size:28px;font-weight:800;color:#ef4444;">{stats.get("a_booster", 0)}</div><div style="font-size:11px;color:#6b7280;">A booster</div></div>
</div>
{summary}
{f'<div style="margin-bottom:20px;"><div style="font-size:18px;font-weight:700;margin-bottom:12px;">Recommandations commerciales (IA)</div>{boost_html}</div>' if boost_html else ''}
{patterns_html}
{f'<div style="background:white;border-radius:12px;padding:20px;margin-bottom:20px;border:1px solid #e5e7eb;overflow-x:auto;"><div style="font-size:16px;font-weight:700;color:#ef4444;margin-bottom:12px;">Marchands a booster</div><table>{table_header}{boost_rows}</table></div>' if boost_rows else ''}
{f'<div style="background:white;border-radius:12px;padding:20px;margin-bottom:20px;border:1px solid #e5e7eb;overflow-x:auto;"><div style="font-size:16px;font-weight:700;color:#f59e0b;margin-bottom:12px;">Marchands a surveiller</div><table>{table_header}{watch_rows}</table></div>' if watch_rows else ''}
{f'<div style="background:white;border-radius:12px;padding:20px;margin-bottom:20px;border:1px solid #e5e7eb;overflow-x:auto;"><div style="font-size:16px;font-weight:700;color:#10b981;margin-bottom:12px;">Top performeurs</div><table>{table_header}{perf_rows}</table></div>' if perf_rows else ''}
<div style="text-align:center;padding:16px;font-size:11px;color:#9ca3af;">Club Privileges — Intelligence Marchands v1.0 · {total} marchands · Gemini AI</div>
</div></body></html>'''


# ═══════════════════════════════════════════════════════════════════════════
# A/B TEST FRAMEWORK: ML Model vs Popularity
# ═══════════════════════════════════════════════════════════════════════════

import hashlib
import random as _random

def _get_ab_group(client_id: int) -> str:
    """Deterministic A/B assignment based on client_id hash."""
    h = hashlib.md5(f"ab_test_v1_{client_id}".encode()).hexdigest()
    return 'ml_model' if int(h[:8], 16) % 2 == 0 else 'popularity'


@app.get("/api/merchant-recommendations/ab-test/results")
async def ab_test_results(request: Request):
    """Get A/B test results comparing ML model vs Popularity."""
    try:
        import sys
        sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'ml_models'))
        from predict_merchant import get_db_connection

        days = int(request.query_params.get('days', '30'))
        conn = get_db_connection()
        cursor = conn.cursor()

        cursor.execute(f"""
            SELECT source, interaction_type,
                   COUNT(*) as cnt,
                   COUNT(DISTINCT client_id) as unique_users,
                   COUNT(DISTINCT partner_id) as unique_merchants
            FROM cp_user_offer_interactions
            WHERE source IN ('ab_ml_model', 'ab_popularity')
              AND created_at >= DATE_SUB(NOW(), INTERVAL {days} DAY)
            GROUP BY source, interaction_type
            ORDER BY source, cnt DESC
        """)
        breakdown = cursor.fetchall()

        cursor.execute(f"""
            SELECT source,
                   SUM(CASE WHEN interaction_type = 'impression' THEN 1 ELSE 0 END) as impressions,
                   SUM(CASE WHEN interaction_type = 'click' THEN 1 ELSE 0 END) as clicks,
                   SUM(CASE WHEN interaction_type = 'redeem' THEN 1 ELSE 0 END) as redeems,
                   COUNT(DISTINCT client_id) as unique_users
            FROM cp_user_offer_interactions
            WHERE source IN ('ab_ml_model', 'ab_popularity')
              AND created_at >= DATE_SUB(NOW(), INTERVAL {days} DAY)
            GROUP BY source
        """)
        summary_rows = cursor.fetchall()
        conn.close()

        groups = {}
        for row in summary_rows:
            src = row['source'].replace('ab_', '')
            impressions = int(row['impressions'] or 0)
            clicks = int(row['clicks'] or 0)
            redeems = int(row['redeems'] or 0)
            groups[src] = {
                'impressions': impressions,
                'clicks': clicks,
                'redeems': redeems,
                'unique_users': int(row['unique_users'] or 0),
                'ctr': round(clicks / max(impressions, 1) * 100, 2),
                'conversion_rate': round(redeems / max(impressions, 1) * 100, 2),
            }

        ml = groups.get('ml_model', {})
        pop = groups.get('popularity', {})
        uplift_ctr = round(ml.get('ctr', 0) - pop.get('ctr', 0), 2) if ml and pop else None
        uplift_conv = round(ml.get('conversion_rate', 0) - pop.get('conversion_rate', 0), 2) if ml and pop else None

        return JSONResponse({
            'period_days': days,
            'groups': groups,
            'uplift': {
                'ctr_pct': uplift_ctr,
                'conversion_pct': uplift_conv,
                'winner': 'ml_model' if (uplift_conv or 0) > 0 else 'popularity' if (uplift_conv or 0) < 0 else 'tie',
            },
            'breakdown': breakdown,
        })
    except Exception as e:
        return JSONResponse({'error': str(e)}, status_code=500)


@app.get("/api/merchant-recommendations/ab-test/{client_id}")
async def ab_test_recommend(client_id: int, request: Request):
    """Serve recommendations via A/B test: ML model vs Popularity fallback."""
    try:
        import sys
        sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'ml_models'))
        from predict_merchant import get_recommendations, get_db_connection

        top_k = int(request.query_params.get('top_k', '5'))
        group = _get_ab_group(client_id)

        if group == 'ml_model':
            result = get_recommendations(client_id=client_id, top_k=top_k)
            recommendations, source, user_context = result if len(result) == 3 else (result[0], result[1], {})
        else:
            # Popularity-based: use fallback (client_id=0 triggers cold start / popularity)
            result = get_recommendations(client_id=0, top_k=top_k)
            recommendations, source, user_context = result if len(result) == 3 else (result[0], result[1], {})
            source = 'popularity_ab'

        items = [{
            'id': r['partner_id'], 'name': r['partner_name'],
            'category': r['category_name'], 'score': r['score_normalized'],
            'type': r.get('recommendation_type', 'DISCOVERY'), 'reason': r['reason'],
            'promos': r['active_promotions'], 'discount': r['avg_discount'],
        } for r in recommendations]

        # Track the A/B assignment
        try:
            conn = get_db_connection()
            cursor = conn.cursor()
            cursor.execute("""
                INSERT INTO cp_user_offer_interactions
                    (client_id, partner_id, interaction_type, source, recommendation_score, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, NOW(), NOW())
            """, (client_id, items[0]['id'] if items else 0, 'impression', f'ab_{group}', 0))
            conn.commit()
            conn.close()
        except Exception:
            pass

        return JSONResponse({
            'client_id': client_id,
            'ab_group': group,
            'source': source,
            'items': items,
            'count': len(items),
        })
    except Exception as e:
        import traceback; traceback.print_exc()
        return JSONResponse({'error': str(e)}, status_code=500)


# ═══════════════════════════════════════════════════════════════════════════
# WEEKLY EMAIL PREVIEW
# ═══════════════════════════════════════════════════════════════════════════

@app.get("/api/merchant-intelligence/weekly-email-preview")
async def weekly_email_preview(request: Request):
    """Generate a preview of the weekly commercial intelligence email."""
    from fastapi.responses import HTMLResponse
    try:
        import sys
        sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'ml_models'))
        from merchant_intelligence import get_top_merchants_to_boost, generate_merchant_intelligence_report

        api_key = os.environ.get("EMERGENT_LLM_KEY", "")
        merchants_data = get_top_merchants_to_boost(limit=10)

        ai_report = None
        if api_key:
            try:
                ai_report = await generate_merchant_intelligence_report(
                    merchants_data, api_key, model_provider='gemini', model_name='gemini-2.5-flash'
                )
            except Exception as e:
                ai_report = {'executive_summary': f'Analyse IA indisponible: {str(e)}', 'boost_recommendations': []}

        html = _build_weekly_email_html(merchants_data, ai_report)
        return HTMLResponse(content=html)
    except Exception as e:
        import traceback; traceback.print_exc()
        return HTMLResponse(content=f"<html><body><h1>Erreur</h1><pre>{str(e)}</pre></body></html>", status_code=500)


def _build_weekly_email_html(data, ai_report):
    """Build the weekly email preview HTML for commercial teams."""
    now = datetime.now().strftime('%d/%m/%Y %H:%M')
    stats = data.get('stats', {})
    total = stats.get('performant', 0) + stats.get('a_surveiller', 0) + stats.get('a_booster', 0)

    summary_text = ''
    if ai_report and ai_report.get('executive_summary'):
        summary_text = ai_report['executive_summary']

    # Build boost actions
    actions_html = ''
    recs = ai_report.get('boost_recommendations', []) if ai_report else []
    for rec in recs:
        priority = rec.get('priority', 'P1')
        pc = {'P0': '#dc2626', 'P1': '#ea580c', 'P2': '#2563eb'}.get(priority, '#6b7280')
        actions_list = ''.join([f'<li style="margin:3px 0;font-size:13px;color:#374151;">{a}</li>' for a in rec.get('actions', [])])
        actions_html += f'''
        <tr>
            <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                    <strong style="font-size:14px;color:#111;">{rec.get('partner_name','')}</strong>
                    <span style="background:{pc};color:white;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;">{priority}</span>
                </div>
                <div style="font-size:12px;color:#6b7280;margin-bottom:6px;">{rec.get('diagnostic','')}</div>
                <ul style="padding-left:18px;margin:0;">{actions_list}</ul>
                <div style="margin-top:6px;font-size:12px;">
                    <span style="color:#1e40af;">Promo: {rec.get('promo_strategy','')}</span><br>
                    <span style="color:#059669;">Digital: {rec.get('digital_strategy','')}</span>
                </div>
            </td>
        </tr>'''

    # Watch alerts
    watch_html = ''
    alerts = ai_report.get('watch_alerts', []) if ai_report else []
    for a in alerts:
        watch_html += f'''<tr><td style="padding:10px 16px;border-bottom:1px solid #f3f4f6;font-size:13px;">
            <strong>{a.get('partner_name','')}</strong>: {a.get('alert','')} &rarr; <em style="color:#1e40af;">{a.get('action','')}</em>
        </td></tr>'''

    # Success patterns
    patterns_html = ''
    patterns = ai_report.get('success_patterns', []) if ai_report else []
    for p in patterns:
        patterns_html += f'<li style="margin:3px 0;font-size:13px;color:#047857;">{p}</li>'

    # Top performers table
    perf_rows = ''
    for m in data.get('top_performers', [])[:5]:
        perf_rows += f'''<tr>
            <td style="padding:8px 12px;border-bottom:1px solid #f3f4f6;font-weight:600;font-size:13px;">{m['partner_name']}</td>
            <td style="padding:8px 12px;border-bottom:1px solid #f3f4f6;font-size:13px;text-align:center;">{m['total_transactions']}</td>
            <td style="padding:8px 12px;border-bottom:1px solid #f3f4f6;text-align:center;">
                <span style="background:#ecfdf5;color:#059669;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;">{m['health_score']}/100</span>
            </td>
        </tr>'''

    return f'''<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Club Privileges — Rapport Commercial Hebdomadaire</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#111;line-height:1.5;">
<div style="max-width:680px;margin:0 auto;padding:24px 12px;">

<!-- Header -->
<table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,#1e3a5f,#2d5f8a);border-radius:12px;margin-bottom:20px;">
<tr><td style="padding:28px 24px;color:white;">
    <div style="font-size:22px;font-weight:800;">Rapport Commercial Hebdomadaire</div>
    <div style="font-size:13px;opacity:0.8;margin-top:4px;">Club Privileges — Intelligence Marchands</div>
    <div style="font-size:12px;opacity:0.6;margin-top:4px;">Genere le {now} · {total} marchands analyses</div>
</td></tr></table>

<!-- KPI Cards -->
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
<tr>
    <td style="width:33%;padding:0 4px 0 0;"><div style="background:white;border-radius:10px;padding:16px;text-align:center;border:1px solid #e5e7eb;">
        <div style="font-size:28px;font-weight:800;color:#10b981;">{stats.get('performant',0)}</div>
        <div style="font-size:11px;color:#6b7280;">Performants</div>
    </div></td>
    <td style="width:33%;padding:0 2px;"><div style="background:white;border-radius:10px;padding:16px;text-align:center;border:1px solid #e5e7eb;">
        <div style="font-size:28px;font-weight:800;color:#f59e0b;">{stats.get('a_surveiller',0)}</div>
        <div style="font-size:11px;color:#6b7280;">A surveiller</div>
    </div></td>
    <td style="width:33%;padding:0 0 0 4px;"><div style="background:white;border-radius:10px;padding:16px;text-align:center;border:1px solid #e5e7eb;">
        <div style="font-size:28px;font-weight:800;color:#ef4444;">{stats.get('a_booster',0)}</div>
        <div style="font-size:11px;color:#6b7280;">A booster</div>
    </div></td>
</tr></table>

<!-- Executive Summary -->
{f"""<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
<tr><td style="background:#fffbeb;border:1px solid #fbbf24;border-radius:10px;padding:18px 20px;">
    <div style="font-weight:700;color:#92400e;font-size:14px;margin-bottom:6px;">Resume executif (Gemini AI)</div>
    <div style="font-size:13px;color:#78350f;line-height:1.6;">{summary_text}</div>
</td></tr></table>""" if summary_text else ''}

<!-- Actions Commerciales -->
{f"""<table width="100%" cellpadding="0" cellspacing="0" style="background:white;border-radius:10px;border:1px solid #e5e7eb;margin-bottom:20px;">
<tr><td style="padding:16px 20px;border-bottom:1px solid #e5e7eb;font-size:16px;font-weight:700;color:#111;">
    Actions commerciales prioritaires
</td></tr>
{actions_html}
</table>""" if actions_html else ''}

<!-- Watch Alerts -->
{f"""<table width="100%" cellpadding="0" cellspacing="0" style="background:white;border-radius:10px;border:1px solid #e5e7eb;margin-bottom:20px;">
<tr><td style="padding:16px 20px;border-bottom:1px solid #e5e7eb;font-size:14px;font-weight:700;color:#f59e0b;">
    Alertes surveillance
</td></tr>
{watch_html}
</table>""" if watch_html else ''}

<!-- Success Patterns -->
{f"""<table width="100%" cellpadding="0" cellspacing="0" style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;margin-bottom:20px;">
<tr><td style="padding:16px 20px;">
    <div style="font-weight:700;color:#065f46;font-size:14px;margin-bottom:6px;">Patterns de succes a repliquer</div>
    <ul style="padding-left:18px;margin:0;">{patterns_html}</ul>
</td></tr></table>""" if patterns_html else ''}

<!-- Top Performers -->
{f"""<table width="100%" cellpadding="0" cellspacing="0" style="background:white;border-radius:10px;border:1px solid #e5e7eb;margin-bottom:20px;">
<tr><td style="padding:16px 20px;border-bottom:1px solid #e5e7eb;font-size:14px;font-weight:700;color:#10b981;">
    Top performeurs
</td></tr>
<tr><td>
<table width="100%" cellpadding="0" cellspacing="0">
<tr style="background:#f9fafb;"><th style="padding:8px 12px;text-align:left;font-size:11px;color:#6b7280;">Marchand</th><th style="padding:8px 12px;text-align:center;font-size:11px;color:#6b7280;">Transactions</th><th style="padding:8px 12px;text-align:center;font-size:11px;color:#6b7280;">Score</th></tr>
{perf_rows}
</table></td></tr></table>""" if perf_rows else ''}

<!-- Footer -->
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td style="text-align:center;padding:20px;font-size:11px;color:#9ca3af;">
    Club Privileges — Intelligence Marchands v1.0<br>
    Ce rapport est genere automatiquement par Gemini AI. Consultez le dashboard pour plus de details.
</td></tr></table>

</div>
</body></html>'''


# ═══════════════════════════════════════════════════════════════════════════
# PROXY CATCH-ALL (must be LAST)
# ═══════════════════════════════════════════════════════════════════════════

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
