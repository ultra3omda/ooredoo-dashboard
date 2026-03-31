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

def get_db_config():
    return {
        'host': os.environ.get('DB_HOST', '51.38.187.245'),
        'port': int(os.environ.get('DB_PORT', 3306)),
        'user': os.environ.get('DB_USERNAME', 'looker_user'),
        'password': os.environ.get('DB_PASSWORD', 'lokaszsh98@Datahive_looker'),
        'database': os.environ.get('DB_DATABASE', 'clubprivileges'),
    }

def fetch_metrics():
    config = get_db_config()
    conn = pymysql.connect(**config)
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

async def generate_report(metrics):
    from emergentintegrations.llm.chat import LlmChat, UserMessage
    
    api_key = os.environ.get('EMERGENT_LLM_KEY', '')
    
    chat = LlmChat(
        api_key=api_key,
        session_id=f"weekly-report-{datetime.now().strftime('%Y%m%d')}",
        system_message="""Tu es un analyste expert en data science et telecom. Tu generes des rapports hebdomadaires pour le dashboard Club Privileges d'Ooredoo Tunisie. 
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
  "prochaines_etapes": [""]
}"""
    ).with_model("openai", "gpt-4o")
    
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

Genere le rapport hebdomadaire complet en JSON."""
    
    message = UserMessage(text=prompt)
    response = await chat.send_message(message)
    
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
    
    with open(output_path, 'w', encoding='utf-8') as f:
        json.dump({
            'report': report,
            'metrics_snapshot': metrics,
            'generated_at': datetime.now().isoformat(),
            'filename': filename
        }, f, ensure_ascii=False, indent=2)
    
    print(f"Report saved: {output_path}")
    print(json.dumps(report, ensure_ascii=False, indent=2))

if __name__ == '__main__':
    main()
