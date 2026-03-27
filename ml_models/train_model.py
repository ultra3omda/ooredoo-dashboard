#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Entraînement du modèle LightGBM pour prédiction du succès de facturation.
Charge les données depuis ml_client_features, entraîne, évalue et sauvegarde.
Usage: python train_model.py (depuis la racine du projet ou ml_models/)
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
    """Modèle ML pour prédire le succès de facturation (multi-opérateur: Timwe, Eklektik, Ooredoo)."""

    # Colonnes remplies par ml:extract-multi (multi-opérateur)
    FEATURE_COLUMNS = [
        'timwe_success_rate', 'timwe_total_attempts', 'timwe_total_successes',
        'timwe_avg_revenue_per_success', 'timwe_no_balance_rate', 'timwe_not_delivered_rate',
        'timwe_has_activity',
        'eklektik_success_rate', 'eklektik_total_attempts', 'eklektik_total_subscriptions',
        'eklektik_avg_daily_successes', 'eklektik_daily_consistency', 'eklektik_has_activity',
        'ooredoo_success_rate', 'ooredoo_total_attempts', 'ooredoo_total_subscriptions',
        'ooredoo_avg_monthly_successes', 'ooredoo_monthly_consistency', 'ooredoo_has_activity',
        'total_operators_used', 'operator_diversity_score',
        'unique_price_points', 'prefers_low_price', 'prefers_high_price', 'is_multi_operator_user',
        'daily_offers_count', 'monthly_offers_count', 'total_offers_count',
        'daily_engagement_rate', 'monthly_engagement_rate',
        'prefers_daily_offers', 'prefers_monthly_offers', 'is_frequency_flexible',
    ]
    # Colonnes catégorielles encodées en numérique dans prepare_features
    CAT_COLUMNS = ['price_preference', 'preferred_frequency', 'best_performing_operator']

    # À NE PAS utiliser comme features : elles définissent directement la cible (data leakage).
    # La cible = (timwe_success_rate > 0.2 et timwe_has_activity) ou idem eklektik/ooredoo.
    LEAKING_COLUMNS = [
        'timwe_success_rate', 'timwe_has_activity',
        'eklektik_success_rate', 'eklektik_has_activity',
        'ooredoo_success_rate', 'ooredoo_has_activity',
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

    # Limite optionnelle pour accélérer l'entraînement (None = pas de limite). 300k suffit pour un modèle stable.
    MAX_TRAINING_ROWS = int(os.getenv('ML_MAX_TRAINING_ROWS', '300000'))

    def load_data_from_db(self):
        """Charge les données depuis ml_client_features: 1 ligne par client (dernière date).
        Sans filtre de date: on prend la dernière snapshot de chaque client pour maximiser les données."""
        conn = self._get_db_connection()
        all_cols = self.FEATURE_COLUMNS + [c for c in self.CAT_COLUMNS if c not in self.FEATURE_COLUMNS]
        cols_list = ['m.' + c for c in all_cols]
        cols_select = 'm.client_id, ' + ', '.join(cols_list)
        # 1 ligne par client = dernière calculation_date (tous les clients, pas seulement 90 j)
        query = f"""
        SELECT {cols_select}
        FROM ml_client_features m
        INNER JOIN (
            SELECT client_id, MAX(calculation_date) AS max_date
            FROM ml_client_features
            GROUP BY client_id
        ) t ON m.client_id = t.client_id AND m.calculation_date = t.max_date
        """
        with warnings.catch_warnings():
            warnings.simplefilter("ignore", UserWarning)
            df = pd.read_sql(query, conn)
        conn.close()
        # Échantillonnage pour rester sous le timeout tout en gardant assez de données
        if self.MAX_TRAINING_ROWS and len(df) > self.MAX_TRAINING_ROWS:
            df = df.sample(n=self.MAX_TRAINING_ROWS, random_state=42)
            print(f"[INFO] Echantillon de {self.MAX_TRAINING_ROWS} lignes (total disponible > limite)")
        return df

    def _build_target_multi_operator(self, df):
        """Cible binaire: au moins un opérateur avec succès (taux > 0.2 et activité)."""
        def _safe_series(df, col, default=0):
            if col not in df.columns:
                return pd.Series(default, index=df.index)
            return pd.to_numeric(df[col], errors='coerce').fillna(default)
        t = _safe_series(df, 'timwe_success_rate')
        e = _safe_series(df, 'eklektik_success_rate')
        o = _safe_series(df, 'ooredoo_success_rate')
        ht = _safe_series(df, 'timwe_has_activity').astype(int) == 1
        he = _safe_series(df, 'eklektik_has_activity').astype(int) == 1
        ho = _safe_series(df, 'ooredoo_has_activity').astype(int) == 1
        has_t = ht & (t > 0.2)
        has_e = he & (e > 0.2)
        has_o = ho & (o > 0.2)
        return (has_t | has_e | has_o).astype(int)

    def prepare_features(self, df):
        """Prépare les features (multi-opérateur) et la cible: au moins un opérateur avec succès."""
        df = df.copy()
        df['target_success'] = self._build_target_multi_operator(df)
        # Colonnes numériques
        numeric_candidates = [c for c in self.FEATURE_COLUMNS if c in df.columns]
        for col in numeric_candidates:
            df[col] = pd.to_numeric(df[col], errors='coerce').fillna(0)
        # Encodage des catégorielles (si présentes)
        for col in self.CAT_COLUMNS:
            if col not in df.columns:
                continue
            s = df[col].fillna('').astype(str).str.strip().str.lower()
            if col == 'price_preference':
                df[col] = s.map({'low': 0, 'high': 1, 'mixed': 2, 'unknown': -1}).fillna(-1)
            elif col == 'preferred_frequency':
                df[col] = s.map({'daily': 0, 'monthly': 1, 'mixed': 2, 'unknown': -1}).fillna(-1)
            elif col == 'best_performing_operator':
                df[col] = s.map({'none': 0, 'timwe': 1, 'eklektik': 2, 'ooredoo': 3}).fillna(0)
            df[col] = pd.to_numeric(df[col], errors='coerce').fillna(-1)
        all_candidates = [c for c in self.FEATURE_COLUMNS + self.CAT_COLUMNS if c in df.columns]
        # Exclure les colonnes qui définissent la cible (éviter data leakage → AUC 1.0 artificiel)
        self.feature_columns = [c for c in all_candidates if c not in self.LEAKING_COLUMNS]
        X = df[self.feature_columns]
        y = df['target_success']
        return X, y

    def train(self, X, y):
        """Entraîne le modèle LightGBM avec early stopping."""
        stratify = y if y.nunique() > 1 else None
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=0.2, random_state=42, stratify=stratify
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
        if y_test.nunique() < 2:
            auc = float('nan')
            print("[ATTENTION] Cible a une seule classe (0 ou 1). AUC non defini. Verifiez les donnees ou le critere de la cible.")
        else:
            auc = roc_auc_score(y_test, y_pred_proba)
        self.feature_importance = dict(zip(
            self.feature_columns,
            [float(x) for x in self.model.feature_importances_]
        ))
        self._save_performance_to_db(auc, y_test, y_pred, y_pred_proba)
        auc_str = f"{auc:.4f}" if not np.isnan(auc) else "nan"
        print(f"\n[OK] Modele entraine | AUC-ROC: {auc_str}")
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
            # precision et recall sont des mots réservés MySQL -> backticks
            cursor.execute("""
                INSERT INTO ml_model_performance (
                    model_name, model_version, evaluation_date,
                    accuracy, `precision`, `recall`, f1_score, auc_roc,
                    total_predictions, correct_predictions,
                    test_period_start, test_period_end, test_sample_size
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """, (
                'lightgbm_billing_predictor',
                'v3.0_optimized',
                today,
                accuracy, precision, recall, f1, 0.0 if np.isnan(auc) else float(auc),  # colonne NOT NULL: 0 si AUC non defini
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
            print(f"[ATTENTION] Impossible d'ecrire dans ml_model_performance: {e}")
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
        print(f"[OK] Modele sauvegarde: {path}")


def main():
    import argparse
    parser = argparse.ArgumentParser(description='Entraîner le modèle ML de prédiction de facturation')
    parser.add_argument('--data', type=str, help='Fichier CSV avec les données (sinon charge depuis DB)')
    parser.add_argument('--limit', type=int, help='Limiter le nombre de lignes (pour tests rapides)')
    args = parser.parse_args()
    
    print("Entrainement du modele ML - Prediction facturation\n")
    predictor = BillingSuccessPredictor()
    
    # Charger depuis CSV ou DB
    if args.data:
        print(f"Chargement depuis CSV: {args.data}")
        if not os.path.isfile(args.data):
            print(f"[ERREUR] Fichier introuvable: {args.data}")
            sys.exit(1)
        df = pd.read_csv(args.data)
        print(f"[OK] {len(df)} enregistrements charges depuis CSV\n")
        
        # Limiter si demandé
        if args.limit and len(df) > args.limit:
            df = df.sample(n=args.limit, random_state=42)
            print(f"[INFO] Echantillon limite a {args.limit} lignes\n")
    else:
        print("Chargement des donnees depuis DB...")
        df = predictor.load_data_from_db()
        print(f"[OK] {len(df)} enregistrements charges depuis DB\n")
    
    if df.empty or len(df) < 100:
        print("[ERREUR] Pas assez de donnees (min 100 enregistrements).")
        sys.exit(1)
    
    print("Preparation des features...")
    X, y = predictor.prepare_features(df)
    print(f"[OK] {X.shape[1]} features | Cible: {y.value_counts().to_dict()}\n")
    auc = predictor.train(X, y)
    predictor.save_model()
    auc_final = f"{auc:.4f}" if not np.isnan(auc) else "nan"
    print(f"\n[OK] SUCCES | Modele AUC={auc_final} sauvegarde.")


if __name__ == '__main__':
    main()
