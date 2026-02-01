#!/usr/bin/env python3
"""
Entraînement du modèle LightGBM pour prédiction du succès de facturation.
Charge les données depuis ml_client_features, entraîne, évalue et sauvegarde.
Usage: python train_model.py (depuis la racine du projet ou ml_models/)
"""
import os
import sys
import json
from datetime import datetime

# Charger .env depuis la racine du projet Laravel si présent
_project_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
_env_path = os.path.join(_project_root, '.env')
if os.path.isfile(_env_path):
    try:
        from dotenv import load_dotenv
        load_dotenv(_env_path)
    except ImportError:
        pass

import pandas as pd
import numpy as np
from lightgbm import LGBMClassifier
from sklearn.model_selection import train_test_split
from sklearn.metrics import roc_auc_score, classification_report, accuracy_score, precision_score, recall_score, f1_score
import joblib
import mysql.connector


class BillingSuccessPredictor:
    """Modèle ML pour prédire le succès de facturation."""

    FEATURE_COLUMNS = [
        'consecutive_failures', 'total_payments', 'total_attempts',
        'payment_frequency', 'avg_payment_amount', 'days_since_last_payment',
        'best_billing_day_week', 'best_billing_hour',
        'end_month_success_rate', 'beginning_month_success_rate',
        'subscription_age_days', 'churn_probability', 'failure_streak',
        'is_high_value_client', 'payment_reliability_score',
        'engagement_score', 'lifetime_value_score',
        'morning_success_rate', 'afternoon_success_rate', 'evening_success_rate',
        'recovery_after_failure_rate', 'max_consecutive_successes',
        'payment_amount_std', 'amount_flexibility',
        'no_balance_failure_rate', 'not_delivered_failure_rate'
    ]

    def __init__(self):
        self.model = None
        self.feature_columns = []
        self.feature_importance = {}

    def _get_db_connection(self):
        return mysql.connector.connect(
            host=os.getenv('DB_HOST', '127.0.0.1'),
            port=int(os.getenv('DB_PORT', 3306)),
            user=os.getenv('DB_USERNAME', 'root'),
            password=os.getenv('DB_PASSWORD', ''),
            database=os.getenv('DB_DATABASE', 'clubprivileges')
        )

    def load_data_from_db(self):
        """Charge les données depuis ml_client_features (derniers 90 jours)."""
        conn = self._get_db_connection()
        cols = ', '.join(['payment_success_rate'] + [c for c in self.FEATURE_COLUMNS if c != 'payment_success_rate'])
        query = f"""
        SELECT client_id, {cols}
        FROM ml_client_features
        WHERE calculation_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        AND calculation_date = (SELECT MAX(calculation_date) FROM ml_client_features m2 WHERE m2.client_id = ml_client_features.client_id)
        """
        df = pd.read_sql(query, conn)
        conn.close()
        return df

    def prepare_features(self, df):
        """Prépare les features et la cible binaire (succès si payment_success_rate > 0.3)."""
        df = df.copy()
        df['target_success'] = (df['payment_success_rate'] > 0.3).astype(int)
        available = [c for c in self.FEATURE_COLUMNS if c in df.columns]
        self.feature_columns = available
        for col in available:
            df[col] = pd.to_numeric(df[col], errors='coerce').fillna(0)
        X = df[available]
        y = df['target_success']
        return X, y

    def train(self, X, y):
        """Entraîne le modèle LightGBM avec early stopping."""
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=0.2, random_state=42, stratify=y
        )
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
        try:
            import lightgbm as lgb
            self.model.fit(
                X_train, y_train,
                eval_set=[(X_test, y_test)],
                callbacks=[lgb.early_stopping(50, verbose=False)]
            )
        except (TypeError, AttributeError):
            self.model.fit(X_train, y_train)
        y_pred = self.model.predict(X_test)
        y_pred_proba = self.model.predict_proba(X_test)[:, 1]
        auc = roc_auc_score(y_test, y_pred_proba)
        self.feature_importance = dict(zip(
            self.feature_columns,
            [float(x) for x in self.model.feature_importances_]
        ))
        self._save_performance_to_db(auc, y_test, y_pred, y_pred_proba)
        print(f"\n✅ Modèle entraîné | AUC-ROC: {auc:.4f}")
        print(classification_report(y_test, y_pred))
        return auc

    def _save_performance_to_db(self, auc, y_test, y_pred, y_pred_proba):
        """Enregistre les métriques dans ml_model_performance (colonnes base + optionnelles)."""
        from sklearn.metrics import accuracy_score, precision_score, recall_score, f1_score
        conn = self._get_db_connection()
        cursor = conn.cursor()
        today = datetime.now().date().isoformat()
        accuracy = float(accuracy_score(y_test, y_pred))
        precision = float(precision_score(y_test, y_pred, zero_division=0))
        recall = float(recall_score(y_test, y_pred, zero_division=0))
        f1 = float(f1_score(y_test, y_pred, zero_division=0))
        correct = int((y_pred == y_test).sum())
        try:
            cursor.execute("""
                INSERT INTO ml_model_performance (
                    model_name, model_version, evaluation_date,
                    accuracy, precision, recall, f1_score, auc_roc,
                    total_predictions, correct_predictions,
                    test_period_start, test_period_end, test_sample_size
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """, (
                'lightgbm_billing_predictor',
                'v3.0_optimized',
                today,
                accuracy, precision, recall, f1, float(auc),
                len(y_test), correct,
                today, today, len(y_test)
            ))
            conn.commit()
            row_id = cursor.lastrowid
            # Optionnel: mettre à jour training_params et feature_importance si colonnes existent
            try:
                training_params = self.model.get_params() if hasattr(self.model, 'get_params') else {}
                cursor.execute("""
                    UPDATE ml_model_performance
                    SET training_params = %s, feature_importance = %s
                    WHERE id = %s
                """, (json.dumps(training_params), json.dumps(self.feature_importance), row_id))
                conn.commit()
            except mysql.connector.Error:
                pass
        except mysql.connector.Error as e:
            print(f"⚠️ Impossible d'écrire dans ml_model_performance: {e}")
        finally:
            cursor.close()
            conn.close()

    def save_model(self, path=None):
        """Sauvegarde le modèle et les métadonnées en .pkl."""
        if path is None:
            path = os.path.join(os.path.dirname(__file__), 'billing_predictor_v3.pkl')
        model_data = {
            'model': self.model,
            'feature_columns': self.feature_columns,
            'feature_importance': self.feature_importance,
            'version': 'v3.0',
            'trained_at': datetime.now().isoformat()
        }
        joblib.dump(model_data, path)
        print(f"💾 Modèle sauvegardé: {path}")


def main():
    print("🤖 Entraînement du modèle ML - Prédiction facturation\n")
    predictor = BillingSuccessPredictor()
    print("📥 Chargement des données...")
    df = predictor.load_data_from_db()
    if df.empty or len(df) < 100:
        print("❌ Pas assez de données (min 100 enregistrements).")
        sys.exit(1)
    print(f"✅ {len(df)} enregistrements chargés\n")
    print("🔧 Préparation des features...")
    X, y = predictor.prepare_features(df)
    print(f"✅ {X.shape[1]} features | Cible: {y.value_counts().to_dict()}\n")
    auc = predictor.train(X, y)
    predictor.save_model()
    print(f"\n🎉 SUCCÈS | Modèle AUC={auc:.4f} sauvegardé.")


if __name__ == '__main__':
    main()
