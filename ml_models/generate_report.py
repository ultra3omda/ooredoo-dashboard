#!/usr/bin/env python3
"""
Generate AI-powered weekly report from ML dashboard metrics.
Uses Emergent LLM Key (GPT-4o) to analyze data and produce actionable insights.
"""
import os
import json
import sys
import asyncio
from datetime import datetime, timedelta

import pymysql

def get_db_connection():
    """Read DB credentials from .env file (same logic as predict_merchant.py)."""
    env_path = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), '.env')
    config = {}
    if os.path.exists(env_path):
        with open(env_path) as f:
            for line in f:
                line = line.strip()
                if '=' in line and not line.startswith('#'):
                    key, val = line.split('=', 1)
                    config[key] = val
    return pymysql.connect(
        host=config.get('DB_HOST', '127.0.0.1'),
        port=int(config.get('DB_PORT', 3306)),
        user=config.get('DB_USERNAME', 'root'),
        password=config.get('DB_PASSWORD', ''),
        database=config.get('DB_DATABASE', 'clubprivileges'),
        charset='utf8mb4',
        cursorclass=pymysql.cursors.DictCursor
    )

def fetch_metrics():
    conn = get_db_connection()
    cursor = conn.cursor(pymysql.cursors.DictCursor)
    
    today = datetime.now().strftime('%Y-%m-%d')
    week_ago = (datetime.now() - timedelta(days=7)).strftime('%Y-%m-%d')
    month_ago = (datetime.now() - timedelta(days=30)).strftime('%Y-%m-%d')
    
    metrics = {}
    
    # Segment distribution (latest date)
    cursor.execute("""
        SELECT client_segment, COUNT(*) as cnt,
               AVG(payment_success_rate) as avg_success_rate,
               AVG(churn_probability) as avg_churn
        FROM ml_client_features
        WHERE calculation_date = (SELECT MAX(calculation_date) FROM ml_client_features)
        GROUP BY client_segment ORDER BY cnt DESC
    """)
    metrics['segments'] = [dict(r) for r in cursor.fetchall()]
    for s in metrics['segments']:
        for k, v in s.items():
            if hasattr(v, '__float__'):
                s[k] = float(v)
    
    # Overall stats
    cursor.execute("""
        SELECT COUNT(DISTINCT client_id) as total_clients,
               AVG(payment_success_rate) as avg_success,
               AVG(churn_probability) as avg_churn,
               SUM(total_payments) as total_payments,
               MAX(calculation_date) as latest_date
        FROM ml_client_features
        WHERE calculation_date = (SELECT MAX(calculation_date) FROM ml_client_features)
    """)
    row = cursor.fetchone()
    metrics['overall'] = {k: (float(v) if hasattr(v, '__float__') else str(v) if v else None) for k, v in row.items()}
    
    # Model metrics
    model_path = os.path.join(os.path.dirname(__file__), 'model_metrics.json')
    if os.path.exists(model_path):
        with open(model_path) as f:
            metrics['model'] = json.load(f)
    
    # Active A/B tests
    cursor.execute("""
        SELECT test_name, status, created_at, 
               total_participants, end_date
        FROM ml_ab_tests 
        ORDER BY created_at DESC LIMIT 5
    """)
    metrics['ab_tests'] = []
    for r in cursor.fetchall():
        metrics['ab_tests'].append({k: (str(v) if v else None) for k, v in r.items()})
    
    conn.close()
    return metrics

def fetch_merchant_recommendation_metrics():
    """Fetch merchant recommendation engine metrics."""
    config = get_db_config()
    conn = pymysql.connect(**config)
    cursor = conn.cursor(pymysql.cursors.DictCursor)
    
    reco_metrics = {}
    
    try:
        # Catalog stats
        cursor.execute("SELECT COUNT(*) as cnt FROM cp_merchants_catalog WHERE is_active = 1")
        reco_metrics['active_merchants'] = cursor.fetchone()['cnt']
        
        cursor.execute("SELECT COUNT(*) as cnt FROM cp_user_profile")
        reco_metrics['profiled_users'] = cursor.fetchone()['cnt']
        
        cursor.execute("SELECT COUNT(*) as cnt FROM cp_user_merchant_history")
        reco_metrics['user_merchant_pairs'] = cursor.fetchone()['cnt']
        
        # Top categories by popularity
        cursor.execute("""
            SELECT category_name, COUNT(*) as merchant_count, 
                   SUM(total_visits) as total_visits, AVG(popularity_score) as avg_popularity
            FROM cp_merchants_catalog WHERE is_active = 1 AND category_name IS NOT NULL
            GROUP BY category_name ORDER BY total_visits DESC LIMIT 5
        """)
        reco_metrics['top_categories'] = [dict(r) for r in cursor.fetchall()]
        for cat in reco_metrics['top_categories']:
            for k, v in cat.items():
                if hasattr(v, '__float__'):
                    cat[k] = float(v)
        
        # Top merchants
        cursor.execute("""
            SELECT partner_name, category_name, total_visits, unique_visitors, 
                   popularity_score, active_promotion_count
            FROM cp_merchants_catalog WHERE is_active = 1
            ORDER BY popularity_score DESC LIMIT 10
        """)
        reco_metrics['top_merchants'] = [dict(r) for r in cursor.fetchall()]
        for m in reco_metrics['top_merchants']:
            for k, v in m.items():
                if hasattr(v, '__float__'):
                    m[k] = float(v)
        
        # Interaction stats (last 7 days)
        cursor.execute("""
            SELECT interaction_type, source, COUNT(*) as cnt
            FROM cp_user_offer_interactions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY interaction_type, source
        """)
        reco_metrics['interactions_7d'] = [dict(r) for r in cursor.fetchall()]
        
        # Model metrics from file
        metrics_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'merchant_recommender_metrics.json')
        if os.path.exists(metrics_path):
            with open(metrics_path) as f:
                model_data = json.load(f)
                reco_metrics['model'] = {
                    'trained_at': model_data.get('trained_at'),
                    'n_train_samples': model_data.get('n_train_samples'),
                    'eval_results': model_data.get('eval_results', {}),
                    'top_features': list(model_data.get('feature_importances', {}).keys())[:5],
                }
    except Exception as e:
        reco_metrics['error'] = str(e)
    
    conn.close()
    return reco_metrics

async def generate_report(metrics):
    api_key = os.environ.get('EMERGENT_LLM_KEY', '') or os.environ.get('OPENAI_API_KEY', '')
    if not api_key:
        return {"titre": "Rapport Hebdomadaire ML", "resume_executif": "Cle API non configuree (EMERGENT_LLM_KEY ou OPENAI_API_KEY)", "raw": True}

    system_message = """Tu es un analyste expert en data science et telecom. Tu generes des rapports hebdomadaires pour le dashboard Club Privileges d'Ooredoo Tunisie. 
Tes rapports sont:
- En francais
- Structures avec des sections claires
- Actionables avec des recommandations concretes
- Bases sur les donnees fournies
Reponds UNIQUEMENT en JSON valide avec cette structure:
{
  "titre": "Rapport Hebdomadaire ML - [date]",
  "resume_executif": "2-3 phrases de resume",
  "kpis": [{"nom": "", "valeur": "", "tendance": "hausse|baisse|stable", "commentaire": ""}],
  "segments": [{"nom": "", "taille": 0, "taux_succes": 0, "risque_churn": 0, "action": ""}],
  "alertes": [{"niveau": "critique|attention|info", "message": ""}],
  "recommandations": [{"priorite": "P0|P1|P2", "action": "", "impact_estime": ""}],
  "modele_ml": {"accuracy": 0, "statut": "", "commentaire": ""},
  "recommandations_marchands": {"top_merchants": [], "categories_tendances": [], "engagement_reco": ""},
  "prochaines_etapes": [""]
}"""

    try:
        from emergentintegrations.llm.chat import LlmChat, UserMessage
        chat = LlmChat(api_key=api_key, session_id=f"weekly-report-{datetime.now().strftime('%Y%m%d')}", system_message=system_message).with_model("openai", "gpt-4o")
        send_msg = chat.send_message
        make_msg = lambda text: UserMessage(text=text)
    except ImportError:
        from openai import AsyncOpenAI
        client = AsyncOpenAI(api_key=api_key)
        async def _send(msg):
            resp = await client.chat.completions.create(
                model="gpt-4o",
                messages=[{"role": "system", "content": system_message}, {"role": "user", "content": msg.content}],
                temperature=0.7,
            )
            return resp.choices[0].message.content
        send_msg = _send
        class _Msg:
            def __init__(self, text):
                self.content = text
        make_msg = _Msg
    
    # Fetch merchant recommendation metrics
    reco_data = fetch_merchant_recommendation_metrics()
    
    prompt = f"""Voici les metriques du dashboard ML Club Privileges pour cette semaine:

**Statistiques Globales:**
- Clients actifs: {metrics['overall'].get('total_clients', 'N/A')}
- Taux de succes moyen: {float(metrics['overall'].get('avg_success', 0))*100:.2f}%
- Probabilite churn moyenne: {float(metrics['overall'].get('avg_churn', 0))*100:.2f}%
- Total paiements: {metrics['overall'].get('total_payments', 0)}
- Date des donnees: {metrics['overall'].get('latest_date', 'N/A')}

**Segments Clients:**
{json.dumps(metrics['segments'], indent=2, ensure_ascii=False)}

**Performance Modele ML:**
{json.dumps(metrics.get('model', {}), indent=2, ensure_ascii=False)}

**Tests A/B en cours:**
{json.dumps(metrics.get('ab_tests', []), indent=2, ensure_ascii=False)}

**Moteur de Recommandation Marchands ML:**
- Marchands actifs dans le catalogue: {reco_data.get('active_merchants', 'N/A')}
- Profils utilisateurs: {reco_data.get('profiled_users', 'N/A')}
- Paires utilisateur-marchand: {reco_data.get('user_merchant_pairs', 'N/A')}
- Top Categories: {json.dumps(reco_data.get('top_categories', []), indent=2, ensure_ascii=False)}
- Top 10 Marchands (par popularite): {json.dumps(reco_data.get('top_merchants', []), indent=2, ensure_ascii=False)}
- Interactions recommandations (7j): {json.dumps(reco_data.get('interactions_7d', []), indent=2, ensure_ascii=False)}
- Performance modele recommandation: {json.dumps(reco_data.get('model', {}), indent=2, ensure_ascii=False)}

Genere le rapport hebdomadaire complet en JSON. Inclus une section specifique sur les recommandations marchands avec les tendances de categories et suggestions d'optimisation."""
    
    message = make_msg(prompt)
    response = await send_msg(message)
    
    # Parse JSON from response
    response_text = response.strip()
    if response_text.startswith('```'):
        response_text = response_text.split('\n', 1)[1]
        if response_text.endswith('```'):
            response_text = response_text[:-3]
    
    try:
        report = json.loads(response_text)
    except json.JSONDecodeError:
        report = {"titre": "Rapport Hebdomadaire ML", "resume_executif": response_text, "raw": True}
    
    return report

def main():
    print("Fetching metrics...")
    metrics = fetch_metrics()
    print(f"Metrics fetched: {metrics['overall'].get('total_clients', 0)} clients")
    
    print("Generating AI report...")
    report = asyncio.run(generate_report(metrics))
    
    # Save report
    output_dir = os.path.join(os.path.dirname(__file__), '..', 'storage', 'app', 'ml_reports')
    os.makedirs(output_dir, exist_ok=True)
    
    filename = f"weekly_report_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
    output_path = os.path.join(output_dir, filename)
    
    reco_snapshot = fetch_merchant_recommendation_metrics()
    with open(output_path, 'w', encoding='utf-8') as f:
        json.dump({
            'report': report,
            'metrics_snapshot': metrics,
            'merchant_reco_snapshot': reco_snapshot,
            'generated_at': datetime.now().isoformat(),
            'filename': filename
        }, f, ensure_ascii=False, indent=2)
    
    print(f"Report saved: {output_path}")
    print(json.dumps(report, ensure_ascii=False, indent=2))

if __name__ == '__main__':
    main()
