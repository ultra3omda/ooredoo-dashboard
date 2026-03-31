#!/usr/bin/env python3
"""
Merchant Recommendation Inference Engine.
Loads trained LightGBM model and scores merchants for a given user.
"""
import os
import json
import pymysql
import numpy as np
import joblib

MODEL_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.join(MODEL_DIR, 'merchant_recommender.joblib')
FALLBACK_PATH = os.path.join(MODEL_DIR, 'merchant_fallback_popular.json')

_cached_model = None
_cached_fallback = None


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
    Get merchant recommendations for a user.
    Returns list of {partner_id, partner_name, category_name, score, reason}
    """
    conn = get_db_connection()
    cursor = conn.cursor()
    
    try:
        # Check if user has a profile
        cursor.execute("SELECT * FROM cp_user_profile WHERE client_id = %s", (client_id,))
        user_profile = cursor.fetchone()
        
        if not user_profile:
            # Cold start: return popular merchants
            return _cold_start_recommendations(cursor, top_k, category_id)
        
        # Load model
        model_data = load_model()
        if not model_data:
            return _cold_start_recommendations(cursor, top_k, category_id)
        
        model = model_data['model']
        feature_cols = model_data['feature_cols']
        
        # Get user's existing merchant history
        cursor.execute(
            "SELECT partner_id, visit_count FROM cp_user_merchant_history WHERE client_id = %s",
            (client_id,)
        )
        visited = {r['partner_id']: r['visit_count'] for r in cursor.fetchall()}
        
        # Get all active merchants
        merchant_filter = "WHERE mc.is_active = 1"
        params = []
        if category_id:
            merchant_filter += " AND mc.category_id = %s"
            params.append(category_id)
        if exclude_visited and visited:
            placeholders = ','.join(['%s'] * len(visited))
            merchant_filter += f" AND mc.partner_id NOT IN ({placeholders})"
            params.extend(visited.keys())
        
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
            return []
        
        # Build feature matrix for all merchants
        sub_tier = {'premium': 3, 'standard': 2, 'test': 1}.get(
            user_profile.get('subscription_type', 'none'), 0
        )
        
        features_list = []
        merchant_info = []
        
        for m in merchants:
            pid = m['partner_id']
            umh = visited.get(pid)
            
            # User-merchant interaction features (0 if not visited)
            if umh:
                cursor.execute(
                    "SELECT * FROM cp_user_merchant_history WHERE client_id = %s AND partner_id = %s",
                    (client_id, pid)
                )
                umh_data = cursor.fetchone() or {}
            else:
                umh_data = {}
            
            features = {
                'visit_count': umh_data.get('visit_count', 0),
                'unique_promotions_used': umh_data.get('unique_promotions_used', 0),
                'days_since_last_visit': umh_data.get('days_since_last_visit', 9999),
                'avg_days_between_visits': float(umh_data.get('avg_days_between_visits', 0)),
                'recency_score': float(umh_data.get('recency_score', 0)),
                'frequency_score': float(umh_data.get('frequency_score', 0)),
                'user_total_visits': user_profile.get('total_visits', 0),
                'unique_merchants_visited': user_profile.get('unique_merchants_visited', 0),
                'unique_categories_visited': user_profile.get('unique_categories_visited', 0),
                'days_since_last_activity': user_profile.get('days_since_last_activity', 0),
                'user_avg_visits': float(user_profile.get('avg_visits_per_merchant', 0)),
                'category_diversity_score': float(user_profile.get('category_diversity_score', 0)),
                'loyalty_score': float(user_profile.get('loyalty_score', 0)),
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
            merchant_info.append(m)
        
        # Score all merchants
        X = np.array(features_list)
        scores = model.predict(X)
        
        # Sort by score descending
        ranked_indices = np.argsort(scores)[::-1][:top_k]
        
        results = []
        for rank, idx in enumerate(ranked_indices):
            m = merchant_info[idx]
            score = float(scores[idx])
            
            # Generate human-readable reason
            reason = _generate_reason(m, user_profile, visited.get(m['partner_id']))
            
            results.append({
                'partner_id': int(m['partner_id']),
                'partner_name': m['partner_name'],
                'category_name': m['category_name'] or 'Autre',
                'score': round(score, 4),
                'rank': rank + 1,
                'active_promotions': m['active_promotion_count'],
                'avg_discount': float(m['avg_discount']),
                'reason': reason,
                'already_visited': m['partner_id'] in visited,
            })
        
        return results
        
    finally:
        conn.close()


def _cold_start_recommendations(cursor, top_k, category_id=None):
    """Fallback for users without history."""
    fallback = load_fallback()
    if category_id:
        # We need to query DB for category filtering
        cat_filter = "AND mc.category_id = %s" if category_id else ""
        cursor.execute(f"""
            SELECT partner_id, partner_name, category_name, popularity_score,
                   active_promotion_count, avg_discount
            FROM cp_merchants_catalog mc
            WHERE is_active = 1 AND total_visits > 0 {cat_filter}
            ORDER BY popularity_score DESC
            LIMIT %s
        """, (category_id, top_k) if category_id else (top_k,))
        rows = cursor.fetchall()
        return [{
            'partner_id': int(r['partner_id']),
            'partner_name': r['partner_name'],
            'category_name': r['category_name'] or 'Autre',
            'score': float(r['popularity_score']),
            'rank': i + 1,
            'active_promotions': r['active_promotion_count'],
            'avg_discount': float(r['avg_discount']),
            'reason': 'Populaire auprès des utilisateurs',
            'already_visited': False,
        } for i, r in enumerate(rows)]
    
    return [{
        **m,
        'rank': i + 1,
        'score': m['popularity_score'],
        'reason': 'Populaire auprès des utilisateurs',
        'already_visited': False,
    } for i, m in enumerate(fallback[:top_k])]


def _generate_reason(merchant, user_profile, visited_count):
    """Generate human-readable recommendation reason."""
    reasons = []
    
    if merchant['category_id'] == user_profile.get('favorite_category_id'):
        reasons.append(f"Dans votre catégorie préférée ({merchant['category_name']})")
    
    if visited_count and visited_count > 2:
        reasons.append(f"Vous y allez régulièrement ({visited_count} visites)")
    elif visited_count:
        reasons.append("Vous avez déjà visité ce marchand")
    
    if merchant['active_promotion_count'] > 3:
        reasons.append(f"{merchant['active_promotion_count']} promotions actives")
    
    if float(merchant['avg_discount']) >= 20:
        reasons.append(f"Remise moyenne de {float(merchant['avg_discount']):.0f}%")
    
    if merchant['is_featured']:
        reasons.append("Marchand en vedette")
    
    if float(merchant['popularity_score']) > 5:
        reasons.append("Très populaire")
    
    if not reasons:
        reasons.append("Recommandé pour vous")
    
    return " · ".join(reasons[:3])
