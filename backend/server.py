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
async def recommendation_timeline():
    """Get daily interaction counts for the last 30 days."""
    try:
        import sys
        sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'ml_models'))
        from predict_merchant import get_db_connection
        
        conn = get_db_connection()
        cursor = conn.cursor()
        
        cursor.execute("""
            SELECT 
                DATE(created_at) as day,
                interaction_type,
                COUNT(*) as cnt
            FROM cp_user_offer_interactions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at), interaction_type
            ORDER BY day ASC
        """)
        rows = cursor.fetchall()
        
        # Convert date objects to strings
        timeline = []
        for r in rows:
            timeline.append({
                "day": str(r['day']) if r['day'] else None,
                "interaction_type": r['interaction_type'],
                "cnt": r['cnt']
            })
        
        # Also get category breakdown
        cursor.execute("""
            SELECT 
                mc.category_name,
                COUNT(*) as cnt,
                COUNT(DISTINCT uoi.client_id) as unique_users
            FROM cp_user_offer_interactions uoi
            JOIN cp_merchants_catalog mc ON uoi.partner_id = mc.partner_id
            WHERE uoi.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY mc.category_name
            ORDER BY cnt DESC
            LIMIT 10
        """)
        categories = cursor.fetchall()
        
        conn.close()
        
        return JSONResponse({
            "timeline": timeline,
            "categories": categories,
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
