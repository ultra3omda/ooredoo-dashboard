#!/usr/bin/env python3
"""
Prediction du succes de facturation via le modele LightGBM entraine.
Usage: python3 predict.py '{"client_id": 123, "features": {...}}'
       python3 predict.py --batch (predicts top 50 clients)
"""
import os
import sys
import json
import joblib
import numpy as np

MODEL_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'billing_predictor_v3.pkl')

def load_model():
    if not os.path.exists(MODEL_PATH):
        print(json.dumps({'error': 'Model not found. Run train_model.py first.'}))
        sys.exit(1)
    return joblib.load(MODEL_PATH)

def predict_single(model_data, features_dict):
    model = model_data['model']
    feature_columns = model_data['feature_columns']
    X = np.array([[float(features_dict.get(col, 0)) for col in feature_columns]])
    proba = model.predict_proba(X)[0]
    return {
        'payment_success_probability': round(float(proba[1]), 4),
        'payment_failure_probability': round(float(proba[0]), 4),
        'predicted_class': int(model.predict(X)[0]),
        'confidence': round(float(max(proba)), 4)
    }

def predict_batch():
    """Predict for top clients from DB."""
    import pymysql
    env_path = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), '.env')
    config = {}
    with open(env_path) as f:
        for line in f:
            line = line.strip()
            if '=' in line and not line.startswith('#'):
                key, val = line.split('=', 1)
                config[key] = val
    
    conn = pymysql.connect(
        host=config.get('DB_HOST', '127.0.0.1'),
        port=int(config.get('DB_PORT', 3306)),
        user=config.get('DB_USERNAME', 'root'),
        password=config.get('DB_PASSWORD', ''),
        database=config.get('DB_DATABASE', 'club_privileges'),
        charset='utf8mb4'
    )
    
    model_data = load_model()
    feature_columns = model_data['feature_columns']
    
    cols_sql = ', '.join([f'COALESCE({c}, 0) as {c}' for c in feature_columns])
    query = f"""
    SELECT client_id, {cols_sql}
    FROM ml_client_features 
    WHERE (timwe_total_attempts > 0 OR eklektik_total_attempts > 0 OR ooredoo_total_attempts > 0)
    ORDER BY RAND()
    LIMIT 50
    """
    
    import pandas as pd
    df = pd.read_sql(query, conn)
    conn.close()
    
    if len(df) == 0:
        return []
    
    X = df[feature_columns].astype(float).fillna(0).values
    model = model_data['model']
    probas = model.predict_proba(X)
    
    results = []
    for i, row in df.iterrows():
        results.append({
            'client_id': int(row['client_id']),
            'payment_success_probability': round(float(probas[i][1]), 4),
            'confidence': round(float(max(probas[i])), 4),
            'predicted_class': int(model.predict(X[i:i+1])[0])
        })
    
    return results

if __name__ == '__main__':
    if len(sys.argv) > 1 and sys.argv[1] == '--batch':
        results = predict_batch()
        print(json.dumps({'success': True, 'predictions': results, 'count': len(results)}))
    elif len(sys.argv) > 1:
        try:
            input_data = json.loads(sys.argv[1])
            features = input_data.get('features', input_data)
            model_data = load_model()
            result = predict_single(model_data, features)
            print(json.dumps({'success': True, **result}))
        except Exception as e:
            print(json.dumps({'success': False, 'error': str(e)}))
            sys.exit(1)
    else:
        print(json.dumps({'error': 'Usage: predict.py <json_features> or predict.py --batch'}))
        sys.exit(1)
