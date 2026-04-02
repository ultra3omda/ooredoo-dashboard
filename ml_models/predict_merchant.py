#!/usr/bin/env python3
"""
Merchant Recommendation Engine — Inspired by AWS Personalize.
LightGBM scoring + Exploration/Exploitation + Contextual Linking + Collaborative Signal.

Recommendation Types (like AWS Personalize Campaigns):
- DISCOVERY: Merchant the user has never visited, predicted to match their profile
- RE_ENGAGEMENT: Merchant visited before but not recently (>30 days)
- LOYALTY: Merchant the user visits frequently and keeps returning to
- TRENDING: Popular merchant with high recent activity
"""
import os
import json
import pymysql
import numpy as np
import joblib
from datetime import datetime

MODEL_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.join(MODEL_DIR, 'merchant_recommender.joblib')
FALLBACK_PATH = os.path.join(MODEL_DIR, 'merchant_fallback_popular.json')

_cached_model = None
_cached_fallback = None

# AWS Personalize-inspired: Exploration weight (0=pure exploitation, 1=pure exploration)
EXPLORATION_WEIGHT = 0.15


def get_db_connection():
    env_path = os.path.join(os.path.dirname(MODEL_DIR), '.env')
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


def load_model():
    global _cached_model
    if _cached_model is None and os.path.exists(MODEL_PATH):
        _cached_model = joblib.load(MODEL_PATH)
    return _cached_model


def load_fallback():
    global _cached_fallback
    if _cached_fallback is None and os.path.exists(FALLBACK_PATH):
        with open(FALLBACK_PATH) as f:
            _cached_fallback = json.load(f)
    return _cached_fallback or []


def get_recommendations(client_id: int, top_k: int = 10, category_id: int = None,
                        exclude_visited: bool = False):
    """
    AWS Personalize-inspired merchant recommendations.
    Returns (recommendations_list, source_type, user_context)
    """
    conn = get_db_connection()
    cursor = conn.cursor()

    try:
        cursor.execute("SELECT * FROM cp_user_profile WHERE client_id = %s", (client_id,))
        user_profile = cursor.fetchone()

        if not user_profile:
            recs = _cold_start_recommendations(cursor, top_k, category_id)
            return recs, 'fallback_popularity', _empty_user_context(client_id)

        model_data = load_model()
        if not model_data:
            recs = _cold_start_recommendations(cursor, top_k, category_id)
            return recs, 'fallback_popularity', _empty_user_context(client_id)

        model = model_data['model']
        feature_cols = model_data['feature_cols']

        # Batch: user's full merchant history
        cursor.execute("SELECT * FROM cp_user_merchant_history WHERE client_id = %s", (client_id,))
        umh_rows = cursor.fetchall()
        umh_map = {r['partner_id']: r for r in umh_rows}

        # Batch: visited merchants with names/categories for "because you visited" linking
        visited_merchants_info = {}
        if umh_map:
            pids = list(umh_map.keys())
            placeholders = ','.join(['%s'] * len(pids))
            cursor.execute(f"""
                SELECT partner_id, partner_name, category_id, category_name
                FROM cp_merchants_catalog WHERE partner_id IN ({placeholders})
            """, pids)
            for r in cursor.fetchall():
                visited_merchants_info[r['partner_id']] = r

        # All active merchants
        merchant_filter = "WHERE mc.is_active = 1"
        params = []
        if category_id:
            merchant_filter += " AND mc.category_id = %s"
            params.append(category_id)
        if exclude_visited and umh_map:
            placeholders = ','.join(['%s'] * len(umh_map))
            merchant_filter += f" AND mc.partner_id NOT IN ({placeholders})"
            params.extend(umh_map.keys())

        cursor.execute(f"""
            SELECT mc.partner_id, mc.partner_name, mc.category_id, mc.category_name,
                   mc.active_promotion_count, mc.total_promotion_count,
                   mc.avg_discount, mc.max_discount, mc.total_visits as merchant_total_visits,
                   mc.unique_visitors as merchant_unique_visitors, mc.popularity_score,
                   mc.avg_visits_per_user as merchant_avg_visits,
                   mc.is_featured, mc.is_premium as merchant_is_premium, mc.location_count
            FROM cp_merchants_catalog mc
            {merchant_filter}
        """, params)
        merchants = cursor.fetchall()

        if not merchants:
            return [], 'ml_model', _build_user_context(client_id, user_profile, umh_map)

        # Collaborative signal: batch query
        # "How many users with same favorite category also visit each merchant?"
        fav_cat = user_profile.get('favorite_category_id')
        collab_map = {}
        if fav_cat:
            rec_pids = [m['partner_id'] for m in merchants]
            # Sample: count users with same fav category who visited each merchant
            placeholders = ','.join(['%s'] * len(rec_pids))
            cursor.execute(f"""
                SELECT umh.partner_id, COUNT(DISTINCT umh.client_id) as similar_users
                FROM cp_user_merchant_history umh
                JOIN cp_user_profile up2 ON umh.client_id = up2.client_id
                WHERE umh.partner_id IN ({placeholders})
                AND up2.favorite_category_id = %s
                AND up2.client_id != %s
                GROUP BY umh.partner_id
            """, rec_pids + [fav_cat, client_id])
            for r in cursor.fetchall():
                collab_map[r['partner_id']] = int(r['similar_users'])

        # Build feature matrix
        sub_tier = {'premium': 3, 'standard': 2, 'test': 1}.get(
            user_profile.get('subscription_type', 'none'), 0
        )

        features_list = []
        merchant_info = []

        for m in merchants:
            pid = m['partner_id']
            umh_data = umh_map.get(pid, {})

            features = {
                'visit_count': umh_data.get('visit_count', 0),
                'unique_promotions_used': umh_data.get('unique_promotions_used', 0),
                'days_since_last_visit': umh_data.get('days_since_last_visit', 9999),
                'avg_days_between_visits': float(umh_data.get('avg_days_between_visits', 0) or 0),
                'recency_score': float(umh_data.get('recency_score', 0) or 0),
                'frequency_score': float(umh_data.get('frequency_score', 0) or 0),
                'user_total_visits': user_profile.get('total_visits', 0),
                'unique_merchants_visited': user_profile.get('unique_merchants_visited', 0),
                'unique_categories_visited': user_profile.get('unique_categories_visited', 0),
                'days_since_last_activity': user_profile.get('days_since_last_activity', 0),
                'user_avg_visits': float(user_profile.get('avg_visits_per_merchant', 0) or 0),
                'category_diversity_score': float(user_profile.get('category_diversity_score', 0) or 0),
                'loyalty_score': float(user_profile.get('loyalty_score', 0) or 0),
                'subscription_tier': sub_tier,
                'is_female': 1 if user_profile.get('gender') == 'F' else 0,
                'age': user_profile.get('age') or 30,
                'active_promotion_count': m['active_promotion_count'],
                'total_promotion_count': m['total_promotion_count'],
                'avg_discount': float(m['avg_discount']),
                'max_discount': float(m['max_discount']),
                'merchant_total_visits': m['merchant_total_visits'],
                'merchant_unique_visitors': m['merchant_unique_visitors'],
                'popularity_score': float(m['popularity_score']),
                'merchant_avg_visits': float(m['merchant_avg_visits']),
                'is_featured': m['is_featured'],
                'merchant_is_premium': m['merchant_is_premium'],
                'location_count': m['location_count'],
                'same_fav_category': 1 if m['category_id'] == user_profile.get('favorite_category_id') else 0,
            }

            features_list.append([features[c] for c in feature_cols])
            merchant_info.append({**m, '_features': features})

        # Batch prediction
        X = np.array(features_list)
        raw_scores = model.predict(X)

        # AWS Personalize-inspired: Exploration/Exploitation
        # Boost unvisited merchants with active promotions (exploration)
        exploration_bonus = np.zeros(len(raw_scores))
        for i, m in enumerate(merchant_info):
            pid = m['partner_id']
            if pid not in umh_map:  # Unvisited = exploration candidate
                promo_bonus = min(m['active_promotion_count'] * 0.05, 0.3)
                pop_bonus = min(float(m['popularity_score']) / 100, 0.2)
                exploration_bonus[i] = promo_bonus + pop_bonus

        # Combine: (1-w) * exploitation + w * exploration
        score_range = raw_scores.max() - raw_scores.min() if raw_scores.max() > raw_scores.min() else 1
        final_scores = raw_scores + EXPLORATION_WEIGHT * exploration_bonus * score_range

        # Rank by final scores
        ranked_indices = np.argsort(final_scores)[::-1][:top_k]

        score_min = float(final_scores.min())
        score_max = float(final_scores.max())

        results = []
        for rank, idx in enumerate(ranked_indices):
            m = merchant_info[idx]
            feat = m['_features']
            score = float(final_scores[idx])
            raw_score = float(raw_scores[idx])
            visited_count = umh_map.get(m['partner_id'], {}).get('visit_count', 0)

            score_normalized = round((score - score_min) / (score_max - score_min) * 100, 1) if score_max > score_min else 50.0

            # Recommendation type (AWS Personalize campaign style)
            rec_type = _classify_recommendation(m, umh_map, feat)

            # "Because you visited" — contextual linking
            because = _find_because_you_visited(m, umh_map, visited_merchants_info, user_profile)

            # Collaborative signal
            similar_users = collab_map.get(m['partner_id'], 0)

            # Explanation
            reason = _generate_reason(m, user_profile, visited_count, rec_type, because)
            explanation = _generate_detailed_explanation(
                feat, user_profile, m, raw_score, score_normalized, rec_type, because, similar_users
            )

            results.append({
                'partner_id': int(m['partner_id']),
                'partner_name': m['partner_name'],
                'category_name': m['category_name'] or 'Autre',
                'category_id': m['category_id'],
                'score': round(score, 4),
                'score_raw': round(raw_score, 4),
                'score_normalized': score_normalized,
                'rank': rank + 1,
                'active_promotions': m['active_promotion_count'],
                'avg_discount': float(m['avg_discount']),
                'max_discount': float(m['max_discount']),
                'reason': reason,
                'explanation': explanation,
                'recommendation_type': rec_type,
                'already_visited': m['partner_id'] in umh_map,
                'visit_count': visited_count,
                'because_you_visited': because,
                'similar_users_count': similar_users,
                'is_featured': bool(m['is_featured']),
                'is_premium': bool(m['merchant_is_premium']),
                'location_count': m['location_count'],
            })

        user_context = _build_user_context(client_id, user_profile, umh_map)
        return results, 'ml_model', user_context

    finally:
        conn.close()


# ─── AWS PERSONALIZE-INSPIRED HELPERS ─────────────────────────────────────

def _classify_recommendation(merchant, umh_map, features):
    """Classify the recommendation type, like AWS Personalize campaigns."""
    pid = merchant['partner_id']
    if pid not in umh_map:
        # Never visited
        if float(merchant['popularity_score']) > 10 and merchant['active_promotion_count'] >= 3:
            return 'TRENDING'
        return 'DISCOVERY'
    else:
        # Previously visited
        days = features.get('days_since_last_visit', 9999)
        visits = features.get('visit_count', 0)
        if days > 30 and visits >= 1:
            return 'RE_ENGAGEMENT'
        if visits >= 3:
            return 'LOYALTY'
        return 'RE_ENGAGEMENT'


def _find_because_you_visited(rec_merchant, umh_map, visited_info, user_profile):
    """
    Find which past visits explain this recommendation (contextual linking).
    Like AWS Personalize "Because you interacted with X".
    """
    rec_cat = rec_merchant['category_id']
    rec_pid = rec_merchant['partner_id']
    links = []

    for pid, umh_data in umh_map.items():
        if pid == rec_pid:
            continue
        info = visited_info.get(pid, {})
        if not info:
            continue

        relevance = 0
        link_reason = ''

        # Same category = strong link
        if info.get('category_id') == rec_cat:
            relevance = 3 + (umh_data.get('visit_count', 0) or 0) * 0.1
            link_reason = f"Meme categorie ({info.get('category_name', '')})"
        # High visit count = moderate link (general affinity)
        elif (umh_data.get('visit_count', 0) or 0) >= 5:
            relevance = 1
            link_reason = "Client fidele multi-marchands"

        if relevance > 0:
            links.append({
                'partner_id': int(pid),
                'partner_name': info.get('partner_name', ''),
                'category_name': info.get('category_name', ''),
                'visit_count': umh_data.get('visit_count', 0) or 0,
                'relevance': round(relevance, 2),
                'link_reason': link_reason,
            })

    # Sort by relevance and return top 3
    links.sort(key=lambda x: x['relevance'], reverse=True)
    return links[:3]


def _build_user_context(client_id, user_profile, umh_map):
    """Build rich user context for the response."""
    # Visited categories distribution
    cat_visits = {}
    for pid, umh_data in umh_map.items():
        cat_id = umh_data.get('category_id')
        if cat_id:
            cat_visits[cat_id] = cat_visits.get(cat_id, 0) + (umh_data.get('visit_count', 0) or 0)

    return {
        'client_id': int(client_id),
        'total_visits': user_profile.get('total_visits', 0),
        'unique_merchants': user_profile.get('unique_merchants_visited', 0),
        'unique_categories': user_profile.get('unique_categories_visited', 0),
        'loyalty_score': float(user_profile.get('loyalty_score', 0) or 0),
        'subscription_type': user_profile.get('subscription_type', 'N/A'),
        'favorite_category': user_profile.get('favorite_category_id'),
        'gender': user_profile.get('gender', 'N/A'),
        'age': user_profile.get('age'),
        'days_since_last_activity': user_profile.get('days_since_last_activity', 0),
        'category_diversity': float(user_profile.get('category_diversity_score', 0) or 0),
        'avg_visits_per_merchant': float(user_profile.get('avg_visits_per_merchant', 0) or 0),
    }


def _empty_user_context(client_id):
    return {
        'client_id': int(client_id),
        'total_visits': 0, 'unique_merchants': 0, 'unique_categories': 0,
        'loyalty_score': 0, 'subscription_type': 'N/A', 'favorite_category': None,
        'gender': 'N/A', 'age': None, 'days_since_last_activity': 0,
        'category_diversity': 0, 'avg_visits_per_merchant': 0,
    }


def _generate_reason(merchant, user_profile, visited_count, rec_type, because):
    """Generate short reason string."""
    parts = []

    type_labels = {
        'DISCOVERY': 'A decouvrir',
        'RE_ENGAGEMENT': 'A re-visiter',
        'LOYALTY': 'Votre favori',
        'TRENDING': 'Tendance',
    }
    parts.append(type_labels.get(rec_type, ''))

    if because:
        top_link = because[0]
        parts.append(f"Parce que vous avez visite {top_link['partner_name']}")
    elif merchant['category_id'] == user_profile.get('favorite_category_id'):
        parts.append(f"Dans votre categorie preferee ({merchant['category_name']})")
    elif visited_count > 2:
        parts.append(f"{visited_count} visites")

    if merchant['active_promotion_count'] > 2:
        parts.append(f"{merchant['active_promotion_count']} promos actives")

    return " · ".join([p for p in parts if p][:3])


def _generate_detailed_explanation(features, user_profile, merchant, raw_score,
                                    normalized_score, rec_type, because, similar_users):
    """Generate comprehensive explanation for a recommendation."""
    factors = []
    details = []

    # 1. Recommendation type context
    type_explanations = {
        'DISCOVERY': "Marchand non visite — le modele predit un fort potentiel d'interet base sur votre profil",
        'RE_ENGAGEMENT': "Vous avez deja visite ce marchand — il est temps d'y retourner",
        'LOYALTY': "Un de vos marchands preferes — recommande pour maintenir votre engagement",
        'TRENDING': "Marchand populaire en ce moment avec beaucoup de promotions actives",
    }
    factors.append(f"Type: {rec_type} — {type_explanations.get(rec_type, '')}")

    # 2. User-merchant relationship
    visit_count = features['visit_count']
    if visit_count > 0:
        recency = features['recency_score']
        frequency = features['frequency_score']
        factors.append(f"Historique: {visit_count} visites (recence={recency:.1f}/10, frequence={frequency:.1f}/10)")
        if recency > 5:
            details.append("Visite recente = score booste")
        if frequency > 5:
            details.append("Frequentation reguliere = score booste")
    else:
        details.append("Pas d'historique direct — prediction basee sur profil + caracteristiques marchand")

    # 3. "Because you visited" links
    if because:
        names = [b['partner_name'] for b in because[:2]]
        factors.append(f"Parce que vous avez visite: {', '.join(names)}")
        for b in because[:2]:
            details.append(f"Lien avec {b['partner_name']} ({b['link_reason']}, {b['visit_count']} visites)")

    # 4. Category match
    if features['same_fav_category']:
        factors.append(f"Categorie preferee du client ({merchant.get('category_name', '')})")

    # 5. Merchant attractiveness
    promos = features['active_promotion_count']
    discount = features['avg_discount']
    popularity = features['popularity_score']
    if promos > 0:
        factors.append(f"Offre: {promos} promos, remise moy. {discount:.0f}%, max {features['max_discount']:.0f}%")
    if popularity > 5:
        factors.append(f"Popularite: {popularity:.1f}/10 ({merchant['merchant_unique_visitors']} visiteurs)")

    # 6. Collaborative signal
    if similar_users > 0:
        factors.append(f"Signal collaboratif: {similar_users} clients similaires visitent aussi ce marchand")

    # 7. Exploration boost
    if rec_type == 'DISCOVERY' and visit_count == 0:
        details.append("Bonus exploration applique (+15% pour favoriser la decouverte)")

    # Score interpretation
    if normalized_score >= 80:
        interp = "Excellente correspondance — tres pertinent pour ce client"
    elif normalized_score >= 60:
        interp = "Bonne correspondance — correspond bien au profil"
    elif normalized_score >= 40:
        interp = "Correspondance moderee — potentiel d'interet"
    else:
        interp = "Suggestion exploratoire — pour diversifier les decouvertes"

    return {
        'summary': interp,
        'recommendation_type': rec_type,
        'factors': factors,
        'details': details,
        'score_interpretation': f"Score modele={raw_score:.4f}, normalise={normalized_score}/100",
        'model_type': 'LightGBM LambdaRank + Exploration/Exploitation',
        'exploration_weight': EXPLORATION_WEIGHT,
    }


# ─── COLD START (FALLBACK) ───────────────────────────────────────────────

def _cold_start_recommendations(cursor, top_k, category_id=None):
    """Fallback for new users without history."""
    fallback = load_fallback()
    if category_id:
        cursor.execute("""
            SELECT partner_id, partner_name, category_name, category_id, popularity_score,
                   active_promotion_count, avg_discount, max_discount, unique_visitors, location_count
            FROM cp_merchants_catalog mc
            WHERE is_active = 1 AND total_visits > 0 AND mc.category_id = %s
            ORDER BY popularity_score DESC LIMIT %s
        """, (category_id, top_k))
        rows = cursor.fetchall()
        if rows:
            return _format_fallback_from_rows(rows)

    if fallback:
        max_pop = max(m.get('popularity_score', 1) for m in fallback[:top_k])
        min_pop = min(m.get('popularity_score', 0) for m in fallback[:top_k]) if len(fallback) > 1 else 0
    else:
        max_pop, min_pop = 1, 0

    return [{
        **m,
        'rank': i + 1,
        'score': m.get('popularity_score', 0),
        'score_raw': m.get('popularity_score', 0),
        'score_normalized': round((m.get('popularity_score', 0) - min_pop) / (max_pop - min_pop) * 100, 1) if max_pop > min_pop else 50.0,
        'recommendation_type': 'TRENDING' if m.get('active_promotions', m.get('active_promotion_count', 0)) >= 3 else 'DISCOVERY',
        'reason': 'Populaire aupres des utilisateurs',
        'explanation': {
            'summary': 'Nouveau client — recommandation basee sur la popularite globale.',
            'recommendation_type': 'COLD_START',
            'factors': ['Popularite generale du marchand', 'Pas de profil utilisateur disponible'],
            'details': ['Apres quelques visites, les recommandations deviendront personnalisees'],
            'score_interpretation': 'Score = popularite brute',
            'model_type': 'Fallback Popularity',
            'exploration_weight': 0,
        },
        'because_you_visited': [],
        'similar_users_count': 0,
        'already_visited': False,
        'visit_count': 0,
        'active_promotions': m.get('active_promotions', m.get('active_promotion_count', 0)),
        'max_discount': m.get('max_discount', m.get('avg_discount', 0)),
        'location_count': m.get('location_count', 1),
    } for i, m in enumerate(fallback[:top_k])]


def _format_fallback_from_rows(rows):
    if rows:
        max_pop = max(float(r['popularity_score']) for r in rows)
        min_pop = min(float(r['popularity_score']) for r in rows) if len(rows) > 1 else 0
    else:
        max_pop, min_pop = 1, 0
    return [{
        'partner_id': int(r['partner_id']),
        'partner_name': r['partner_name'],
        'category_name': r['category_name'] or 'Autre',
        'category_id': r.get('category_id'),
        'score': float(r['popularity_score']),
        'score_raw': float(r['popularity_score']),
        'score_normalized': round((float(r['popularity_score']) - min_pop) / (max_pop - min_pop) * 100, 1) if max_pop > min_pop else 50.0,
        'rank': i + 1,
        'active_promotions': r['active_promotion_count'],
        'avg_discount': float(r['avg_discount']),
        'max_discount': float(r.get('max_discount', 0)),
        'recommendation_type': 'TRENDING',
        'reason': 'Populaire aupres des utilisateurs',
        'explanation': {
            'summary': 'Nouveau client — recommandation basee sur la popularite.',
            'recommendation_type': 'COLD_START',
            'factors': ['Popularite du marchand'],
            'details': [],
            'score_interpretation': 'Score = popularite brute',
            'model_type': 'Fallback Popularity',
            'exploration_weight': 0,
        },
        'because_you_visited': [],
        'similar_users_count': 0,
        'already_visited': False,
        'visit_count': 0,
    } for i, r in enumerate(rows)]
