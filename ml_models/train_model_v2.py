#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Entraînement du modèle LightGBM v2 - SANS DATA LEAKAGE
Prédiction time-series: Features au temps T → Label au temps T+30j
Usage: python train_model_v2.py --data=storage/ml_training_samples.csv
"""
import os
import sys
import io

# Éviter UnicodeEncodeError sur Windows (console cp1252)
if sys.platform == 'win32' and hasattr(sys.stdout, 'buffer'):
    try:
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
    except Exception:
        pass

import json
import warnings
import argparse
from datetime import datetime

import pandas as pd
import numpy as np
from lightgbm import LGBMClassifier
from sklearn.model_selection import train_test_split
from sklearn.metrics import (
    roc_auc_score, classification_report, 
    accuracy_score, precision_score, recall_score, f1_score
)
import joblib


class BillingSuccessPredictorV2:
    """
    Modèle ML v2 - Prédiction time-series sans data leakage.
    Features = comportement PASSÉ (J-90 à J)
    Label = succès FUTUR (J à J+30)
    """

    # Features utilisées (TOUTES historiques, ne révèlent PAS le futur)
    FEATURE_COLUMNS = [
        # Timwe - Historique
        'timwe_past_attempts',
        'timwe_past_successes',
        'timwe_past_failures',
        'timwe_past_avg_success_rate',
        'timwe_days_since_last_success',
        
        # Eklektik - Historique
        'eklektik_past_attempts',
        'eklektik_past_successes',
        'eklektik_past_failures',
        'eklektik_past_avg_success_rate',
        'eklektik_days_since_last_success',
        
        # Ooredoo - Historique
        'ooredoo_past_attempts',
        'ooredoo_past_successes',
        'ooredoo_past_failures',
        'ooredoo_past_avg_success_rate',
        'ooredoo_days_since_last_success',
        
        # Métriques générales
        'total_past_attempts',
        'total_past_successes',
        'total_past_revenue',
        'consecutive_failures_before',
        'days_since_any_success',
        
        # Patterns et tendances
        'operators_used_count',
        'dominant_operator',  # déjà encodé en numérique
        'engagement_trend',    # déjà encodé en numérique
        'had_recent_activity_7d',
        'had_recent_success_7d',
    ]

    TARGET_COLUMN = 'had_success_next_30d'

    def __init__(self):
        self.model = None
        self.feature_columns = []
        self.feature_importance = {}

    def load_data(self, csv_path):
        """Charge les données depuis le CSV exporté."""
        print(f"📂 Chargement depuis : {csv_path}")
        
        if not os.path.isfile(csv_path):
            raise FileNotFoundError(f"Fichier introuvable : {csv_path}")
        
        df = pd.read_csv(csv_path)
        print(f"✅ {len(df)} samples chargés")
        
        # Vérifier que les colonnes nécessaires existent
        missing = set(self.FEATURE_COLUMNS + [self.TARGET_COLUMN]) - set(df.columns)
        if missing:
            raise ValueError(f"Colonnes manquantes dans le CSV : {missing}")
        
        return df

    def prepare_features(self, df):
        """Prépare X (features) et y (target)."""
        df = df.copy()
        
        # Gérer les valeurs manquantes
        for col in self.FEATURE_COLUMNS:
            if col in df.columns:
                df[col] = pd.to_numeric(df[col], errors='coerce').fillna(0)
        
        # Extraire features et target
        X = df[self.FEATURE_COLUMNS]
        y = df[self.TARGET_COLUMN].astype(int)
        
        self.feature_columns = self.FEATURE_COLUMNS
        
        return X, y

    def train(self, X, y, test_size=0.2):
        """Entraîne le modèle LightGBM avec early stopping."""
        print("\n🎓 Entraînement du modèle...")
        print(f"   • Features : {X.shape[1]}")
        print(f"   • Samples : {len(X)}")
        print(f"   • Classe 0 (échec) : {(y == 0).sum()}")
        print(f"   • Classe 1 (succès) : {(y == 1).sum()}")
        
        # Split train/test avec stratification
        stratify = y if y.nunique() > 1 else None
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=test_size, random_state=42, stratify=stratify
        )
        
        # Configuration du modèle
        self.model = LGBMClassifier(
            n_estimators=500,
            learning_rate=0.05,
            max_depth=8,
            num_leaves=31,
            min_child_samples=20,
            subsample=0.8,
            colsample_bytree=0.8,
            reg_alpha=0.1,
            reg_lambda=0.1,
            random_state=42,
            class_weight='balanced',
            n_jobs=-1,
            verbosity=-1
        )
        
        # Entraînement avec early stopping
        try:
            import lightgbm as lgb
            self.model.fit(
                X_train, y_train,
                eval_set=[(X_test, y_test)],
                callbacks=[lgb.early_stopping(50, verbose=False)]
            )
            print(f"✅ Entraînement terminé (early stopping à {self.model.best_iteration_} itérations)")
        except (TypeError, AttributeError):
            self.model.fit(X_train, y_train)
            print("✅ Entraînement terminé")
        
        # Évaluation
        y_pred = self.model.predict(X_test)
        y_pred_proba = self.model.predict_proba(X_test)[:, 1]
        
        # Métriques
        if y_test.nunique() < 2:
            print("⚠️  Une seule classe dans y_test, AUC non défini")
            auc = float('nan')
        else:
            auc = roc_auc_score(y_test, y_pred_proba)
        
        accuracy = accuracy_score(y_test, y_pred)
        precision = precision_score(y_test, y_pred, zero_division=0)
        recall = recall_score(y_test, y_pred, zero_division=0)
        f1 = f1_score(y_test, y_pred, zero_division=0)
        
        # Feature importance
        self.feature_importance = dict(zip(
            self.feature_columns,
            [float(x) for x in self.model.feature_importances_]
        ))
        
        # Affichage
        print("\n" + "="*60)
        print("📊 RÉSULTATS DE L'ENTRAÎNEMENT")
        print("="*60)
        print(f"AUC-ROC      : {auc:.4f}" if not np.isnan(auc) else "AUC-ROC      : N/A")
        print(f"Accuracy     : {accuracy:.4f}")
        print(f"Precision    : {precision:.4f}")
        print(f"Recall       : {recall:.4f}")
        print(f"F1-Score     : {f1:.4f}")
        print("\n" + classification_report(y_test, y_pred, target_names=['Échec', 'Succès']))
        
        # Top 10 features
        print("\n🔝 Top 10 Features les plus importantes :")
        sorted_features = sorted(self.feature_importance.items(), key=lambda x: x[1], reverse=True)
        for i, (feat, score) in enumerate(sorted_features[:10], 1):
            print(f"   {i:2d}. {feat:40s} : {score:.4f}")
        
        print("="*60)
        
        return {
            'auc': auc,
            'accuracy': accuracy,
            'precision': precision,
            'recall': recall,
            'f1': f1,
            'y_test': y_test,
            'y_pred': y_pred,
            'y_pred_proba': y_pred_proba
        }

    def save_model(self, path=None):
        """Sauvegarde le modèle et les métadonnées."""
        if path is None:
            path = os.path.join(os.path.dirname(__file__), 'billing_predictor_v2.pkl')
        
        model_data = {
            'model': self.model,
            'feature_columns': self.feature_columns,
            'feature_importance': self.feature_importance,
            'version': 'v2.0_no_leakage',
            'trained_at': datetime.now().isoformat()
        }
        
        joblib.dump(model_data, path)
        print(f"\n💾 Modèle sauvegardé : {path}")


def main():
    parser = argparse.ArgumentParser(
        description='Entraîner le modèle ML v2 (sans data leakage)'
    )
    parser.add_argument(
        '--data', 
        type=str, 
        required=True,
        help='Fichier CSV avec les samples (sortie de ml:export-training-samples)'
    )
    parser.add_argument(
        '--test-size',
        type=float,
        default=0.2,
        help='Proportion du test set (défaut: 0.2)'
    )
    parser.add_argument(
        '--output',
        type=str,
        help='Chemin du modèle .pkl (défaut: ml_models/billing_predictor_v2.pkl)'
    )
    
    args = parser.parse_args()
    
    print("\n" + "="*60)
    print("🚀 ENTRAÎNEMENT MODÈLE ML v2 - Sans Data Leakage")
    print("="*60 + "\n")
    
    # Initialiser le predictor
    predictor = BillingSuccessPredictorV2()
    
    # Charger les données
    df = predictor.load_data(args.data)
    
    if len(df) < 100:
        print("❌ Pas assez de données (minimum 100 samples)")
        sys.exit(1)
    
    # Préparer features
    print("\n🔧 Préparation des features...")
    X, y = predictor.prepare_features(df)
    
    # Entraîner
    results = predictor.train(X, y, test_size=args.test_size)
    
    # Sauvegarder
    predictor.save_model(args.output)
    
    print("\n✅ ENTRAÎNEMENT TERMINÉ AVEC SUCCÈS !")
    
    # Validation finale
    auc = results['auc']
    if not np.isnan(auc):
        if auc > 0.95:
            print("\n⚠️  ATTENTION : AUC > 0.95 - Vérifiez qu'il n'y a pas de data leakage !")
        elif auc > 0.75:
            print("\n🎉 Excellent modèle (AUC > 0.75)")
        elif auc > 0.65:
            print("\n👍 Bon modèle (AUC > 0.65)")
        else:
            print("\n⚠️  Modèle faible (AUC < 0.65) - Envisagez plus de features ou de données")
    
    print()


if __name__ == '__main__':
    main()
