<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\MLClientFeature;
use App\Services\MLABTestingService;
use Carbon\Carbon;
use Exception;

class MLMultiOperatorPredictionService
{
    private MLABTestingService $abTestingService;

    public function __construct(MLABTestingService $abTestingService)
    {
        $this->abTestingService = $abTestingService;
    }

    /**
     * Prédiction adaptée selon l'opérateur et le type d'offre
     */
    public function predictPaymentSuccess(int $clientId, string $operator = 'all', string $offerType = 'auto', Carbon $predictionDate = null): array
    {
        if (!$predictionDate) {
            $predictionDate = Carbon::now();
        }

        Log::info("MLMultiOperatorPredictionService - Prédiction pour client $clientId, opérateur: $operator, offre: $offerType");

        try {
            $features = MLClientFeature::getLatestForClient($clientId);
            
            if (!$features) {
                return $this->getDefaultPrediction($operator, $offerType);
            }

            // Déterminer le type d'offre automatiquement si nécessaire
            if ($offerType === 'auto') {
                $offerType = $this->determineOptimalOfferType($features, $operator);
            }

            // Prédiction adaptée selon l'opérateur
            switch ($operator) {
                case 'timwe':
                    return $this->predictTimwe($features, $predictionDate, $offerType);
                case 'eklektik': 
                    return $this->predictEklektik($features, $predictionDate, $offerType);
                case 'ooredoo':
                case 'dgv':
                    return $this->predictOoredoo($features, $predictionDate, $offerType);
                default:
                    return $this->predictOptimalOperator($features, $predictionDate);
            }

        } catch (Exception $e) {
            Log::error("MLMultiOperatorPredictionService - Erreur prédiction client $clientId", [
                'error' => $e->getMessage()
            ]);
            
            return $this->getDefaultPrediction($operator, $offerType);
        }
    }

    /**
     * Prédiction spécifique Timwe (mensuel 3.0 TND)
     */
    private function predictTimwe(MLClientFeature $features, Carbon $predictionDate, string $offerType): array
    {
        $baseRate = $features->timwe_success_rate ?? 0;
        
        // Ajustements spécifiques Timwe
        $adjustments = 0;
        
        // Historique Timwe favorable
        if ($features->timwe_has_activity && $baseRate > 0.1) {
            $adjustments += 0.1; // +10% si succès passés sur Timwe
        }
        
        // Pas de problèmes NO_BALANCE récurrents
        if (($features->timwe_no_balance_rate ?? 0) < 0.3) {
            $adjustments += 0.05; // +5% si peu de problèmes de solde
        }
        
        // Préférence mensuelle
        if ($features->prefers_monthly_offers) {
            $adjustments += 0.08; // +8% si préfère les offres mensuelles
        }
        
        // Ajustements temporels (fin de mois pour mensuel)
        $dayOfMonth = $predictionDate->day;
        if ($dayOfMonth > 25) {
            $adjustments += 0.12; // +12% fin de mois (salaires reçus)
        } elseif ($dayOfMonth <= 5) {
            $adjustments += 0.08; // +8% début de mois
        }

        $finalProbability = max(0, min(1, $baseRate + $adjustments));
        
        return [
            'client_id' => $features->client_id,
            'operator' => 'timwe',
            'offer_type' => 'monthly',
            'prediction_date' => $predictionDate->toDateString(),
            'payment_success_probability' => round($finalProbability, 4),
            'base_rate' => round($baseRate, 4),
            'adjustments' => round($adjustments, 4),
            'optimal_price' => 3.0,
            'optimal_frequency' => 'monthly',
            'optimal_timing' => $this->calculateOptimalTimingTimwe($features, $predictionDate),
            'success_confidence' => $this->calculateConfidenceTimwe($features),
            'reasoning' => "Offre mensuelle Timwe 3.0 TND - ajustements temporels et historique",
            'model_version' => 'multi_operator_v2.1'
        ];
    }

    /**
     * Prédiction spécifique Eklektik (quotidien 0.3 TND Club Privilèges)
     */
    private function predictEklektik(MLClientFeature $features, Carbon $predictionDate, string $offerType): array
    {
        $baseRate = $features->eklektik_success_rate ?? 0;
        
        // Ajustements spécifiques Eklektik
        $adjustments = 0;
        
        // Consistance quotidienne 
        if (($features->eklektik_daily_consistency ?? 0) > 0.5) {
            $adjustments += 0.15; // +15% si habitude quotidienne
        }
        
        // Préférence bas prix
        if ($features->prefers_low_price) {
            $adjustments += 0.1; // +10% si préfère prix bas
        }
        
        // Préférence quotidienne
        if ($features->prefers_daily_offers) {
            $adjustments += 0.12; // +12% si préfère offres fréquentes
        }
        
        // Ajustements horaires (quotidien = plus flexible)
        $hour = $predictionDate->hour;
        if ($hour >= 8 && $hour <= 20) {
            $adjustments += 0.05; // +5% heures actives
        }
        
        // Jour de semaine (éviter week-end pour business)
        if (!in_array($predictionDate->dayOfWeek, [0, 6])) {
            $adjustments += 0.03; // +3% jour de semaine
        }

        $finalProbability = max(0, min(1, $baseRate + $adjustments));
        
        return [
            'client_id' => $features->client_id,
            'operator' => 'eklektik',
            'offer_type' => 'daily',
            'prediction_date' => $predictionDate->toDateString(),
            'payment_success_probability' => round($finalProbability, 4),
            'base_rate' => round($baseRate, 4),
            'adjustments' => round($adjustments, 4),
            'optimal_price' => 0.3,
            'optimal_frequency' => 'daily',
            'optimal_timing' => $this->calculateOptimalTimingEklektik($features, $predictionDate),
            'success_confidence' => $this->calculateConfidenceEklektik($features),
            'reasoning' => "Offre quotidienne Eklektik 0.3 TND - Club Privilèges",
            'model_version' => 'multi_operator_v2.1'
        ];
    }

    /**
     * Prédiction spécifique Ooredoo/DGV (mensuel 3.0 TND)
     */
    private function predictOoredoo(MLClientFeature $features, Carbon $predictionDate, string $offerType): array
    {
        $baseRate = $features->ooredoo_success_rate ?? 0;
        
        // Ajustements spécifiques Ooredoo/DGV
        $adjustments = 0;
        
        // Consistance mensuelle
        if (($features->ooredoo_monthly_consistency ?? 0) > 0.3) {
            $adjustments += 0.12; // +12% si habitude mensuelle
        }
        
        // Si client multi-opérateur avec bon Ooredoo
        if ($features->is_multi_operator_user && $baseRate > 0.2) {
            $adjustments += 0.08; // +8% expérience positive multi-op
        }
        
        // Préférence mensuelle et prix élevé
        if ($features->prefers_monthly_offers && $features->prefers_high_price) {
            $adjustments += 0.1; // +10% profil premium mensuel
        }
        
        // Timing mensuel (début/fin de mois)
        $dayOfMonth = $predictionDate->day;
        if ($dayOfMonth <= 3 || $dayOfMonth > 28) {
            $adjustments += 0.15; // +15% période de paiement mensuel
        }

        $finalProbability = max(0, min(1, $baseRate + $adjustments));
        
        return [
            'client_id' => $features->client_id,
            'operator' => 'ooredoo',
            'offer_type' => 'monthly',
            'prediction_date' => $predictionDate->toDateString(),
            'payment_success_probability' => round($finalProbability, 4),
            'base_rate' => round($baseRate, 4),
            'adjustments' => round($adjustments, 4),
            'optimal_price' => 3.0,
            'optimal_frequency' => 'monthly',
            'optimal_timing' => $this->calculateOptimalTimingOoredoo($features, $predictionDate),
            'success_confidence' => $this->calculateConfidenceOoredoo($features),
            'reasoning' => "Offre mensuelle Ooredoo/DGV 3.0 TND - patterns mensuels",
            'model_version' => 'multi_operator_v2.1'
        ];
    }

    /**
     * Recommande le meilleur opérateur pour un client
     */
    private function predictOptimalOperator(MLClientFeature $features, Carbon $predictionDate): array
    {
        // Calculer la probabilité pour chaque opérateur
        $timwePred = $this->predictTimwe($features, $predictionDate, 'monthly');
        $eklektikPred = $this->predictEklektik($features, $predictionDate, 'daily');
        $ooredooPred = $this->predictOoredoo($features, $predictionDate, 'monthly');
        
        // Sélectionner le meilleur
        $predictions = [
            'timwe' => $timwePred,
            'eklektik' => $eklektikPred,
            'ooredoo' => $ooredooPred
        ];
        
        $bestOperator = 'timwe';
        $bestProbability = 0;
        
        foreach ($predictions as $op => $pred) {
            if ($pred['payment_success_probability'] > $bestProbability) {
                $bestProbability = $pred['payment_success_probability'];
                $bestOperator = $op;
            }
        }
        
        $bestPrediction = $predictions[$bestOperator];
        $bestPrediction['alternative_operators'] = array_map(function($op, $pred) {
            return [
                'operator' => $op,
                'probability' => $pred['payment_success_probability'],
                'price' => $pred['optimal_price'],
                'frequency' => $pred['optimal_frequency']
            ];
        }, array_keys($predictions), $predictions);
        
        $bestPrediction['recommendation_reasoning'] = "Meilleur opérateur sélectionné: $bestOperator ({$bestProbability}% succès attendu)";
        
        return $bestPrediction;
    }

    /**
     * Détermine le type d'offre optimal selon le profil client
     */
    private function determineOptimalOfferType(MLClientFeature $features, string $operator): string
    {
        $preferredFreq = $features->preferred_frequency ?? 'unknown';
        $pricePreference = $features->price_preference ?? 'unknown';
        
        // Logique par opérateur
        switch ($operator) {
            case 'eklektik':
                return 'daily'; // Eklektik = toujours quotidien
                
            case 'timwe':
            case 'ooredoo':
                // Mensuel par défaut, sauf si forte préférence quotidienne
                return $preferredFreq === 'daily' && $pricePreference === 'low' ? 'daily' : 'monthly';
                
            default:
                // Auto: selon les préférences du client
                if ($preferredFreq === 'daily') return 'daily';
                if ($preferredFreq === 'monthly') return 'monthly';
                return 'monthly'; // Default
        }
    }

    /**
     * Timing optimal spécifique Timwe (considère les patterns mensuels)
     */
    private function calculateOptimalTimingTimwe(MLClientFeature $features, Carbon $baseDate): string
    {
        $optimal = $baseDate->copy();
        
        // Pour Timwe mensuel: cibler fin/début de mois
        $dayOfMonth = $baseDate->day;
        if ($dayOfMonth < 25) {
            // Attendre la fin du mois
            $optimal = $baseDate->copy()->endOfMonth()->subDays(2); // 29-30 du mois
        }
        
        // Heure optimale Timwe
        $optimalHour = $features->best_billing_hour ?? 14;
        $optimal->setTime($optimalHour, 0, 0);
        
        return $optimal->toDateTimeString();
    }

    /**
     * Timing optimal spécifique Eklektik (considère les patterns quotidiens)
     */
    private function calculateOptimalTimingEklektik(MLClientFeature $features, Carbon $baseDate): string
    {
        $optimal = $baseDate->copy();
        
        // Pour Eklektik quotidien: prochaine heure favorable
        $currentHour = $baseDate->hour;
        $optimalHour = 9; // Défaut matin business
        
        // Utiliser les patterns temporels du client
        if (($features->morning_success_rate ?? 0) > 0.3) {
            $optimalHour = 10; // Milieu de matinée
        } elseif (($features->afternoon_success_rate ?? 0) > 0.3) {
            $optimalHour = 15; // Milieu d'après-midi
        } elseif (($features->evening_success_rate ?? 0) > 0.3) {
            $optimalHour = 19; // Début de soirée
        }
        
        // Si on est passé l'heure optimale, prendre demain
        if ($currentHour >= $optimalHour) {
            $optimal->addDay();
        }
        
        $optimal->setTime($optimalHour, 0, 0);
        
        return $optimal->toDateTimeString();
    }

    /**
     * Timing optimal spécifique Ooredoo (considère les patterns mensuels + solde)
     */
    private function calculateOptimalTimingOoredoo(MLClientFeature $features, Carbon $baseDate): string
    {
        $optimal = $baseDate->copy();
        
        // Pour Ooredoo mensuel: éviter milieu de mois, cibler début/fin
        $dayOfMonth = $baseDate->day;
        if ($dayOfMonth > 5 && $dayOfMonth <= 25) {
            // Attendre la fin du mois ou début du suivant
            if ($dayOfMonth > 15) {
                $optimal = $baseDate->copy()->endOfMonth()->addDays(1); // 1er du mois suivant
            } else {
                $optimal = $baseDate->copy()->endOfMonth()->subDays(1); // Fin du mois
            }
        }
        
        // Heure optimale (après-midi pour Ooredoo)
        $optimalHour = 15; // 15h = après le déjeuner
        if (($features->afternoon_success_rate ?? 0) > 0.4) {
            $optimalHour = 16;
        }
        
        $optimal->setTime($optimalHour, 0, 0);
        
        return $optimal->toDateTimeString();
    }

    /**
     * Confiance spécifique Timwe
     */
    private function calculateConfidenceTimwe(MLClientFeature $features): float
    {
        $confidence = 0.4;
        
        // Données historiques Timwe
        $attempts = $features->timwe_total_attempts ?? 0;
        if ($attempts >= 10) $confidence += 0.2;
        elseif ($attempts >= 5) $confidence += 0.1;
        
        // Stabilité NO_BALANCE
        $noBalanceRate = $features->timwe_no_balance_rate ?? 0;
        if ($noBalanceRate < 0.2) $confidence += 0.15;
        elseif ($noBalanceRate < 0.5) $confidence += 0.05;
        
        // Préférence mensuelle
        if ($features->prefers_monthly_offers) $confidence += 0.1;
        
        return round(min(0.9, $confidence), 4);
    }

    /**
     * Confiance spécifique Eklektik
     */
    private function calculateConfidenceEklektik(MLClientFeature $features): float
    {
        $confidence = 0.3;
        
        // Consistance quotidienne
        $consistency = $features->eklektik_daily_consistency ?? 0;
        $confidence += $consistency * 0.3; // Max +30%
        
        // Préférence prix bas
        if ($features->prefers_low_price) $confidence += 0.2;
        
        // Préférence quotidienne
        if ($features->prefers_daily_offers) $confidence += 0.15;
        
        return round(min(0.85, $confidence), 4);
    }

    /**
     * Confiance spécifique Ooredoo
     */
    private function calculateConfidenceOoredoo(MLClientFeature $features): float
    {
        $confidence = 0.35;
        
        // Consistance mensuelle
        $consistency = $features->ooredoo_monthly_consistency ?? 0;
        $confidence += $consistency * 0.25;
        
        // Multi-opérateur = plus de données
        if ($features->is_multi_operator_user) $confidence += 0.15;
        
        // Spécialiste opérateur
        if ($features->best_performing_operator === 'ooredoo') $confidence += 0.2;
        
        return round(min(0.9, $confidence), 4);
    }

    /**
     * Recommandation de stratégie multi-opérateur
     */
    public function recommendOptimalStrategy(int $clientId): array
    {
        $features = MLClientFeature::getLatestForClient($clientId);
        
        if (!$features) {
            return ['strategy' => 'no_data', 'recommendations' => []];
        }

        // Analyser les performances par opérateur
        $operatorScores = [
            'timwe' => ($features->timwe_success_rate ?? 0) * ($features->timwe_has_activity ? 1 : 0.1),
            'eklektik' => ($features->eklektik_success_rate ?? 0) * ($features->eklektik_has_activity ? 1 : 0.1),
            'ooredoo' => ($features->ooredoo_success_rate ?? 0) * ($features->ooredoo_has_activity ? 1 : 0.1)
        ];

        arsort($operatorScores); // Trier par score décroissant
        
        $recommendations = [];
        $strategy = 'single_operator';
        
        // Stratégie selon les scores
        $topScore = reset($operatorScores);
        $topOperator = array_key_first($operatorScores);
        
        if ($topScore > 0.5) {
            $strategy = 'focus_best';
            $recommendations[] = [
                'type' => 'focus',
                'operator' => $topOperator,
                'reason' => "Excellent taux de succès sur $topOperator (" . round($topScore * 100, 1) . "%)",
                'action' => 'Concentrer les efforts sur cet opérateur'
            ];
        } elseif (count(array_filter($operatorScores, fn($s) => $s > 0.1)) >= 2) {
            $strategy = 'multi_operator_diversified';
            $recommendations[] = [
                'type' => 'diversify',
                'operators' => array_keys(array_filter($operatorScores, fn($s) => $s > 0.1)),
                'reason' => 'Performance correcte sur plusieurs opérateurs',
                'action' => 'Tester alternativement selon les patterns temporels'
            ];
        } else {
            $strategy = 'experimental';
            $recommendations[] = [
                'type' => 'experiment',
                'suggestion' => 'Commencer par Eklektik (bas risque 0.3 TND)',
                'reason' => 'Peu d\'historique, test avec offre low-cost',
                'action' => 'A/B test Eklektik quotidien vs Timwe mensuel'
            ];
        }

        // Recommandation de timing selon préférences
        if ($features->preferred_frequency === 'daily') {
            $recommendations[] = [
                'type' => 'frequency',
                'suggestion' => 'Privilégier Eklektik (quotidien)',
                'reason' => 'Profil compatible avec offres fréquentes'
            ];
        } elseif ($features->preferred_frequency === 'monthly') {
            $recommendations[] = [
                'type' => 'frequency', 
                'suggestion' => 'Privilégier Timwe/Ooredoo (mensuel)',
                'reason' => 'Profil compatible avec offres mensuelles'
            ];
        }

        return [
            'client_id' => $clientId,
            'strategy' => $strategy,
            'operator_scores' => $operatorScores,
            'recommendations' => $recommendations,
            'best_operator' => $topOperator,
            'diversification_potential' => $features->is_multi_operator_user ? 'high' : 'medium',
            'risk_tolerance' => $features->prefers_low_price ? 'low' : 'medium'
        ];
    }

    private function getDefaultPrediction(string $operator, string $offerType): array
    {
        return [
            'payment_success_probability' => 0.09,
            'operator' => $operator,
            'offer_type' => $offerType,
            'optimal_price' => $operator === 'eklektik' ? 0.3 : 3.0,
            'optimal_frequency' => $operator === 'eklektik' ? 'daily' : 'monthly',
            'success_confidence' => 0.3,
            'model_version' => 'multi_operator_default'
        ];
    }
}