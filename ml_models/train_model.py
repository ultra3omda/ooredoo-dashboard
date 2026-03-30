#!/usr/bin/env python3
"""
Export training data from MySQL ml_client_features table and train LightGBM model.
"""
import os
import sys
import json
import pymysql
import pandas as pd
import numpy as np
from lightgbm import LGBMClassifier
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score, precision_score, recall_score, f1_score, roc_auc_score
import joblib

def get_db_connection():
    """Connect to MySQL using Laravel .env configuration."""
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
        database=config.get('DB_DATABASE', 'club_privileges'),
        charset='utf8mb4'
    )

def export_training_data():
    """Export a representative sample from ml_client_features for training."""
    print("Connecting to database...")
    conn = get_db_connection()
    
    # Get a balanced sample: clients with activity
    query = """
    SELECT 
        client_id,
        CAST(payment_success_rate AS DECIMAL(10,6)) as payment_success_rate,
        consecutive_failures,
        COALESCE(total_payments, 0) as total_payments,
        CAST(churn_probability AS DECIMAL(10,6)) as churn_probability,
        CAST(payment_reliability_score AS DECIMAL(10,6)) as payment_reliability_score,
        COALESCE(days_since_last_payment, 999) as days_since_last_payment,
        subscription_age_days,
        is_high_value_client,
        CAST(COALESCE(timwe_success_rate, 0) AS DECIMAL(10,6)) as timwe_success_rate,
        CAST(COALESCE(eklektik_success_rate, 0) AS DECIMAL(10,6)) as eklektik_success_rate,
        CAST(COALESCE(ooredoo_success_rate, 0) AS DECIMAL(10,6)) as ooredoo_success_rate,
        COALESCE(timwe_total_attempts, 0) as timwe_total_attempts,
        COALESCE(eklektik_total_attempts, 0) as eklektik_total_attempts,
        COALESCE(ooredoo_total_attempts, 0) as ooredoo_total_attempts,
        CAST(COALESCE(engagement_score, 0) AS DECIMAL(10,6)) as engagement_score,
        CAST(COALESCE(lifetime_value_score, 0) AS DECIMAL(10,6)) as lifetime_value_score,
        COALESCE(total_attempts, 0) as total_attempts,
        CAST(COALESCE(payment_frequency, 0) AS DECIMAL(10,6)) as payment_frequency,
        CASE 
            WHEN (timwe_success_rate > 0.3 OR eklektik_success_rate > 0.3 OR ooredoo_success_rate > 0.3) THEN 1
            WHEN (total_payments > 0 AND payment_success_rate > 0.15) THEN 1
            ELSE 0
        END as payment_success_label
    FROM ml_client_features 
    WHERE (timwe_total_attempts > 0 OR eklektik_total_attempts > 0 OR ooredoo_total_attempts > 0 OR total_attempts > 0)
    ORDER BY RAND()
    LIMIT 80000
    """
    
    print("Exporting training data (up to 50k samples)...")
    df = pd.read_sql(query, conn)
    conn.close()
    
    print(f"Exported {len(df)} samples")
    print(f"Label distribution:\n{df['payment_success_label'].value_counts()}")
    return df

def train_model(df):
    """Train LightGBM classifier on the exported data."""
    feature_columns = [
        'payment_success_rate', 'consecutive_failures', 'total_payments',
        'churn_probability', 'payment_reliability_score', 'days_since_last_payment',
        'subscription_age_days', 'is_high_value_client',
        'timwe_success_rate', 'eklektik_success_rate', 'ooredoo_success_rate',
        'timwe_total_attempts', 'eklektik_total_attempts', 'ooredoo_total_attempts',
        'engagement_score', 'lifetime_value_score', 'total_attempts', 'payment_frequency'
    ]
    
    X = df[feature_columns].astype(float).fillna(0)
    y = df['payment_success_label'].astype(int)
    
    print(f"\nTraining with {len(feature_columns)} features on {len(X)} samples...")
    print(f"Positive rate: {y.mean():.4f}")
    
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42, stratify=y)
    
    # Handle class imbalance
    pos_count = y_train.sum()
    neg_count = len(y_train) - pos_count
    scale_pos_weight = neg_count / max(pos_count, 1)
    
    model = LGBMClassifier(
        n_estimators=200,
        max_depth=6,
        learning_rate=0.05,
        num_leaves=31,
        min_child_samples=20,
        scale_pos_weight=scale_pos_weight,
        random_state=42,
        verbose=-1
    )
    
    model.fit(X_train, y_train)
    
    # Evaluate
    y_pred = model.predict(X_test)
    y_proba = model.predict_proba(X_test)[:, 1]
    
    metrics = {
        'accuracy': round(accuracy_score(y_test, y_pred) * 100, 2),
        'precision': round(precision_score(y_test, y_pred, zero_division=0) * 100, 2),
        'recall': round(recall_score(y_test, y_pred, zero_division=0) * 100, 2),
        'f1': round(f1_score(y_test, y_pred, zero_division=0) * 100, 2),
        'auc_roc': round(roc_auc_score(y_test, y_proba) * 100, 2) if len(np.unique(y_test)) > 1 else 0,
        'samples_train': len(X_train),
        'samples_test': len(X_test),
        'positive_rate': round(y.mean() * 100, 2)
    }
    
    print(f"\nModel Performance:")
    for k, v in metrics.items():
        print(f"  {k}: {v}")
    
    # Feature importance
    importance = dict(zip(feature_columns, model.feature_importances_.tolist()))
    sorted_imp = sorted(importance.items(), key=lambda x: x[1], reverse=True)
    print(f"\nTop 5 Feature Importance:")
    for name, imp in sorted_imp[:5]:
        print(f"  {name}: {imp}")
    
    # Save model
    model_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'billing_predictor_v3.pkl')
    model_data = {
        'model': model,
        'feature_columns': feature_columns,
        'feature_importance': importance,
        'metrics': metrics,
        'trained_at': pd.Timestamp.now().isoformat()
    }
    joblib.dump(model_data, model_path)
    print(f"\nModel saved to: {model_path}")
    
    # Also save metrics as JSON
    metrics_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'model_metrics.json')
    with open(metrics_path, 'w') as f:
        json.dump({**metrics, 'feature_importance': importance, 'trained_at': model_data['trained_at']}, f, indent=2)
    print(f"Metrics saved to: {metrics_path}")
    
    return metrics

if __name__ == '__main__':
    print("=" * 60)
    print("Club Privileges - ML Model Training (LightGBM)")
    print("=" * 60)
    
    df = export_training_data()
    if len(df) < 100:
        print("ERROR: Not enough training data (< 100 samples)")
        sys.exit(1)
    
    metrics = train_model(df)
    print("\n" + "=" * 60)
    print("Training complete!")
    print(json.dumps(metrics, indent=2))
