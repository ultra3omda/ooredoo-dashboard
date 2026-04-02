#!/usr/bin/env python3
"""
Merchant Intelligence Engine — Analyse des performances marchands.
Détecte les anomalies de trafic, analyse les patterns, et génère
des recommandations commerciales actionnables via Gemini/GPT.
"""
import os
import json
import pymysql
import numpy as np
from datetime import datetime, timedelta
from collections import defaultdict


def get_db_connection():
    env_path = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), '.env')
    config = {}
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


def analyze_merchant_traffic(partner_id: int = None, days: int = 90):
    """
    Analyse le trafic par marchand sur N jours.
    Détecte: pics, creux, tendances, saisonnalité.
    """
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        end_date = datetime.now()
        start_date = end_date - timedelta(days=days)

        where_clause = "WHERE h.time BETWEEN %s AND %s"
        params = [start_date, end_date]
        if partner_id:
            where_clause += " AND p.partner_id = %s"
            params.append(partner_id)

        # Daily transactions per merchant
        cursor.execute(f"""
            SELECT p.partner_id, p.partner_name,
                   pc.partner_category_name as category,
                   DATE(h.time) as day,
                   COUNT(h.history_id) as tx_count,
                   COUNT(DISTINCT h.client_id) as unique_users
            FROM history h
            JOIN promotion pr ON h.promotion_id = pr.promotion_id
            JOIN partner p ON pr.partner_id = p.partner_id
            LEFT JOIN partner_category pc ON p.partner_category_id = pc.partner_category_id
            {where_clause}
            GROUP BY p.partner_id, p.partner_name, pc.partner_category_name, DATE(h.time)
            ORDER BY p.partner_id, day
        """, params)
        rows = cursor.fetchall()

        # Group by merchant
        merchant_data = defaultdict(lambda: {'name': '', 'category': '', 'daily': []})
        for r in rows:
            pid = r['partner_id']
            merchant_data[pid]['name'] = r['partner_name']
            merchant_data[pid]['category'] = r['category'] or 'Autre'
            merchant_data[pid]['daily'].append({
                'day': r['day'].strftime('%Y-%m-%d'),
                'tx': r['tx_count'],
                'users': r['unique_users'],
            })

        # Promotion activity per merchant
        cursor.execute(f"""
            SELECT p.partner_id,
                   COUNT(pr.promotion_id) as total_promos,
                   SUM(CASE WHEN pr.promotion_active = 1 THEN 1 ELSE 0 END) as active_promos,
                   AVG(pr.promotion_discount) as avg_discount,
                   MAX(pr.promotion_discount) as max_discount
            FROM partner p
            JOIN promotion pr ON p.partner_id = pr.partner_id
            {'WHERE p.partner_id = %s' if partner_id else ''}
            GROUP BY p.partner_id
        """, [partner_id] if partner_id else [])
        promo_map = {r['partner_id']: r for r in cursor.fetchall()}

        # Analyze each merchant
        results = []
        for pid, data in merchant_data.items():
            daily = data['daily']
            if len(daily) < 7:
                continue

            tx_values = [d['tx'] for d in daily]
            user_values = [d['users'] for d in daily]

            avg_tx = np.mean(tx_values)
            std_tx = np.std(tx_values) if len(tx_values) > 1 else 0
            total_tx = sum(tx_values)
            total_days = len(daily)

            # Trend: compare last 7 days vs previous 7 days
            last_7 = tx_values[-7:] if len(tx_values) >= 7 else tx_values
            prev_7 = tx_values[-14:-7] if len(tx_values) >= 14 else tx_values[:7]
            trend_pct = ((np.mean(last_7) - np.mean(prev_7)) / max(np.mean(prev_7), 1)) * 100

            # Detect spikes (>2 std from mean)
            spikes = []
            drops = []
            for d in daily:
                if std_tx > 0:
                    z_score = (d['tx'] - avg_tx) / std_tx
                    if z_score > 2:
                        spikes.append({'day': d['day'], 'tx': d['tx'], 'z_score': round(z_score, 2)})
                    elif z_score < -1.5 and avg_tx > 2:
                        drops.append({'day': d['day'], 'tx': d['tx'], 'z_score': round(z_score, 2)})

            # Day of week analysis
            dow_tx = defaultdict(list)
            for d in daily:
                from datetime import date
                dt = date.fromisoformat(d['day'])
                dow_tx[dt.strftime('%A')].append(d['tx'])
            best_day = max(dow_tx.items(), key=lambda x: np.mean(x[1]))[0] if dow_tx else 'N/A'
            worst_day = min(dow_tx.items(), key=lambda x: np.mean(x[1]))[0] if dow_tx else 'N/A'

            # Health score (0-100)
            activity_score = min(total_tx / max(total_days, 1) * 10, 40)
            trend_score = max(0, min(trend_pct + 20, 30))
            consistency_score = max(0, 30 - (std_tx / max(avg_tx, 1) * 15))
            health_score = round(activity_score + trend_score + consistency_score)

            promo = promo_map.get(pid, {})
            # Convert Decimal to native types
            for pk in promo:
                if hasattr(promo[pk], 'as_integer_ratio'):
                    promo[pk] = float(promo[pk])

            # Classify merchant status
            if health_score >= 70:
                status = 'PERFORMANT'
            elif health_score >= 40:
                status = 'A_SURVEILLER'
            else:
                status = 'A_BOOSTER'

            results.append({
                'partner_id': int(pid),
                'partner_name': data['name'],
                'category': data['category'],
                'status': status,
                'health_score': health_score,
                'total_transactions': total_tx,
                'avg_daily_tx': round(avg_tx, 1),
                'total_unique_users': sum(user_values),
                'trend_7d_pct': round(trend_pct, 1),
                'best_day': best_day,
                'worst_day': worst_day,
                'spikes': spikes[:5],
                'drops': drops[:5],
                'active_promos': int(promo.get('active_promos', 0) or 0),
                'total_promos': int(promo.get('total_promos', 0) or 0),
                'avg_discount': round(float(promo.get('avg_discount', 0) or 0), 1),
                'period_days': total_days,
            })

        results.sort(key=lambda x: x['health_score'])
        return results

    finally:
        conn.close()


def get_top_merchants_to_boost(limit: int = 10):
    """Get merchants that need the most attention (lowest health scores)."""
    all_merchants = analyze_merchant_traffic(days=30)
    to_boost = [m for m in all_merchants if m['status'] == 'A_BOOSTER'][:limit]
    to_watch = [m for m in all_merchants if m['status'] == 'A_SURVEILLER'][:limit]
    performant = [m for m in all_merchants if m['status'] == 'PERFORMANT']
    performant.sort(key=lambda x: x['total_transactions'], reverse=True)
    return {
        'to_boost': to_boost,
        'to_watch': to_watch,
        'top_performers': performant[:5],
        'total_analyzed': len(all_merchants),
        'stats': {
            'performant': len([m for m in all_merchants if m['status'] == 'PERFORMANT']),
            'a_surveiller': len([m for m in all_merchants if m['status'] == 'A_SURVEILLER']),
            'a_booster': len([m for m in all_merchants if m['status'] == 'A_BOOSTER']),
        }
    }


async def generate_merchant_intelligence_report(merchants_data: dict, llm_key: str, model_provider: str = "gemini", model_name: str = "gemini-2.5-flash"):
    """
    Use Gemini/GPT to generate actionable commercial recommendations
    based on merchant traffic analysis.
    """
    import asyncio as _asyncio

    # Build prompt with real data
    to_boost = merchants_data.get('to_boost', [])[:5]
    to_watch = merchants_data.get('to_watch', [])[:5]
    top_perf = merchants_data.get('top_performers', [])[:3]
    stats = merchants_data.get('stats', {})

    boost_lines = []
    for m in to_boost:
        spikes_str = f", pics: {', '.join([s['day'] for s in m['spikes'][:2]])}" if m['spikes'] else ""
        drops_str = f", creux: {', '.join([d['day'] for d in m['drops'][:2]])}" if m['drops'] else ""
        boost_lines.append(
            f"- {m['partner_name']} ({m['category']}): score={m['health_score']}/100, "
            f"{m['avg_daily_tx']} tx/jour, tendance 7j={m['trend_7d_pct']:+.1f}%, "
            f"{m['active_promos']} promos actives, remise moy {m['avg_discount']}%, "
            f"meilleur jour={m['best_day']}, pire jour={m['worst_day']}{spikes_str}{drops_str}"
        )

    watch_lines = []
    for m in to_watch:
        watch_lines.append(
            f"- {m['partner_name']} ({m['category']}): score={m['health_score']}/100, "
            f"{m['avg_daily_tx']} tx/jour, tendance={m['trend_7d_pct']:+.1f}%"
        )

    perf_lines = []
    for m in top_perf:
        perf_lines.append(f"- {m['partner_name']} ({m['category']}): {m['total_transactions']} tx, score={m['health_score']}/100")

    prompt = f"""Analyse les performances des marchands Club Privileges et genere des recommandations commerciales actionables.

## Donnees (30 derniers jours)
- Total marchands analyses: {stats.get('performant', 0) + stats.get('a_surveiller', 0) + stats.get('a_booster', 0)}
- Performants: {stats.get('performant', 0)} | A surveiller: {stats.get('a_surveiller', 0)} | A booster: {stats.get('a_booster', 0)}

### Marchands a BOOSTER (priorite haute):
{chr(10).join(boost_lines) if boost_lines else "Aucun marchand critique"}

### Marchands a SURVEILLER:
{chr(10).join(watch_lines) if watch_lines else "Aucun"}

### Top Performeurs:
{chr(10).join(perf_lines) if perf_lines else "Aucun"}

## Ta mission:
Pour chaque marchand a booster, genere:
1. **Diagnostic**: Pourquoi le trafic est faible (basé sur les données)
2. **Actions commerciales** (3 max): ce que l'equipe commerciale CP doit faire cette semaine
3. **Strategie promotionnelle**: quel type de promo proposerait et quand (basé sur le meilleur/pire jour)
4. **Presence digitale**: recommandation pour ameliorer la visibilite (Google Business, reseaux sociaux)

Pour les marchands a surveiller, donne 1 action preventive.
Pour les top performers, identifie ce qui fonctionne pour les repliquer.

Genere aussi un **resume executif** (3-4 phrases) pour le CEO.

Reponds en JSON avec cette structure:
{{
  "executive_summary": "...",
  "boost_recommendations": [
    {{
      "partner_name": "...",
      "diagnostic": "...",
      "actions": ["action1", "action2", "action3"],
      "promo_strategy": "...",
      "digital_strategy": "...",
      "priority": "P0|P1|P2"
    }}
  ],
  "watch_alerts": [
    {{"partner_name": "...", "alert": "...", "action": "..."}}
  ],
  "success_patterns": ["pattern1", "pattern2"],
  "key_metrics": {{
    "total_merchants": ...,
    "boost_needed": ...,
    "avg_health_score": ...
  }}
}}"""

    system_message = "Tu es un consultant senior en strategie commerciale et marketing digital pour Club Privileges, un programme de fidelite en Tunisie avec 576 marchands partenaires. Tu analyses les donnees de performance des marchands et generes des recommandations actionables pour l'equipe commerciale. Tu es pragmatique et tes recommandations sont specifiques, mesurables et realisables dans la semaine. Reponds UNIQUEMENT en JSON valide."

    response = await _call_llm_universal(llm_key, system_message, prompt, model_provider, model_name)

    # Parse JSON response
    try:
        # Clean potential markdown code block
        clean = response.strip()
        if clean.startswith('```'):
            clean = clean.split('\n', 1)[1] if '\n' in clean else clean[3:]
            if clean.endswith('```'):
                clean = clean[:-3]
            clean = clean.strip()
            if clean.startswith('json'):
                clean = clean[4:].strip()
        return json.loads(clean)
    except json.JSONDecodeError:
        return {
            "executive_summary": response[:500],
            "boost_recommendations": [],
            "watch_alerts": [],
            "success_patterns": [],
            "raw_response": response,
        }


async def _call_llm_universal(api_key: str, system_message: str, prompt: str, provider: str = "gemini", model: str = "gemini-2.5-flash") -> str:
    """Universal LLM caller: tries emergentintegrations first, falls back to direct SDK."""
    try:
        from emergentintegrations.llm.chat import LlmChat, UserMessage
        chat = LlmChat(api_key=api_key, session_id=f"llm-{id(prompt)}", system_message=system_message)
        chat.with_model(provider, model)
        return await chat.send_message(UserMessage(text=prompt))
    except ImportError:
        pass

    if provider == "openai":
        from openai import AsyncOpenAI
        client = AsyncOpenAI(api_key=api_key)
        resp = await client.chat.completions.create(
            model=model,
            messages=[{"role": "system", "content": system_message}, {"role": "user", "content": prompt}],
            temperature=0.7,
        )
        return resp.choices[0].message.content
    elif provider == "gemini":
        import google.generativeai as genai
        genai.configure(api_key=api_key)
        gen_model = genai.GenerativeModel(model, system_instruction=system_message)
        resp = await _asyncio.to_thread(gen_model.generate_content, prompt)
        return resp.text
    else:
        raise ValueError(f"Provider non supporte: {provider}")
