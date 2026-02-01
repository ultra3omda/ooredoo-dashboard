#!/usr/bin/env python3
"""
Prédiction du succès de facturation via le modèle LightGBM entraîné.
Usage: python predict.py --features '{"consecutive_failures": 2, ...}' [--model-path path/to/model.pkl]
Sortie JSON: {"probability": 0.xx, "confidence": 0.xx}
"""
import os
import sys
import json
import argparse

_project_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, _project_root)

import joblib
import numpy as np


def load_model(path):
    """Charge le modèle et les métadonnées."""
    if not os.path.isfile(path):
        raise FileNotFoundError(f"Modèle non trouvé: {path}")
    return joblib.load(path)


def predict(features_dict, model_path=None):
    """Prédit la probabilité de succès à partir des features (dict)."""
    if model_path is None:
        model_path = os.path.join(os.path.dirname(__file__), 'billing_predictor_v3.pkl')
    data = load_model(model_path)
    model = data['model']
    feature_columns = data.get('feature_columns', [])
    if not feature_columns:
        feature_columns = data.get('feature_importance', {}).keys()
    feature_columns = list(feature_columns)
    # Construire le vecteur dans le même ordre que l'entraînement
    X = np.array([[float(features_dict.get(c, 0)) for c in feature_columns]], dtype=np.float64)
    # Remplacer NaN par 0
    X = np.nan_to_num(X, nan=0.0, posinf=0.0, neginf=0.0)
    proba = model.predict_proba(X)[0, 1]
    # Confiance basée sur la cohérence des features (nombre de non-nuls)
    non_null = sum(1 for c in feature_columns if features_dict.get(c) is not None and features_dict.get(c) != '')
    confidence = min(0.95, 0.3 + (non_null / max(len(feature_columns), 1)) * 0.65)
    return {
        'probability': round(float(proba), 4),
        'confidence': round(float(confidence), 4)
    }


def main():
    parser = argparse.ArgumentParser(description='Prédiction succès facturation')
    parser.add_argument('--features', type=str, required=True, help='JSON des features du client')
    parser.add_argument('--model-path', type=str, default=None, help='Chemin vers le fichier .pkl')
    args = parser.parse_args()
    try:
        features = json.loads(args.features)
    except json.JSONDecodeError as e:
        print(json.dumps({'error': f'JSON invalide: {e}'}), file=sys.stderr)
        sys.exit(1)
    try:
        if args.model_path:
            result = predict(features, args.model_path)
        else:
            result = predict(features)
        print(json.dumps(result))
    except FileNotFoundError as e:
        print(json.dumps({'error': str(e)}), file=sys.stderr)
        sys.exit(1)
    except Exception as e:
        print(json.dumps({'error': str(e)}), file=sys.stderr)
        sys.exit(1)


if __name__ == '__main__':
    main()
