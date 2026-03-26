<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\MLClientFeature;
use App\Services\MLABTestingService;
use Carbon\Carbon;
use Exception;

class MLPredictionServiceV2
{
    private MLABTestingService $abTestingService;

    public function __construct(MLABTestingService $abTestingService)
    {
        $this->abTestingService = $abTestingService;
    }

    /**
     * Prédit la probabilité de succès avec A/B testing (ML vs rule-based)
     */
    public function predictPaymentSuccess(int $clientId, Carbon $predictionDate = null): array
    {
        if (!$predictionDate) {
            $predictionDate = Carbon::now();
        }

        Log::info("MLPredictionServiceV2 - Prédiction pour client $clientId");

        try {
            // Récupérer les features du client
            $features = MLClientFeature::getLatestForClient($clientId);
            
            if (!$features) {
                Log::warning("MLPredictionServiceV2 - Aucune feature trouvée pour client $clientId");
                return $this->getDefaultPrediction();
            }

            // Déterminer quelle méthode utiliser via A/B testing
            $useMLPrediction = $this->abTestingService->shouldUseMLPrediction($clientId);
            
            if ($useMLPrediction && $this->isLightGBModelAvailable()) {
                $prediction = $this->lightgbmPrediction($features, $predictionDate);
                $prediction['model_used'] = 'lightgbm_v1';
                $prediction['ab_test_group'] = 'treatment';
            } else {
                $prediction = $this->ruleBasedPrediction($features, $predictionDate);
                $prediction['model_used'] = 'rule_based_v1.0';
                $prediction['ab_test_group'] = $useMLPrediction ? 'treatment_fallback' : 'control';
            }
            
            // Enregistrer la prédiction
            $this->storePrediction($clientId, $predictionDate, $prediction);
            
            // Enregistrer pour l'A/B testing si applicable
            $this->recordForABTesting($clientId, $prediction);
            
            return $prediction;

        } catch (Exception $e) {
            Log::error("MLPredictionServiceV2 - Erreur prédiction client $clientId", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->getDefaultPrediction();
        }
    }

    /**
     * Prédiction utilisant le modèle LightGBM entraîné
     */
    private function lightgbmPrediction(MLClientFeature $features, Carbon $predictionDate): array
    {
        try {
            // Charger la configuration du modèle actif
            $modelConfig = $this->loadModelConfig();
            
            if (!$modelConfig || !isset($modelConfig['model_path'])) {
                throw new Exception("Configuration modèle LightGBM non trouvée");
            }
            
            // Préparer le vecteur de features
            $featureVector = $this->prepareFeatureVector($features, $modelConfig['feature_names']);
            
            // Appeler le script Python pour la prédiction
            $prediction = $this->executePythonPrediction($featureVector, $modelConfig);
            
            $successProbability = $prediction['probability'] ?? 0;
            $confidence = $prediction['confidence'] ?? 0.7;
            
            // Calculs dérivés
            $optimalTiming = $this->calculateOptimalTiming($features, $predictionDate);
            $optimalPrice = $this->calculateOptimalPriceML($features, $successProbability);
            
            return [
                'client_id' => $features->client_id,
                'prediction_date' => $predictionDate->toDateString(),
                'payment_success_probability' => round($successProbability, 4),
                'success_confidence' => round($confidence, 4),
                'optimal_billing_time' => $optimalTiming['datetime'],
                'optimal_billing_hour' => $optimalTiming['hour'],
                'optimal_price' => $optimalPrice['price'],
                'optimal_frequency' => $optimalPrice['frequency'],
                'price_confidence' => $optimalPrice['confidence'],
                'timing_confidence' => $optimalTiming['confidence'],
                'client_segment' => $features->client_segment,
                'churn_probability' => $features->churn_probability,
                'model_version' => $modelConfig['active_model'] ?? 'lightgbm_v1',
                'prediction_threshold' => $modelConfig['threshold'] ?? 0.5,
                'features_used' => $modelConfig['feature_names'] ?? [],
                'ml_explanation' => $prediction['explanation'] ?? null
            ];
            
        } catch (Exception $e) {
            Log::warning("MLPredictionServiceV2 - Fallback vers rule-based pour client {$features->client_id}: " . $e->getMessage());
            return $this->ruleBasedPrediction($features, $predictionDate);
        }
    }

    /**
     * Prédiction rule-based (version améliorée)
     */
    private function ruleBasedPrediction(MLClientFeature $features, Carbon $predictionDate): array
    {
        $baseSuccessRate = $features->payment_success_rate;
        $reliability = $features->payment_reliability_score;
        
        // === AJUSTEMENTS TEMPORELS AMÉLIORÉS ===
        $timeAdjustment = 0;
        
        // Patterns temporels fins (nouvelles features)
        $currentHour = $predictionDate->hour;
        if ($currentHour >= 6 && $currentHour < 12) {
            $timeAdjustment += $features->morning_success_rate * 0.1;
        } elseif ($currentHour >= 12 && $currentHour < 18) {
            $timeAdjustment += $features->afternoon_success_rate * 0.1;
        } elseif ($currentHour >= 18 && $currentHour < 22) {
            $timeAdjustment += $features->evening_success_rate * 0.1;
        }
        
        // Jour optimal
        if ($features->best_billing_day_week) {
            $currentDayOfWeek = $predictionDate->dayOfWeek ?: 7;
            if ($currentDayOfWeek == $features->best_billing_day_week) {
                $timeAdjustment += 0.15;
            }
        }
        
        // === AJUSTEMENTS COMPORTEMENTAUX AMÉLIORÉS ===
        $behaviorAdjustment = 0;
        
        // Nouvelles features de récupération
        if ($features->recovery_after_failure_rate > 0.5) {
            $behaviorAdjustment += 0.1; // Bon à récupérer après échec
        }
        
        if ($features->max_consecutive_successes >= 3) {
            $behaviorAdjustment += 0.05; // Capable de séquences de succès
        }
        
        // Stabilité des montants (nouveauté)
        if ($features->payment_amount_std < 0.5) {
            $behaviorAdjustment += 0.03; // Montants stables = prédictible
        }
        
        // Patterns d'échec spécifiques
        if ($features->no_balance_failure_rate > 0.8) {
            $behaviorAdjustment -= 0.15; // Beaucoup de problèmes de solde
        }
        
        // Échecs consécutifs (logique existante)
        if ($features->consecutive_failures > 5) {
            $behaviorAdjustment -= 0.20;
        } elseif ($features->consecutive_failures > 2) {
            $behaviorAdjustment -= 0.10;
        }
        
        // === CALCUL FINAL AMÉLIORÉ ===
        $predictedSuccessRate = $baseSuccessRate + $timeAdjustment + $behaviorAdjustment;
        $predictedSuccessRate = max(0, min(1, $predictedSuccessRate));
        
        // Confiance basée sur la qualité des données
        $confidence = $this->calculateConfidence($features);
        
        $optimalTiming = $this->calculateOptimalTiming($features, $predictionDate);
        $optimalPrice = $this->calculateOptimalPrice($features);
        
        return [
            'client_id' => $features->client_id,
            'prediction_date' => $predictionDate->toDateString(),
            'payment_success_probability' => round($predictedSuccessRate, 4),
            'success_confidence' => round($confidence, 4),
            'base_success_rate' => round($baseSuccessRate, 4),
            'time_adjustment' => round($timeAdjustment, 4),
            'behavior_adjustment' => round($behaviorAdjustment, 4),
            'optimal_billing_time' => $optimalTiming['datetime'],
            'optimal_billing_hour' => $optimalTiming['hour'],
            'optimal_price' => $optimalPrice['price'],
            'optimal_frequency' => $optimalPrice['frequency'],
            'price_confidence' => $optimalPrice['confidence'],
            'timing_confidence' => $optimalTiming['confidence'],
            'client_segment' => $features->client_segment,
            'churn_probability' => $features->churn_probability,
            'model_version' => 'rule_based_v2.0_improved',
            'features_used' => [
                'payment_success_rate', 'morning_success_rate', 'afternoon_success_rate',
                'evening_success_rate', 'recovery_after_failure_rate', 'max_consecutive_successes',
                'payment_amount_std', 'no_balance_failure_rate', 'consecutive_failures'
            ]
        ];
    }

    /**
     * Calcule une confiance améliorée basée sur la qualité des données
     */
    private function calculateConfidence(MLClientFeature $features): float
    {
        $confidence = 0.3; // Base minimum
        
        // Facteur 1: Quantité de données (30%)
        $totalAttempts = $features->total_attempts ?? 0;
        $dataQuality = min($totalAttempts / 50, 1.0); // Max à 50 tentatives
        $confidence += $dataQuality * 0.3;
        
        // Facteur 2: Stabilité comportementale (25%)
        $amountStd = $features->payment_amount_std ?? 999;
        $stabilityScore = $amountStd < 1.0 ? 0.25 : ($amountStd < 2.0 ? 0.15 : 0);
        $confidence += $stabilityScore;
        
        // Facteur 3: Récence des données (25%)
        $daysSinceLastPayment = $features->days_since_last_payment ?? 999;
        $recencyScore = 0;
        if ($daysSinceLastPayment <= 7) {
            $recencyScore = 0.25;
        } elseif ($daysSinceLastPayment <= 30) {
            $recencyScore = 0.15;
        } elseif ($daysSinceLastPayment <= 90) {
            $recencyScore = 0.1;
        }
        $confidence += $recencyScore;
        
        // Facteur 4: Cohérence segment (20%)
        $segmentCoherence = 0;
        $successRate = $features->payment_success_rate ?? 0;
        switch ($features->client_segment) {
            case 'premium_payers':
                $segmentCoherence = $successRate >= 0.7 ? 0.2 : 0.1;
                break;
            case 'regular_payers':
                $segmentCoherence = $successRate >= 0.3 && $successRate <= 0.8 ? 0.2 : 0.1;
                break;
            case 'struggling_payers':
                $segmentCoherence = $successRate >= 0.1 && $successRate <= 0.4 ? 0.2 : 0.1;
                break;
            default:
                $segmentCoherence = 0.05;
        }
        $confidence += $segmentCoherence;
        
        return max(0.3, min(0.95, $confidence));
    }

    /**
     * Charge la configuration du modèle ML actif
     */
    private function loadModelConfig(): ?array
    {
        $configPath = storage_path('ml_config/active_model.json');
        
        if (!file_exists($configPath)) {
            return null;
        }
        
        $content = file_get_contents($configPath);
        return json_decode($content, true);
    }

    /**
     * Vérifie si un modèle LightGBM est disponible
     */
    private function isLightGBModelAvailable(): bool
    {
        $config = $this->loadModelConfig();
        return $config && 
               isset($config['model_path']) && 
               file_exists($config['model_path']) && 
               $config['model_type'] === 'lightgbm';
    }

    /**
     * Prépare le vecteur de features pour le modèle ML
     */
    private function prepareFeatureVector(MLClientFeature $features, array $featureNames): array
    {
        $vector = [];
        
        foreach ($featureNames as $featureName) {
            $value = $features->{$featureName} ?? 0;
            
            // Normalisation selon le type de feature
            if (in_array($featureName, ['payment_success_rate', 'engagement_score', 'lifetime_value_score'])) {
                $value = max(0, min(1, $value)); // [0, 1]
            } elseif (in_array($featureName, ['consecutive_failures', 'total_payments', 'total_attempts'])) {
                $value = max(0, $value); // [0, +∞]
            }
            
            $vector[] = (float)$value;
        }
        
        return $vector;
    }

    /**
     * Exécute une prédiction Python avec le modèle LightGBM
     */
    private function executePythonPrediction(array $featureVector, array $modelConfig): array
    {
        $tempDataPath = storage_path('ml_temp/prediction_' . uniqid() . '.json');
        $tempResultPath = storage_path('ml_temp/result_' . uniqid() . '.json');
        
        // Créer les dossiers si nécessaire
        $tempDir = dirname($tempDataPath);
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        // Préparer les données pour Python
        $pythonData = [
            'features' => [$featureVector], // Un seul échantillon
            'feature_names' => $modelConfig['feature_names'],
            'model_path' => $modelConfig['model_path'],
            'threshold' => $modelConfig['threshold'] ?? 0.5
        ];
        
        file_put_contents($tempDataPath, json_encode($pythonData));
        
        // Script Python pour prédiction
        $pythonScript = $this->createPredictionScript();
        
        $command = "python \"$pythonScript\" \"$tempDataPath\" \"$tempResultPath\"";
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Erreur Python prédiction: " . implode("\n", $output));
        }
        
        if (!file_exists($tempResultPath)) {
            throw new Exception("Résultat Python non trouvé");
        }
        
        $result = json_decode(file_get_contents($tempResultPath), true);
        
        // Nettoyer les fichiers temporaires
        @unlink($tempDataPath);
        @unlink($tempResultPath);
        
        return $result ?? [];
    }

    /**
     * Crée le script Python pour les prédictions
     */
    private function createPredictionScript(): string
    {
        $scriptPath = storage_path('ml_scripts/predict.py');
        $dir = dirname($scriptPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $pythonCode = '#!/usr/bin/env python3
import json
import sys
import numpy as np
import lightgbm as lgb
import warnings
warnings.filterwarnings("ignore")

def predict(data_path, output_path):
    # Charger les données
    with open(data_path, "r") as f:
        data = json.load(f)
    
    # Charger le modèle
    model = lgb.Booster(model_file=data["model_path"])
    
    # Prédiction
    features = np.array(data["features"], dtype=np.float32)
    probabilities = model.predict(features)
    
    probability = float(probabilities[0]) if len(probabilities) > 0 else 0
    threshold = data.get("threshold", 0.5)
    
    # Confiance basée sur la distance au seuil
    distance_to_threshold = abs(probability - threshold)
    confidence = min(0.95, 0.5 + distance_to_threshold)
    
    # Feature importance pour explication
    feature_importance = dict(zip(
        data["feature_names"], 
        model.feature_importance().tolist()
    ))
    top_features = sorted(feature_importance.items(), key=lambda x: x[1], reverse=True)[:5]
    
    result = {
        "probability": probability,
        "confidence": confidence,
        "binary_prediction": probability >= threshold,
        "threshold_used": threshold,
        "explanation": {
            "top_features": top_features,
            "distance_to_threshold": distance_to_threshold
        }
    }
    
    # Sauvegarder
    with open(output_path, "w") as f:
        json.dump(result, f)

if __name__ == "__main__":
    try:
        predict(sys.argv[1], sys.argv[2])
    except Exception as e:
        print(f"Erreur: {e}")
        sys.exit(1)
';
        
        file_put_contents($scriptPath, $pythonCode);
        return $scriptPath;
    }

    /**
     * Enregistre le résultat pour l'A/B testing
     */
    private function recordForABTesting(int $clientId, array $prediction): void
    {
        try {
            $activeTest = DB::table('ml_ab_tests')
                ->where('status', 'active')
                ->where('test_name', 'ml_prediction_rollout')
                ->first();
                
            if (!$activeTest) {
                return; // Pas de test actif
            }
            
            $group = $prediction['ab_test_group'] ?? 'unknown';
            
            $outcome = [
                'prediction_probability' => $prediction['payment_success_probability'],
                'confidence' => $prediction['success_confidence'],
                'model_used' => $prediction['model_used'],
                'recorded_at' => now()->toISOString()
            ];
            
            $this->abTestingService->recordPredictionOutcome($clientId, $activeTest->id, $group, $outcome);
            
        } catch (Exception $e) {
            Log::warning("MLPredictionServiceV2 - Erreur enregistrement A/B testing", [
                'client_id' => $clientId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Prix optimal adapté selon la prédiction ML
     */
    private function calculateOptimalPriceML(MLClientFeature $features, float $successProbability): array
    {
        $segment = $features->client_segment;
        
        // Ajustement dynamique des prix selon la probabilité de succès
        $baseConfig = [
            'premium_payers' => ['price' => 3.5, 'frequency' => 'monthly'],
            'regular_payers' => ['price' => 1.5, 'frequency' => 'bi_weekly'],
            'struggling_payers' => ['price' => 0.75, 'frequency' => 'weekly'],
            'high_risk' => ['price' => 0.1, 'frequency' => 'daily'],
            'churn_risk' => ['price' => 2.0, 'frequency' => 'monthly']
        ];
        
        $config = $baseConfig[$segment] ?? ['price' => 3.0, 'frequency' => 'monthly'];
        
        // Ajustement ML: réduire le prix si faible probabilité
        if ($successProbability < 0.3) {
            $config['price'] *= 0.7; // -30%
            $config['frequency'] = 'weekly'; // Plus fréquent
        } elseif ($successProbability > 0.8) {
            $config['price'] *= 1.2; // +20%
        }
        
        $confidence = 0.7 + ($successProbability - 0.5) * 0.4; // 0.5-0.9 selon proba
        
        return [
            'price' => round($config['price'], 2),
            'frequency' => $config['frequency'],
            'confidence' => round(max(0.3, min(0.9, $confidence)), 4),
            'ml_adjustment' => $successProbability < 0.3 ? 'reduced_risk' : ($successProbability > 0.8 ? 'premium_opportunity' : 'standard')
        ];
    }

    // Reprendre les méthodes de l'ancien service...
    private function calculateOptimalTiming(MLClientFeature $features, Carbon $baseDate): array
    {
        // Logique existante du MLPredictionService
        $optimalDate = $baseDate->copy();
        
        if ($features->best_billing_day_week) {
            $targetDayOfWeek = $features->best_billing_day_week;
            $currentDayOfWeek = $baseDate->dayOfWeek ?: 7;
            
            $daysToAdd = ($targetDayOfWeek - $currentDayOfWeek + 7) % 7;
            if ($daysToAdd == 0 && $baseDate->hour >= ($features->best_billing_hour ?? 12)) {
                $daysToAdd = 7;
            }
            
            $optimalDate->addDays($daysToAdd);
        }
        
        if ($features->best_billing_hour) {
            $optimalDate->setTime($features->best_billing_hour, 0, 0);
        } else {
            $optimalDate->setTime(14, 0, 0);
        }
        
        // Amélioration: utiliser les nouvelles features temporelles
        $confidence = 0.6;
        if ($features->morning_success_rate > 0.5 && $optimalDate->hour >= 6 && $optimalDate->hour < 12) {
            $confidence += 0.15;
        } elseif ($features->afternoon_success_rate > 0.5 && $optimalDate->hour >= 12 && $optimalDate->hour < 18) {
            $confidence += 0.15;
        } elseif ($features->evening_success_rate > 0.5 && $optimalDate->hour >= 18 && $optimalDate->hour < 22) {
            $confidence += 0.15;
        }
        
        return [
            'datetime' => $optimalDate->toDateTimeString(),
            'day_of_week' => $optimalDate->dayOfWeek ?: 7,
            'hour' => $optimalDate->hour,
            'confidence' => round(min(0.95, $confidence), 4)
        ];
    }

    private function calculateOptimalPrice(MLClientFeature $features): array
    {
        // Logique existante mais améliorée
        $segment = $features->client_segment;
        
        $segmentConfig = [
            'premium_payers' => ['price' => 3.5, 'frequency' => 'monthly', 'confidence' => 0.9],
            'regular_payers' => ['price' => 1.5, 'frequency' => 'bi_weekly', 'confidence' => 0.8],
            'struggling_payers' => ['price' => 0.75, 'frequency' => 'weekly', 'confidence' => 0.7],
            'high_risk' => ['price' => 0.1, 'frequency' => 'daily', 'confidence' => 0.6],
            'churn_risk' => ['price' => 2.0, 'frequency' => 'monthly', 'confidence' => 0.5]
        ];
        
        return $segmentConfig[$segment] ?? ['price' => 3.0, 'frequency' => 'monthly', 'confidence' => 0.5];
    }

    private function storePrediction(int $clientId, Carbon $predictionDate, array $prediction): void
    {
        DB::table('ml_predictions')->upsert([
            'client_id' => $clientId,
            'prediction_date' => $predictionDate->toDateString(),
            'payment_success_probability' => $prediction['payment_success_probability'],
            'churn_probability' => $prediction['churn_probability'],
            'optimal_price' => $prediction['optimal_price'],
            'optimal_frequency' => $prediction['optimal_frequency'],
            'optimal_billing_time' => $prediction['optimal_billing_time'],
            'success_confidence' => $prediction['success_confidence'],
            'timing_confidence' => $prediction['timing_confidence'] ?? 0,
            'price_confidence' => $prediction['price_confidence'] ?? 0,
            'model_version' => $prediction['model_version'],
            'model_features_used' => json_encode($prediction['features_used'] ?? []),
            'created_at' => now(),
            'updated_at' => now()
        ], ['client_id', 'prediction_date'], array_keys($prediction));
    }

    private function getDefaultPrediction(): array
    {
        return [
            'payment_success_probability' => 0.09, // Moyenne globale
            'success_confidence' => 0.3,
            'optimal_billing_time' => Carbon::now()->addDay()->setTime(14, 0)->toDateTimeString(),
            'optimal_billing_hour' => 14,
            'optimal_price' => 3.0,
            'optimal_frequency' => 'monthly',
            'price_confidence' => 0.5,
            'timing_confidence' => 0.5,
            'client_segment' => 'unknown',
            'churn_probability' => 0.5,
            'model_version' => 'default_v1.0',
            'ab_test_group' => 'none'
        ];
    }

    /**
     * Prédiction en batch pour plusieurs clients
     */
    public function batchPredictPaymentSuccess(array $clientIds, Carbon $predictionDate = null): array
    {
        $predictions = [];
        
        foreach ($clientIds as $clientId) {
            try {
                $predictions[$clientId] = $this->predictPaymentSuccess($clientId, $predictionDate);
            } catch (Exception $e) {
                Log::error("MLPredictionServiceV2 - Erreur batch client $clientId: " . $e->getMessage());
                $predictions[$clientId] = $this->getDefaultPrediction();
            }
        }
        
        return $predictions;
    }
}