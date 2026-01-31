<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\MLClientFeature;
use Carbon\Carbon;

class MLPredictionService
{
    /**
     * Prédit la probabilité de succès de facturation pour un client
     */
    public function predictPaymentSuccess(int $clientId, Carbon $predictionDate = null): array
    {
        if (!$predictionDate) {
            $predictionDate = Carbon::now();
        }

        Log::info("MLPredictionService - Prédiction succès paiement pour client $clientId");

        try {
            // Récupérer les features les plus récentes du client
            $features = MLClientFeature::getLatestForClient($clientId);
            
            if (!$features) {
                Log::warning("MLPredictionService - Aucune feature trouvée pour client $clientId");
                return $this->getDefaultPrediction();
            }

            // === MODÈLE SIMPLE BASÉ SUR LES RÈGLES ===
            // (En attendant l'implémentation des vrais modèles ML)
            
            $prediction = $this->ruleBasedPaymentPrediction($features, $predictionDate);
            
            // Sauvegarder la prédiction
            $this->storePrediction($clientId, $predictionDate, $prediction);
            
            return $prediction;

        } catch (\Exception $e) {
            Log::error("MLPredictionService - Erreur prédiction client $clientId", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->getDefaultPrediction();
        }
    }

    /**
     * Modèle basé sur des règles (temporaire avant ML)
     */
    private function ruleBasedPaymentPrediction(MLClientFeature $features, Carbon $predictionDate): array
    {
        $baseSuccessRate = $features->payment_success_rate;
        $reliability = $features->payment_reliability_score;
        
        // === AJUSTEMENTS TEMPORELS ===
        $timeAdjustment = 0;
        
        // Jour de la semaine optimal
        if ($features->best_billing_day_week) {
            $currentDayOfWeek = $predictionDate->dayOfWeek ?: 7; // Dimanche = 7
            if ($currentDayOfWeek == $features->best_billing_day_week) {
                $timeAdjustment += 0.15; // +15% si c'est le bon jour
            }
        }
        
        // Heure optimale
        if ($features->best_billing_hour) {
            $currentHour = $predictionDate->hour;
            $hourDiff = abs($currentHour - $features->best_billing_hour);
            if ($hourDiff <= 2) {
                $timeAdjustment += 0.10; // +10% si c'est dans la bonne plage horaire
            }
        }
        
        // Fin/début de mois
        $dayOfMonth = $predictionDate->day;
        if ($dayOfMonth > 25 && $features->end_month_success_rate > $features->beginning_month_success_rate) {
            $timeAdjustment += 0.05; // +5% fin de mois
        } elseif ($dayOfMonth <= 5 && $features->beginning_month_success_rate > $features->end_month_success_rate) {
            $timeAdjustment += 0.05; // +5% début de mois
        }
        
        // === AJUSTEMENTS COMPORTEMENTAUX ===
        $behaviorAdjustment = 0;
        
        // Échecs consécutifs récents
        if ($features->consecutive_failures > 5) {
            $behaviorAdjustment -= 0.20; // -20% après 5 échecs
        } elseif ($features->consecutive_failures > 2) {
            $behaviorAdjustment -= 0.10; // -10% après 2 échecs
        }
        
        // Client de valeur
        if ($features->is_high_value_client) {
            $behaviorAdjustment += 0.05; // +5% client important
        }
        
        // Engagement récent
        if ($features->engagement_score > 0.7) {
            $behaviorAdjustment += 0.05; // +5% client engagé
        }
        
        // === CALCUL FINAL ===
        $predictedSuccessRate = $baseSuccessRate + $timeAdjustment + $behaviorAdjustment;
        $predictedSuccessRate = max(0, min(1, $predictedSuccessRate)); // Limiter entre 0 et 1
        
        // Confiance basée sur la quantité de données historiques
        $confidence = min(0.95, $features->total_attempts / 50); // Max confiance à 50 tentatives
        $confidence = max(0.3, $confidence); // Min 30% de confiance
        
        // Timing optimal
        $optimalTiming = $this->calculateOptimalTiming($features, $predictionDate);
        
        // Prix optimal
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
            'optimal_billing_day_of_week' => $optimalTiming['day_of_week'],
            'optimal_billing_hour' => $optimalTiming['hour'],
            'timing_confidence' => $optimalTiming['confidence'],
            'optimal_price' => $optimalPrice['price'],
            'optimal_frequency' => $optimalPrice['frequency'],
            'price_confidence' => $optimalPrice['confidence'],
            'client_segment' => $features->client_segment,
            'churn_probability' => $features->churn_probability,
            'model_version' => 'rule_based_v1.0',
            'features_used' => [
                'payment_success_rate', 'consecutive_failures', 'best_billing_day_week',
                'best_billing_hour', 'is_high_value_client', 'engagement_score',
                'end_month_success_rate', 'beginning_month_success_rate'
            ]
        ];
    }

    /**
     * Calcule le timing optimal pour un client
     */
    private function calculateOptimalTiming(MLClientFeature $features, Carbon $baseDate): array
    {
        $optimalDate = $baseDate->copy();
        
        // Utiliser le jour optimal de la semaine si disponible
        if ($features->best_billing_day_week) {
            $targetDayOfWeek = $features->best_billing_day_week;
            $currentDayOfWeek = $baseDate->dayOfWeek ?: 7; // Dimanche = 7
            
            $daysToAdd = ($targetDayOfWeek - $currentDayOfWeek + 7) % 7;
            if ($daysToAdd == 0 && $baseDate->hour >= ($features->best_billing_hour ?? 12)) {
                $daysToAdd = 7; // Si c'est le bon jour mais passé l'heure, prendre la semaine suivante
            }
            
            $optimalDate->addDays($daysToAdd);
        }
        
        // Utiliser l'heure optimale si disponible
        if ($features->best_billing_hour) {
            $optimalDate->setTime($features->best_billing_hour, 0, 0);
        } else {
            // Heure par défaut : 14h (après-midi)
            $optimalDate->setTime(14, 0, 0);
        }
        
        // Éviter les week-ends si pas de pattern spécifique
        if (!$features->best_billing_day_week && in_array($optimalDate->dayOfWeek, [0, 6])) {
            // Passer au lundi suivant
            $optimalDate->next(Carbon::MONDAY);
        }
        
        $confidence = 0.6; // Confiance de base
        if ($features->best_billing_day_week && $features->best_billing_hour) {
            $confidence = 0.85; // Haute confiance si on a les deux
        } elseif ($features->best_billing_day_week || $features->best_billing_hour) {
            $confidence = 0.75; // Confiance moyenne si on a un des deux
        }
        
        return [
            'datetime' => $optimalDate->toDateTimeString(),
            'day_of_week' => $optimalDate->dayOfWeek ?: 7,
            'hour' => $optimalDate->hour,
            'confidence' => round($confidence, 4),
            'reasoning' => 'Based on historical success patterns'
        ];
    }

    /**
     * Calcule le prix et la fréquence optimaux
     */
    private function calculateOptimalPrice(MLClientFeature $features): array
    {
        $segment = $features->client_segment;
        $successRate = $features->payment_success_rate;
        
        // Configuration par segment
        $segmentConfig = [
            'premium_payers' => [
                'price' => 3.500,
                'frequency' => 'monthly',
                'confidence' => 0.9
            ],
            'regular_payers' => [
                'price' => 1.500,
                'frequency' => 'bi_weekly',
                'confidence' => 0.8
            ],
            'struggling_payers' => [
                'price' => 0.750,
                'frequency' => 'weekly',
                'confidence' => 0.7
            ],
            'high_risk' => [
                'price' => 0.100,
                'frequency' => 'daily',
                'confidence' => 0.6
            ],
            'churn_risk' => [
                'price' => 2.000, // Prix réduit pour la rétention
                'frequency' => 'monthly',
                'confidence' => 0.5
            ]
        ];
        
        $config = $segmentConfig[$segment] ?? [
            'price' => 3.000,
            'frequency' => 'monthly',
            'confidence' => 0.5
        ];
        
        // Ajustements fins basés sur les performances
        if ($successRate > 0.8) {
            $config['price'] *= 1.1; // +10% pour les très bons payeurs
        } elseif ($successRate < 0.2) {
            $config['price'] *= 0.8; // -20% pour les mauvais payeurs
        }
        
        return [
            'price' => round($config['price'], 3),
            'frequency' => $config['frequency'],
            'confidence' => $config['confidence'],
            'reasoning' => "Based on segment: $segment (success rate: " . round($successRate * 100, 1) . "%)"
        ];
    }

    /**
     * Sauvegarde une prédiction en base de données
     */
    private function storePrediction(int $clientId, Carbon $predictionDate, array $prediction): void
    {
        try {
            DB::table('ml_predictions')->upsert([
                [
                    'client_id' => $clientId,
                    'prediction_date' => $predictionDate->toDateString(),
                    'payment_success_probability' => $prediction['payment_success_probability'],
                    'churn_probability' => $prediction['churn_probability'] ?? 0,
                    'optimal_price' => $prediction['optimal_price'],
                    'optimal_frequency' => $prediction['optimal_frequency'],
                    'optimal_billing_time' => $prediction['optimal_billing_time'],
                    'optimal_billing_day_of_week' => $prediction['optimal_billing_day_of_week'],
                    'optimal_billing_hour' => $prediction['optimal_billing_hour'],
                    'success_confidence' => $prediction['success_confidence'],
                    'timing_confidence' => $prediction['timing_confidence'],
                    'price_confidence' => $prediction['price_confidence'],
                    'model_version' => $prediction['model_version'],
                    'model_features_used' => json_encode($prediction['features_used']),
                    'prediction_explanation' => "Rule-based model prediction with time/behavior adjustments",
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]
            ], 
            ['client_id', 'prediction_date'], // Clés uniques
            [
                'payment_success_probability', 'churn_probability', 'optimal_price',
                'optimal_frequency', 'optimal_billing_time', 'optimal_billing_day_of_week',
                'optimal_billing_hour', 'success_confidence', 'timing_confidence',
                'price_confidence', 'model_version', 'model_features_used',
                'prediction_explanation', 'updated_at'
            ] // Colonnes à mettre à jour
            );
        } catch (\Exception $e) {
            Log::error("MLPredictionService - Erreur sauvegarde prédiction", [
                'client_id' => $clientId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Prédiction par défaut en cas d'erreur
     */
    private function getDefaultPrediction(): array
    {
        return [
            'payment_success_probability' => 0.10, // 10% par défaut (taux actuel)
            'success_confidence' => 0.30,
            'optimal_billing_time' => Carbon::now()->addDays(1)->setTime(14, 0, 0)->toDateTimeString(),
            'optimal_billing_day_of_week' => 3, // Mercredi
            'optimal_billing_hour' => 14,
            'timing_confidence' => 0.30,
            'optimal_price' => 3.000,
            'optimal_frequency' => 'monthly',
            'price_confidence' => 0.30,
            'client_segment' => 'unknown',
            'churn_probability' => 0.50,
            'model_version' => 'default_fallback',
            'prediction_explanation' => 'Default prediction - no features available'
        ];
    }

    /**
     * Prédictions en batch pour plusieurs clients
     */
    public function batchPredictPaymentSuccess(array $clientIds, Carbon $predictionDate = null): array
    {
        Log::info("MLPredictionService - Prédictions batch pour " . count($clientIds) . " clients");
        
        $predictions = [];
        
        foreach ($clientIds as $clientId) {
            $predictions[$clientId] = $this->predictPaymentSuccess($clientId, $predictionDate);
        }
        
        return $predictions;
    }

    /**
     * Récupère les prédictions pour le dashboard
     */
    public function getDashboardPredictions(int $limit = 20): array
    {
        $today = Carbon::today();
        
        // Clients avec prédictions récentes
        $predictions = DB::table('ml_predictions as p')
            ->join('ml_client_features as f', function($join) {
                $join->on('p.client_id', '=', 'f.client_id')
                     ->whereRaw('f.calculation_date = (
                         SELECT MAX(calculation_date) 
                         FROM ml_client_features 
                         WHERE client_id = p.client_id
                     )');
            })
            ->leftJoin('client as c', 'p.client_id', '=', 'c.client_id')
            ->where('p.prediction_date', $today->toDateString())
            ->select(
                'p.*',
                'f.client_segment',
                'f.payment_success_rate as historical_success_rate',
                'f.is_high_value_client',
                'c.client_telephone',
                'c.client_nom',
                'c.client_prenom'
            )
            ->orderBy('p.payment_success_probability', 'desc')
            ->limit($limit)
            ->get();

        return [
            'predictions' => $predictions->toArray(),
            'summary' => [
                'total_predictions' => $predictions->count(),
                'avg_success_probability' => $predictions->avg('payment_success_probability'),
                'high_confidence_count' => $predictions->where('success_confidence', '>', 0.7)->count(),
                'high_risk_clients' => $predictions->where('churn_probability', '>', 0.5)->count(),
            ]
        ];
    }
}