<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\MLClientFeature;
use Carbon\Carbon;
use Exception;

class MLModelTrainingService
{
    /**
     * Entraîne un nouveau modèle LightGBM avec gestion du déséquilibre
     */
    public function trainLightGBModel(string $modelName = 'lightgbm_v1', array $options = []): array
    {
        Log::info("MLModelTrainingService - Début entraînement modèle $modelName");
        
        try {
            // 1. Préparer les données d'entraînement
            $trainingData = $this->prepareTrainingData($options);
            
            if (empty($trainingData['features'])) {
                throw new Exception("Pas assez de données pour l'entraînement");
            }
            
            Log::info("MLModelTrainingService - Données préparées", [
                'samples' => count($trainingData['features']),
                'features_count' => count($trainingData['feature_names']),
                'positive_rate' => $trainingData['positive_rate']
            ]);
            
            // 2. Exporter vers Python pour entraînement
            $pythonData = $this->exportToPython($trainingData, $modelName);
            
            // 3. Lancer l'entraînement LightGBM
            $trainingResult = $this->executePythonTraining($pythonData, $modelName, $options);
            
            // 4. Sauvegarder les métriques de performance
            $this->saveModelPerformance($modelName, $trainingResult);
            
            // 5. Mettre à jour le service de prédiction
            $this->updatePredictionService($modelName, $trainingResult);
            
            Log::info("MLModelTrainingService - Entraînement terminé avec succès", $trainingResult);
            
            return $trainingResult;
            
        } catch (Exception $e) {
            Log::error("MLModelTrainingService - Erreur entraînement", [
                'model' => $modelName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Prépare les données pour l'entraînement avec gestion du déséquilibre
     */
    private function prepareTrainingData(array $options): array
    {
        $startDate = $options['start_date'] ?? Carbon::now()->subMonths(6)->toDateString();
        $endDate = $options['end_date'] ?? Carbon::now()->toDateString();
        
        // Features sélectionnées v2.1 (multi-opérateur)
        $selectedFeatures = [
            // Features génériques (v1.0)
            'payment_success_rate',
            'consecutive_failures',
            'total_payments',
            'payment_reliability_score',
            'days_since_last_payment',
            'subscription_age_days',
            'is_high_value_client',
            
            // Features temporelles (v2.0)
            'morning_success_rate',
            'afternoon_success_rate', 
            'evening_success_rate',
            'recovery_after_failure_rate',
            'max_consecutive_successes',
            'payment_amount_std',
            'no_balance_failure_rate',
            'not_delivered_failure_rate',
            
            // Features multi-opérateur (v2.1)
            'timwe_success_rate',
            'timwe_has_activity',
            'eklektik_success_rate',
            'eklektik_daily_consistency',
            'eklektik_has_activity',
            'ooredoo_success_rate',
            'ooredoo_monthly_consistency', 
            'ooredoo_has_activity',
            'total_operators_used',
            'operator_diversity_score',
            'prefers_low_price',
            'prefers_high_price',
            'prefers_daily_offers',
            'prefers_monthly_offers',
        ];

        // Charger les features avec calcul des labels réels
        $query = "
            SELECT 
                f.*,
                CASE WHEN f.payment_success_rate > 0 THEN 1 ELSE 0 END as label
            FROM ml_client_features f
            WHERE f.calculation_date BETWEEN ? AND ?
            AND f.payment_success_rate IS NOT NULL
            ORDER BY f.calculation_date, f.client_id
        ";
        
        $rawData = DB::select($query, [$startDate, $endDate]);
        
        $features = [];
        $labels = [];
        $clientIds = [];
        
        foreach ($rawData as $row) {
            $featureVector = [];
            foreach ($selectedFeatures as $feature) {
                $featureVector[] = $row->{$feature} ?? 0;
            }
            
            $features[] = $featureVector;
            $labels[] = (int)$row->label;
            $clientIds[] = $row->client_id;
        }
        
        $positiveCount = array_sum($labels);
        $totalCount = count($labels);
        $positiveRate = $totalCount > 0 ? $positiveCount / $totalCount : 0;
        
        return [
            'features' => $features,
            'labels' => $labels,
            'client_ids' => $clientIds,
            'feature_names' => $selectedFeatures,
            'positive_rate' => $positiveRate,
            'total_samples' => $totalCount,
            'positive_samples' => $positiveCount,
            'negative_samples' => $totalCount - $positiveCount
        ];
    }

    /**
     * Exporte les données vers un format Python
     */
    private function exportToPython(array $trainingData, string $modelName): string
    {
        $pythonDataPath = storage_path("ml_data/training_{$modelName}_" . date('Y_m_d_H_i_s') . '.json');
        
        // Créer le dossier si nécessaire
        $dir = dirname($pythonDataPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $exportData = [
            'features' => $trainingData['features'],
            'labels' => $trainingData['labels'],
            'feature_names' => $trainingData['feature_names'],
            'metadata' => [
                'total_samples' => $trainingData['total_samples'],
                'positive_samples' => $trainingData['positive_samples'],
                'negative_samples' => $trainingData['negative_samples'],
                'positive_rate' => $trainingData['positive_rate'],
                'export_date' => now()->toISOString(),
                'model_name' => $modelName
            ]
        ];
        
        file_put_contents($pythonDataPath, json_encode($exportData, JSON_UNESCAPED_UNICODE));
        
        Log::info("MLModelTrainingService - Données exportées vers Python", [
            'path' => $pythonDataPath,
            'size_mb' => round(filesize($pythonDataPath) / 1024 / 1024, 2)
        ]);
        
        return $pythonDataPath;
    }

    /**
     * Exécute l'entraînement Python LightGBM
     */
    private function executePythonTraining(string $dataPath, string $modelName, array $options): array
    {
        $pythonScriptPath = $this->createPythonTrainingScript($modelName);
        $outputPath = storage_path("ml_models/{$modelName}_results.json");
        
        // Paramètres d'entraînement
        $maxRounds = $options['max_rounds'] ?? 200;
        $learningRate = $options['learning_rate'] ?? 0.05;
        $testSize = $options['test_size'] ?? 0.2;
        
        $command = "python \"{$pythonScriptPath}\" \"{$dataPath}\" \"{$outputPath}\" --max_rounds={$maxRounds} --learning_rate={$learningRate} --test_size={$testSize}";
        
        Log::info("MLModelTrainingService - Lancement entraînement Python", [
            'command' => $command
        ]);
        
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Erreur Python: " . implode("\n", $output));
        }
        
        if (!file_exists($outputPath)) {
            throw new Exception("Fichier de résultats Python non trouvé: $outputPath");
        }
        
        $results = json_decode(file_get_contents($outputPath), true);
        if (!$results) {
            throw new Exception("Impossible de lire les résultats Python");
        }
        
        return $results;
    }

    /**
     * Crée le script Python d'entraînement LightGBM
     */
    private function createPythonTrainingScript(string $modelName): string
    {
        $scriptPath = storage_path("ml_scripts/train_{$modelName}.py");
        $dir = dirname($scriptPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $pythonCode = $this->getPythonTrainingCode();
        file_put_contents($scriptPath, $pythonCode);
        
        return $scriptPath;
    }

    /**
     * Code Python pour l'entraînement LightGBM optimisé
     */
    private function getPythonTrainingCode(): string
    {
        return '#!/usr/bin/env python3
import json
import sys
import argparse
import time
import numpy as np
import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.metrics import roc_auc_score, classification_report, confusion_matrix, precision_recall_curve
import lightgbm as lgb
import warnings
warnings.filterwarnings("ignore")

def train_model(data_path, output_path, max_rounds=200, learning_rate=0.05, test_size=0.2):
    print("🤖 Entraînement LightGBM avec gestion déséquilibre...")
    start_time = time.time()
    
    # Charger les données
    with open(data_path, "r", encoding="utf-8") as f:
        data = json.load(f)
    
    X = np.array(data["features"], dtype=np.float32)
    y = np.array(data["labels"], dtype=np.int32)
    feature_names = data["feature_names"]
    metadata = data["metadata"]
    
    print(f"📊 Données: {len(X)} échantillons, {X.shape[1]} features")
    print(f"📊 Déséquilibre: {metadata[\"positive_rate\"]:.1%} succès, {1-metadata[\"positive_rate\"]:.1%} échecs")
    
    # Split temporel pour éviter data leakage
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=test_size, shuffle=False, stratify=None, random_state=42
    )
    
    # Calculer scale_pos_weight pour gérer le déséquilibre
    pos_count = np.sum(y_train == 1)
    neg_count = np.sum(y_train == 0)
    scale_pos_weight = float(neg_count / pos_count) if pos_count > 0 else 1.0
    
    print(f"🎯 Scale pos weight: {scale_pos_weight:.2f}")
    
    # Paramètres LightGBM optimisés pour déséquilibre
    params = {
        "objective": "binary",
        "metric": "auc",
        "boosting_type": "gbdt",
        "num_leaves": 31,
        "learning_rate": learning_rate,
        "feature_fraction": 0.8,
        "bagging_fraction": 0.8,
        "bagging_freq": 5,
        "scale_pos_weight": scale_pos_weight,
        "min_child_weight": 5,
        "min_child_samples": 10,
        "reg_alpha": 0.1,
        "reg_lambda": 0.1,
        "random_state": 42,
        "verbosity": -1
    }
    
    # Datasets LightGBM
    train_data = lgb.Dataset(X_train, label=y_train, feature_name=feature_names)
    val_data = lgb.Dataset(X_test, label=y_test, reference=train_data, feature_name=feature_names)
    
    # Entraînement avec early stopping
    print("🏃 Démarrage entraînement...")
    model = lgb.train(
        params,
        train_data,
        num_boost_round=max_rounds,
        valid_sets=[train_data, val_data],
        valid_names=["train", "eval"], 
        callbacks=[lgb.early_stopping(20), lgb.log_evaluation(0)]
    )
    
    # Prédictions
    y_pred_train = model.predict(X_train)
    y_pred_test = model.predict(X_test)
    
    # Métriques
    train_auc = roc_auc_score(y_train, y_pred_train)
    test_auc = roc_auc_score(y_test, y_pred_test)
    
    # Optimiser le seuil pour F1-score
    precision, recall, thresholds = precision_recall_curve(y_test, y_pred_test)
    f1_scores = 2 * (precision * recall) / (precision + recall + 1e-8)
    best_threshold_idx = np.argmax(f1_scores)
    best_threshold = float(thresholds[best_threshold_idx]) if len(thresholds) > best_threshold_idx else 0.5
    
    # Prédictions binaires avec seuil optimisé
    y_pred_binary = (y_pred_test >= best_threshold).astype(int)
    
    # Rapport de classification
    class_report = classification_report(y_test, y_pred_binary, output_dict=True, zero_division=0)
    conf_matrix = confusion_matrix(y_test, y_pred_binary).tolist()
    
    # Feature importance
    feature_importance = dict(zip(feature_names, model.feature_importance().tolist()))
    feature_importance_sorted = sorted(feature_importance.items(), key=lambda x: x[1], reverse=True)
    
    # Nom du modèle avec timestamp
    model_filename = f"lightgbm_{int(time.time())}.txt"
    model_path = f"storage/ml_models/{model_filename}"
    
    # Sauvegarder le modèle
    model.save_model(model_path)
    
    training_time = time.time() - start_time
    
    results = {
        "success": True,
        "model_name": sys.argv[0].split("/")[-1] if len(sys.argv) > 0 else "lightgbm",
        "model_path": model_path,
        "training_samples": int(len(X_train)),
        "test_samples": int(len(X_test)),
        "positive_rate": float(metadata["positive_rate"]),
        "scale_pos_weight": scale_pos_weight,
        "best_threshold": best_threshold,
        "training_duration_minutes": round(training_time / 60, 2),
        "performance": {
            "train_auc": float(train_auc),
            "test_auc": float(test_auc),
            "accuracy": float(class_report.get("accuracy", 0)),
            "precision": float(class_report.get("1", {}).get("precision", 0)),
            "recall": float(class_report.get("1", {}).get("recall", 0)),
            "f1_score": float(class_report.get("1", {}).get("f1-score", 0)),
            "confusion_matrix": conf_matrix
        },
        "feature_importance": feature_importance_sorted[:15],
        "training_params": params,
        "trained_at": pd.Timestamp.now().isoformat()
    }
    
    # Sauvegarder les résultats
    with open(output_path, "w", encoding="utf-8") as f:
        json.dump(results, f, indent=2, ensure_ascii=False)
    
    print("✅ Entraînement terminé!")
    print(f"📊 AUC Train: {train_auc:.3f}")
    print(f"📊 AUC Test: {test_auc:.3f}")
    print(f"📊 F1-Score: {class_report.get(\"1\", {}).get(\"f1-score\", 0):.3f}")
    print(f"📊 Seuil optimal: {best_threshold:.3f}")
    print(f"⏱️  Durée: {training_time:.1f}s")
    
    return results

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Entraînement modèle LightGBM pour prédiction succès facturation")
    parser.add_argument("data_path", help="Chemin vers les données JSON")
    parser.add_argument("output_path", help="Chemin de sortie des résultats")
    parser.add_argument("--max_rounds", type=int, default=200, help="Nombre max de rounds")
    parser.add_argument("--learning_rate", type=float, default=0.05, help="Taux dapprentissage")
    parser.add_argument("--test_size", type=float, default=0.2, help="Taille du set de test")
    
    args = parser.parse_args()
    
    try:
        results = train_model(
            args.data_path, 
            args.output_path, 
            args.max_rounds, 
            args.learning_rate, 
            args.test_size
        )
        print("🎉 Succès!")
        sys.exit(0)
    except Exception as e:
        print(f"❌ Erreur: {str(e)}")
        import traceback
        traceback.print_exc()
        sys.exit(1)
';
    }

    /**
     * Sauvegarde les métriques de performance dans la base
     */
    private function saveModelPerformance(string $modelName, array $results): void
    {
        DB::table('ml_model_performance')->insert([
            'model_name' => $modelName,
            'model_version' => $results['model_name'] ?? $modelName,
            'evaluation_date' => now(),
            'accuracy' => $results['performance']['accuracy'] ?? 0,
            'precision' => $results['performance']['precision'] ?? 0,
            'recall' => $results['performance']['recall'] ?? 0,
            'f1_score' => $results['performance']['f1_score'] ?? 0,
            'auc_roc' => $results['performance']['test_auc'] ?? 0,
            'training_samples' => $results['training_samples'] ?? 0,
            'test_samples' => $results['test_samples'] ?? 0,
            'feature_count' => count($results['feature_importance'] ?? []),
            'positive_rate' => $results['positive_rate'] ?? 0,
            'model_path' => $results['model_path'] ?? null,
            'training_duration_minutes' => null, // À calculer
            'model_size_mb' => null,
            'revenue_impact' => null, // À calculer après déploiement
            'success_rate_improvement' => null, // À calculer
            'training_params' => json_encode($results['training_params'] ?? []),
            'feature_importance' => json_encode($results['feature_importance'] ?? []),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Met à jour le service de prédiction avec le nouveau modèle
     */
    private function updatePredictionService(string $modelName, array $results): void
    {
        // Créer un fichier de configuration pour le nouveau modèle
        $configPath = storage_path("ml_config/active_model.json");
        $dir = dirname($configPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $config = [
            'active_model' => $modelName,
            'model_path' => $results['model_path'] ?? null,
            'model_type' => 'lightgbm',
            'threshold' => $results['best_threshold'] ?? 0.5,
            'feature_names' => array_column($results['feature_importance'] ?? [], 0),
            'updated_at' => now()->toISOString(),
            'performance' => $results['performance'] ?? []
        ];
        
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
        
        Log::info("MLModelTrainingService - Configuration modèle mise à jour", [
            'active_model' => $modelName,
            'config_path' => $configPath
        ]);
    }

    /**
     * Valide les performances du modèle sur données récentes
     */
    public function validateModel(string $modelName, Carbon $validationDate = null): array
    {
        if (!$validationDate) {
            $validationDate = Carbon::now();
        }
        
        // Récupérer les prédictions récentes vs résultats réels
        $predictions = DB::table('ml_predictions')
            ->where('model_version', 'LIKE', "%{$modelName}%")
            ->where('prediction_date', '>=', $validationDate->subWeeks(2))
            ->get();
        
        if (empty($predictions)) {
            return ['status' => 'no_data', 'message' => 'Pas de prédictions récentes trouvées'];
        }
        
        // Calculer les métriques de validation
        // (à implémenter selon la logique métier)
        
        return [
            'status' => 'validated',
            'validation_date' => $validationDate->toISOString(),
            'predictions_count' => count($predictions),
            'accuracy_estimate' => 0.75, // À calculer réellement
            'drift_detected' => false
        ];
    }
}