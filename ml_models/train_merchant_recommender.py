#!/usr/bin/env python3
"""
Merchant Recommendation Engine - Feature Extraction & LightGBM Ranker Training.
Extracts user-merchant interaction features from MySQL, trains a LightGBM ranker,
and populates pre-computed tables for fast inference.
"""
import os
import sys
import json
import time
import pymysql
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
import joblib
import warnings
warnings.filterwarnings('ignore')

MODEL_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.join(MODEL_DIR, 'merchant_recommender.joblib')
METRICS_PATH = os.path.join(MODEL_DIR, 'merchant_recommender_metrics.json')

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
        charset='utf8mb4'
    )


def extract_merchants_catalog(conn):
    """Populate cp_merchants_catalog from partner + promotion + history data."""
    print("[1/5] Extracting merchants catalog...")
    cursor = conn.cursor()

    cursor.execute("""
        INSERT INTO cp_merchants_catalog 
            (partner_id, partner_name, category_id, category_name, location_count,
             active_promotion_count, total_promotion_count, avg_discount, max_discount,
             total_visits, unique_visitors, popularity_score, avg_visits_per_user,
             is_active, is_featured, is_premium, created_at, updated_at)
        SELECT 
            p.partner_id,
            p.partner_name,
            p.partner_category_id,
            pc.partner_category_name,
            COALESCE(loc.loc_count, 0),
            COALESCE(promo_active.cnt, 0),
            COALESCE(promo_all.cnt, 0),
            COALESCE(promo_all.avg_disc, 0),
            COALESCE(promo_all.max_disc, 0),
            COALESCE(hist.total_visits, 0),
            COALESCE(hist.unique_visitors, 0),
            -- Popularity = log(1 + visits) * (1 + unique_visitors/100)
            ROUND(LOG(1 + COALESCE(hist.total_visits, 0)) * (1 + COALESCE(hist.unique_visitors, 0) / 100.0), 4),
            CASE WHEN COALESCE(hist.unique_visitors, 0) > 0 
                 THEN ROUND(COALESCE(hist.total_visits, 0) / hist.unique_visitors, 4) ELSE 0 END,
            IF(p.partener_active = 1, 1, 0),
            IF(p.partner_featured = 1, 1, 0),
            COALESCE(promo_all.has_premium, 0),
            NOW(), NOW()
        FROM partner p
        LEFT JOIN partner_category pc ON p.partner_category_id = pc.partner_category_id
        LEFT JOIN (
            SELECT partner_id, COUNT(*) as loc_count 
            FROM partner_location GROUP BY partner_id
        ) loc ON p.partner_id = loc.partner_id
        LEFT JOIN (
            SELECT partner_id, COUNT(*) as cnt 
            FROM promotion WHERE promotion_active = 1 GROUP BY partner_id
        ) promo_active ON p.partner_id = promo_active.partner_id
        LEFT JOIN (
            SELECT partner_id, COUNT(*) as cnt, 
                   AVG(promotion_discount) as avg_disc, MAX(promotion_discount) as max_disc,
                   MAX(is_premium) as has_premium
            FROM promotion GROUP BY partner_id
        ) promo_all ON p.partner_id = promo_all.partner_id
        LEFT JOIN (
            SELECT pr.partner_id, COUNT(*) as total_visits, COUNT(DISTINCT h.client_id) as unique_visitors
            FROM history h
            JOIN promotion pr ON h.promotion_id = pr.promotion_id
            WHERE h.client_id IS NOT NULL
            GROUP BY pr.partner_id
        ) hist ON p.partner_id = hist.partner_id
        WHERE p.partener_active = 1
        ON DUPLICATE KEY UPDATE
            partner_name = VALUES(partner_name),
            category_id = VALUES(category_id),
            category_name = VALUES(category_name),
            location_count = VALUES(location_count),
            active_promotion_count = VALUES(active_promotion_count),
            total_promotion_count = VALUES(total_promotion_count),
            avg_discount = VALUES(avg_discount),
            max_discount = VALUES(max_discount),
            total_visits = VALUES(total_visits),
            unique_visitors = VALUES(unique_visitors),
            popularity_score = VALUES(popularity_score),
            avg_visits_per_user = VALUES(avg_visits_per_user),
            is_active = VALUES(is_active),
            is_featured = VALUES(is_featured),
            is_premium = VALUES(is_premium),
            updated_at = NOW()
    """)
    conn.commit()
    count = cursor.execute("SELECT COUNT(*) FROM cp_merchants_catalog")
    row = cursor.fetchone()
    print(f"   -> {row[0]} merchants in catalog")
    return row[0]


def extract_user_merchant_history(conn):
    """Populate cp_user_merchant_history from history + promotion joins."""
    print("[2/5] Extracting user-merchant history...")
    cursor = conn.cursor()

    cursor.execute("""
        INSERT INTO cp_user_merchant_history 
            (client_id, partner_id, visit_count, unique_promotions_used,
             first_visit, last_visit, days_since_last_visit,
             avg_days_between_visits, recency_score, frequency_score,
             created_at, updated_at)
        SELECT 
            h.client_id,
            pr.partner_id,
            COUNT(*) as visit_count,
            COUNT(DISTINCT h.promotion_id) as unique_promotions_used,
            MIN(h.time) as first_visit,
            MAX(h.time) as last_visit,
            DATEDIFF(NOW(), MAX(h.time)) as days_since_last_visit,
            CASE WHEN COUNT(*) > 1 
                 THEN DATEDIFF(MAX(h.time), MIN(h.time)) / (COUNT(*) - 1) 
                 ELSE 0 END as avg_days_between_visits,
            -- Recency: exponential decay (half-life 30 days)
            ROUND(EXP(-0.693 * DATEDIFF(NOW(), MAX(h.time)) / 30.0), 4),
            -- Frequency: normalized log
            ROUND(LOG(1 + COUNT(*)) / LOG(1 + 50), 4),
            NOW(), NOW()
        FROM history h
        JOIN promotion pr ON h.promotion_id = pr.promotion_id
        WHERE h.client_id IS NOT NULL
        GROUP BY h.client_id, pr.partner_id
        ON DUPLICATE KEY UPDATE
            visit_count = VALUES(visit_count),
            unique_promotions_used = VALUES(unique_promotions_used),
            first_visit = VALUES(first_visit),
            last_visit = VALUES(last_visit),
            days_since_last_visit = VALUES(days_since_last_visit),
            avg_days_between_visits = VALUES(avg_days_between_visits),
            recency_score = VALUES(recency_score),
            frequency_score = VALUES(frequency_score),
            updated_at = NOW()
    """)
    conn.commit()
    count = cursor.execute("SELECT COUNT(*) FROM cp_user_merchant_history")
    row = cursor.fetchone()
    print(f"   -> {row[0]} user-merchant pairs extracted")
    return row[0]


def extract_user_profiles(conn):
    """Populate cp_user_profile from aggregated history + client data."""
    print("[3/5] Extracting user profiles...")
    cursor = conn.cursor()

    cursor.execute("""
        INSERT INTO cp_user_profile
            (client_id, total_visits, unique_merchants_visited, unique_categories_visited,
             favorite_category_id, favorite_category_name, favorite_merchant_id,
             days_since_last_activity, avg_visits_per_merchant,
             category_diversity_score, loyalty_score, subscription_type,
             gender, age, sub_store_id, created_at, updated_at)
        SELECT
            umh.client_id,
            SUM(umh.visit_count) as total_visits,
            COUNT(DISTINCT umh.partner_id) as unique_merchants,
            COUNT(DISTINCT mc.category_id) as unique_categories,
            fav_cat.category_id,
            fav_cat.category_name,
            fav_merch.partner_id,
            MIN(umh.days_since_last_visit) as days_since_last,
            AVG(umh.visit_count) as avg_visits_per_merchant,
            -- Diversity: unique_categories / total_categories (11)
            ROUND(COUNT(DISTINCT mc.category_id) / 11.0, 4),
            -- Loyalty: weighted recency + frequency 
            ROUND(0.4 * MAX(umh.recency_score) + 0.6 * AVG(umh.frequency_score), 4),
            COALESCE(sub_info.subscription_type, 'none'),
            COALESCE(c.client_gender, 'M'),
            c.client_age,
            c.sub_store,
            NOW(), NOW()
        FROM cp_user_merchant_history umh
        JOIN cp_merchants_catalog mc ON umh.partner_id = mc.partner_id
        LEFT JOIN client c ON umh.client_id = c.client_id
        LEFT JOIN (
            SELECT ca.client_id, ca.subscription_type
            FROM client_abonnement ca
            INNER JOIN (
                SELECT client_id, MAX(client_abonnement_id) as max_id
                FROM client_abonnement GROUP BY client_id
            ) latest ON ca.client_abonnement_id = latest.max_id
        ) sub_info ON umh.client_id = sub_info.client_id
        LEFT JOIN (
            SELECT umh2.client_id, mc2.category_id, mc2.category_name,
                   ROW_NUMBER() OVER (PARTITION BY umh2.client_id ORDER BY SUM(umh2.visit_count) DESC) as rn
            FROM cp_user_merchant_history umh2
            JOIN cp_merchants_catalog mc2 ON umh2.partner_id = mc2.partner_id
            WHERE mc2.category_id IS NOT NULL
            GROUP BY umh2.client_id, mc2.category_id, mc2.category_name
        ) fav_cat ON umh.client_id = fav_cat.client_id AND fav_cat.rn = 1
        LEFT JOIN (
            SELECT client_id, partner_id,
                   ROW_NUMBER() OVER (PARTITION BY client_id ORDER BY visit_count DESC) as rn
            FROM cp_user_merchant_history
        ) fav_merch ON umh.client_id = fav_merch.client_id AND fav_merch.rn = 1
        GROUP BY umh.client_id, fav_cat.category_id, fav_cat.category_name,
                 fav_merch.partner_id, sub_info.subscription_type,
                 c.client_gender, c.client_age, c.sub_store
        ON DUPLICATE KEY UPDATE
            total_visits = VALUES(total_visits),
            unique_merchants_visited = VALUES(unique_merchants_visited),
            unique_categories_visited = VALUES(unique_categories_visited),
            favorite_category_id = VALUES(favorite_category_id),
            favorite_category_name = VALUES(favorite_category_name),
            favorite_merchant_id = VALUES(favorite_merchant_id),
            days_since_last_activity = VALUES(days_since_last_activity),
            avg_visits_per_merchant = VALUES(avg_visits_per_merchant),
            category_diversity_score = VALUES(category_diversity_score),
            loyalty_score = VALUES(loyalty_score),
            subscription_type = VALUES(subscription_type),
            gender = VALUES(gender),
            age = VALUES(age),
            sub_store_id = VALUES(sub_store_id),
            updated_at = NOW()
    """)
    conn.commit()
    count = cursor.execute("SELECT COUNT(*) FROM cp_user_profile")
    row = cursor.fetchone()
    print(f"   -> {row[0]} user profiles extracted")
    return row[0]


def build_training_data(conn):
    """Build training pairs for LightGBM Ranker: (user, merchant) with relevance labels."""
    print("[4/5] Building training data...")

    # Positive samples: actual user-merchant interactions
    query_positive = """
    SELECT 
        umh.client_id,
        umh.partner_id,
        umh.visit_count,
        umh.unique_promotions_used,
        umh.days_since_last_visit,
        umh.avg_days_between_visits,
        umh.recency_score,
        umh.frequency_score,
        up.total_visits as user_total_visits,
        up.unique_merchants_visited,
        up.unique_categories_visited,
        up.days_since_last_activity,
        up.avg_visits_per_merchant as user_avg_visits,
        up.category_diversity_score,
        up.loyalty_score,
        CASE up.subscription_type
            WHEN 'premium' THEN 3
            WHEN 'standard' THEN 2
            WHEN 'test' THEN 1
            ELSE 0
        END as subscription_tier,
        CASE up.gender WHEN 'F' THEN 1 ELSE 0 END as is_female,
        COALESCE(up.age, 30) as age,
        mc.active_promotion_count,
        mc.total_promotion_count,
        mc.avg_discount,
        mc.max_discount,
        mc.total_visits as merchant_total_visits,
        mc.unique_visitors as merchant_unique_visitors,
        mc.popularity_score,
        mc.avg_visits_per_user as merchant_avg_visits,
        mc.is_featured,
        mc.is_premium as merchant_is_premium,
        mc.location_count,
        -- Same category as favorite?
        IF(mc.category_id = up.favorite_category_id, 1, 0) as same_fav_category
    FROM cp_user_merchant_history umh
    JOIN cp_user_profile up ON umh.client_id = up.client_id
    JOIN cp_merchants_catalog mc ON umh.partner_id = mc.partner_id
    WHERE mc.is_active = 1
    """
    
    df_positive = pd.read_sql(query_positive, conn)
    
    # Relevance label: 0-4 based on visit_count
    df_positive['relevance'] = pd.cut(
        df_positive['visit_count'], 
        bins=[-1, 0, 1, 3, 7, float('inf')],
        labels=[0, 1, 2, 3, 4]
    ).astype(int)
    
    print(f"   -> {len(df_positive)} positive samples")

    # Negative samples: random merchants users haven't visited
    query_negative_candidates = """
    SELECT up.client_id, mc.partner_id
    FROM cp_user_profile up
    CROSS JOIN cp_merchants_catalog mc
    WHERE mc.is_active = 1
    AND NOT EXISTS (
        SELECT 1 FROM cp_user_merchant_history umh 
        WHERE umh.client_id = up.client_id AND umh.partner_id = mc.partner_id
    )
    ORDER BY RAND()
    LIMIT %s
    """
    # Sample ~2x negatives vs positives (capped at 200K)
    neg_count = min(len(df_positive) * 2, 200000)
    df_neg_pairs = pd.read_sql(query_negative_candidates, conn, params=[neg_count])
    
    if len(df_neg_pairs) > 0:
        query_neg_features = """
        SELECT 
            up.client_id,
            mc.partner_id,
            0 as visit_count,
            0 as unique_promotions_used,
            9999 as days_since_last_visit,
            0 as avg_days_between_visits,
            0 as recency_score,
            0 as frequency_score,
            up.total_visits as user_total_visits,
            up.unique_merchants_visited,
            up.unique_categories_visited,
            up.days_since_last_activity,
            up.avg_visits_per_merchant as user_avg_visits,
            up.category_diversity_score,
            up.loyalty_score,
            CASE up.subscription_type
                WHEN 'premium' THEN 3
                WHEN 'standard' THEN 2
                WHEN 'test' THEN 1
                ELSE 0
            END as subscription_tier,
            CASE up.gender WHEN 'F' THEN 1 ELSE 0 END as is_female,
            COALESCE(up.age, 30) as age,
            mc.active_promotion_count,
            mc.total_promotion_count,
            mc.avg_discount,
            mc.max_discount,
            mc.total_visits as merchant_total_visits,
            mc.unique_visitors as merchant_unique_visitors,
            mc.popularity_score,
            mc.avg_visits_per_user as merchant_avg_visits,
            mc.is_featured,
            mc.is_premium as merchant_is_premium,
            mc.location_count,
            IF(mc.category_id = up.favorite_category_id, 1, 0) as same_fav_category,
            0 as relevance
        FROM cp_user_profile up
        JOIN cp_merchants_catalog mc
        WHERE (up.client_id, mc.partner_id) IN ({})
        """.format(','.join([f"({r.client_id},{r.partner_id})" for _, r in df_neg_pairs.iterrows()]))
        
        # This query could be too long. Use a temp table approach instead.
        cursor = conn.cursor()
        cursor.execute("CREATE TEMPORARY TABLE _tmp_neg_pairs (client_id BIGINT, partner_id BIGINT)")
        
        # Batch insert
        batch_size = 5000
        for i in range(0, len(df_neg_pairs), batch_size):
            batch = df_neg_pairs.iloc[i:i+batch_size]
            values = ','.join([f"({r.client_id},{r.partner_id})" for _, r in batch.iterrows()])
            cursor.execute(f"INSERT INTO _tmp_neg_pairs VALUES {values}")
        conn.commit()
        
        df_negative = pd.read_sql("""
        SELECT 
            up.client_id,
            tnp.partner_id,
            0 as visit_count,
            0 as unique_promotions_used,
            9999 as days_since_last_visit,
            0 as avg_days_between_visits,
            0 as recency_score,
            0 as frequency_score,
            up.total_visits as user_total_visits,
            up.unique_merchants_visited,
            up.unique_categories_visited,
            up.days_since_last_activity,
            up.avg_visits_per_merchant as user_avg_visits,
            up.category_diversity_score,
            up.loyalty_score,
            CASE up.subscription_type
                WHEN 'premium' THEN 3
                WHEN 'standard' THEN 2
                WHEN 'test' THEN 1
                ELSE 0
            END as subscription_tier,
            CASE up.gender WHEN 'F' THEN 1 ELSE 0 END as is_female,
            COALESCE(up.age, 30) as age,
            mc.active_promotion_count,
            mc.total_promotion_count,
            mc.avg_discount,
            mc.max_discount,
            mc.total_visits as merchant_total_visits,
            mc.unique_visitors as merchant_unique_visitors,
            mc.popularity_score,
            mc.avg_visits_per_user as merchant_avg_visits,
            mc.is_featured,
            mc.is_premium as merchant_is_premium,
            mc.location_count,
            IF(mc.category_id = up.favorite_category_id, 1, 0) as same_fav_category,
            0 as relevance
        FROM _tmp_neg_pairs tnp
        JOIN cp_user_profile up ON tnp.client_id = up.client_id
        JOIN cp_merchants_catalog mc ON tnp.partner_id = mc.partner_id
        """, conn)
        
        cursor.execute("DROP TEMPORARY TABLE IF EXISTS _tmp_neg_pairs")
        print(f"   -> {len(df_negative)} negative samples")
        
        df_all = pd.concat([df_positive, df_negative], ignore_index=True)
    else:
        df_all = df_positive.copy()

    print(f"   -> Total training samples: {len(df_all)}")
    return df_all


def train_ranker(df):
    """Train LightGBM Ranker model."""
    print("[5/5] Training LightGBM Ranker...")
    from lightgbm import LGBMRanker
    from sklearn.model_selection import GroupShuffleSplit
    
    feature_cols = [
        'visit_count', 'unique_promotions_used', 'days_since_last_visit',
        'avg_days_between_visits', 'recency_score', 'frequency_score',
        'user_total_visits', 'unique_merchants_visited', 'unique_categories_visited',
        'days_since_last_activity', 'user_avg_visits', 'category_diversity_score',
        'loyalty_score', 'subscription_tier', 'is_female', 'age',
        'active_promotion_count', 'total_promotion_count', 'avg_discount',
        'max_discount', 'merchant_total_visits', 'merchant_unique_visitors',
        'popularity_score', 'merchant_avg_visits', 'is_featured',
        'merchant_is_premium', 'location_count', 'same_fav_category'
    ]
    
    X = df[feature_cols].fillna(0)
    y = df['relevance']
    groups = df['client_id']
    
    # Group-aware split: ensure all items for a user are in the same split
    gss = GroupShuffleSplit(n_splits=1, test_size=0.2, random_state=42)
    train_idx, test_idx = next(gss.split(X, y, groups))
    
    X_train, X_test = X.iloc[train_idx], X.iloc[test_idx]
    y_train, y_test = y.iloc[train_idx], y.iloc[test_idx]
    groups_train = groups.iloc[train_idx]
    groups_test = groups.iloc[test_idx]
    
    # Compute group sizes (number of merchants per user in training set)
    train_group_sizes = groups_train.value_counts().sort_index()
    # Reorder to match the group order in training data
    train_groups_ordered = groups_train.values
    seen = set()
    group_list_train = []
    for g in train_groups_ordered:
        if g not in seen:
            seen.add(g)
            group_list_train.append((groups_train == g).sum())
    
    test_groups_ordered = groups_test.values
    seen = set()
    group_list_test = []
    for g in test_groups_ordered:
        if g not in seen:
            seen.add(g)
            group_list_test.append((groups_test == g).sum())

    model = LGBMRanker(
        objective='lambdarank',
        metric='ndcg',
        n_estimators=200,
        learning_rate=0.05,
        num_leaves=31,
        max_depth=6,
        min_child_samples=20,
        subsample=0.8,
        colsample_bytree=0.8,
        random_state=42,
        verbose=-1
    )
    
    model.fit(
        X_train, y_train,
        group=group_list_train,
        eval_set=[(X_test, y_test)],
        eval_group=[group_list_test],
        eval_metric='ndcg',
        eval_at=[5, 10],
    )
    
    # Feature importances
    importances = dict(zip(feature_cols, model.feature_importances_.tolist()))
    sorted_importances = dict(sorted(importances.items(), key=lambda x: x[1], reverse=True))
    
    # Evaluate: NDCG@5 and NDCG@10 from best iteration
    best_score = model.best_score_
    eval_results = {}
    if best_score and 'valid_0' in best_score:
        eval_results = best_score['valid_0']
    
    # Save model
    joblib.dump({
        'model': model,
        'feature_cols': feature_cols,
        'trained_at': datetime.now().isoformat(),
        'n_samples': len(df),
        'n_features': len(feature_cols),
    }, MODEL_PATH)
    
    metrics = {
        'trained_at': datetime.now().isoformat(),
        'n_train_samples': len(X_train),
        'n_test_samples': len(X_test),
        'n_features': len(feature_cols),
        'n_groups_train': len(group_list_train),
        'n_groups_test': len(group_list_test),
        'eval_results': {k: float(v) for k, v in eval_results.items()},
        'feature_importances': {k: int(v) for k, v in sorted_importances.items()},
        'best_iteration': model.best_iteration_,
    }
    
    with open(METRICS_PATH, 'w') as f:
        json.dump(metrics, f, indent=2)
    
    print(f"   -> Model saved to {MODEL_PATH}")
    print(f"   -> Metrics: {json.dumps(eval_results, indent=2)}")
    print(f"   -> Top features: {list(sorted_importances.keys())[:5]}")
    
    return model, feature_cols, metrics


def generate_cold_start_recommendations(conn):
    """Pre-compute popularity-based recommendations for users without history."""
    print("Generating cold-start fallback (popularity-based)...")
    cursor = conn.cursor()
    
    cursor.execute("""
        SELECT partner_id, partner_name, category_name, popularity_score,
               active_promotion_count, avg_discount
        FROM cp_merchants_catalog
        WHERE is_active = 1 AND total_visits > 0
        ORDER BY popularity_score DESC
        LIMIT 50
    """)
    rows = cursor.fetchall()
    
    fallback = []
    for r in rows:
        fallback.append({
            'partner_id': r[0],
            'partner_name': r[1],
            'category_name': r[2],
            'popularity_score': float(r[3]),
            'active_promotions': r[4],
            'avg_discount': float(r[5]),
        })
    
    fallback_path = os.path.join(MODEL_DIR, 'merchant_fallback_popular.json')
    with open(fallback_path, 'w') as f:
        json.dump(fallback, f, indent=2)
    
    print(f"   -> {len(fallback)} popular merchants saved as fallback")
    return fallback


def main():
    start = time.time()
    print("=" * 60)
    print("MERCHANT RECOMMENDATION ENGINE - Training Pipeline")
    print("=" * 60)
    
    conn = get_db_connection()
    
    try:
        # Phase 1: Extract features into pre-computed tables
        n_merchants = extract_merchants_catalog(conn)
        n_pairs = extract_user_merchant_history(conn)
        n_profiles = extract_user_profiles(conn)
        
        # Phase 2: Build training data and train
        df = build_training_data(conn)
        
        if len(df) < 100:
            print("WARNING: Not enough training data. Using fallback only.")
            generate_cold_start_recommendations(conn)
            return
        
        model, feature_cols, metrics = train_ranker(df)
        
        # Phase 3: Cold-start fallback
        generate_cold_start_recommendations(conn)
        
        elapsed = time.time() - start
        print(f"\nPipeline complete in {elapsed:.1f}s")
        print(f"  Merchants: {n_merchants} | User-Merchant pairs: {n_pairs} | Profiles: {n_profiles}")
        print(f"  Training samples: {len(df)} | Model: {MODEL_PATH}")
        
    finally:
        conn.close()


if __name__ == '__main__':
    main()
