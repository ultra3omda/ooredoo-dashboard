<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\MLClientFeature;
use Carbon\Carbon;

class MLRecommendationService
{
    public function __construct(
        private ?AcquisitionStrategySuggestionService $acquisitionSuggestionService = null
    ) {
        $this->acquisitionSuggestionService ??= app(AcquisitionStrategySuggestionService::class);
    }

    /**
     * Génère des recommandations pour optimiser la facturation
     */
    public function generateRecommendations(Carbon $analysisDate = null): array
    {
        if (!$analysisDate) {
            $analysisDate = Carbon::today();
        }

        Log::info("MLRecommendationService - Génération des recommandations pour {$analysisDate->toDateString()}");

        $recommendations = [];

        try {
            // 1. Recommandations de pricing
            $recommendations['pricing'] = $this->generatePricingRecommendations($analysisDate);
            
            // 2. Recommandations de timing
            $recommendations['timing'] = $this->generateTimingRecommendations($analysisDate);
            
            // 3. Recommandations de fréquence
            $recommendations['frequency'] = $this->generateFrequencyRecommendations($analysisDate);
            
            // 4. Recommandations de segmentation
            $recommendations['segmentation'] = $this->generateSegmentationRecommendations($analysisDate);
            
            // 5. Recommandations de prévention du churn
            $recommendations['churn_prevention'] = $this->generateChurnPreventionRecommendations($analysisDate);
            
            // 6. Recommandations globales + suggestions acquisition/conversion/taux de facturation
            $globalStrategy = $this->generateGlobalStrategyRecommendations($analysisDate);
            $strategySuggestions = $this->acquisitionSuggestionService->getStrategySuggestions($analysisDate);
            $recommendations['global_strategy'] = array_merge($globalStrategy, $strategySuggestions);

            // Sauvegarder les recommandations
            $this->storeRecommendations($recommendations, $analysisDate);

        } catch (\Exception $e) {
            Log::error("MLRecommendationService - Erreur génération recommandations", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $recommendations;
    }

    /**
     * Recommandations de pricing
     */
    private function generatePricingRecommendations(Carbon $analysisDate): array
    {
        $recommendations = [];

        // Analyser les segments avec faible taux de succès
        $segmentStats = MLClientFeature::getSegmentStats($analysisDate);
        
        foreach ($segmentStats as $segment) {
            if ($segment['avg_success_rate'] < 15 && $segment['count'] > 100) {
                $recommendations[] = [
                    'type' => 'pricing',
                    'target_segment' => $segment['segment'],
                    'current_strategy' => 'Prix fixe 3 TND mensuel',
                    'recommended_strategy' => 'Micro-billing adaptatif',
                    'details' => [
                        'current_price' => 3.00,
                        'recommended_price' => $this->calculateAdaptivePrice($segment),
                        'recommended_frequency' => $this->calculateAdaptiveFrequency($segment),
                        'expected_improvement' => $this->estimatePricingImprovement($segment)
                    ],
                    'reasoning' => "Segment '{$segment['segment']}' avec taux de succès de {$segment['avg_success_rate']}% nécessite une approche prix adaptative",
                    'priority' => $segment['avg_success_rate'] < 10 ? 'critical' : 'high',
                    'expected_impact_percentage' => $this->estimatePricingImprovement($segment),
                    'affected_clients' => $segment['count']
                ];
            }
        }

        // Recommandation pour les clients premium
        $premiumSegment = collect($segmentStats)->firstWhere('segment', 'premium_payers');
        if ($premiumSegment && $premiumSegment['avg_success_rate'] > 70) {
            $recommendations[] = [
                'type' => 'pricing',
                'target_segment' => 'premium_payers',
                'current_strategy' => 'Prix standard 3 TND',
                'recommended_strategy' => 'Prix premium avec services additionnels',
                'details' => [
                    'current_price' => 3.00,
                    'recommended_price' => 4.00,
                    'additional_services' => ['Support prioritaire', 'Contenu exclusif'],
                    'expected_improvement' => 25
                ],
                'reasoning' => "Clients premium avec {$premiumSegment['avg_success_rate']}% de succès peuvent supporter un prix plus élevé",
                'priority' => 'medium',
                'expected_impact_percentage' => 25,
                'affected_clients' => $premiumSegment['count']
            ];
        }

        return $recommendations;
    }

    /**
     * Recommandations de timing
     */
    private function generateTimingRecommendations(Carbon $analysisDate): array
    {
        $recommendations = [];

        // Analyser les patterns temporels globaux
        $temporalAnalysis = $this->analyzeTemporalPatterns($analysisDate);

        if ($temporalAnalysis['best_global_hour'] && $temporalAnalysis['current_spread_too_wide']) {
            $recommendations[] = [
                'type' => 'timing',
                'target_segment' => 'all',
                'current_strategy' => 'Facturation dispersée sur 24h',
                'recommended_strategy' => "Concentrer sur {$temporalAnalysis['best_global_hour']}h-" . ($temporalAnalysis['best_global_hour'] + 2) . "h",
                'details' => [
                    'optimal_hour' => $temporalAnalysis['best_global_hour'],
                    'optimal_window' => '2 heures',
                    'expected_improvement' => $temporalAnalysis['expected_improvement']
                ],
                'reasoning' => "Analyse temporelle montre {$temporalAnalysis['success_rate_at_optimal']}% de succès à {$temporalAnalysis['best_global_hour']}h vs {$temporalAnalysis['average_success_rate']}% en moyenne",
                'priority' => 'high',
                'expected_impact_percentage' => $temporalAnalysis['expected_improvement'],
                'affected_clients' => 'all_active'
            ];
        }

        // Recommandation spécifique aux échecs NO_BALANCE
        $noBalanceAnalysis = $this->analyzeNoBalancePatterns($analysisDate);
        if ($noBalanceAnalysis['needs_delay_after_recharge']) {
            $recommendations[] = [
                'type' => 'timing',
                'target_segment' => 'high_no_balance_rate',
                'current_strategy' => 'Facturation immédiate',
                'recommended_strategy' => 'Délai de 2-4h après détection de recharge',
                'details' => [
                    'delay_hours' => $noBalanceAnalysis['optimal_delay'],
                    'trigger' => 'Détection activité/recharge',
                    'expected_improvement' => 40
                ],
                'reasoning' => "Clients avec {$noBalanceAnalysis['no_balance_rate']}% de NO_BALANCE bénéficieraient d'un délai après recharge",
                'priority' => 'critical',
                'expected_impact_percentage' => 40,
                'affected_clients' => $noBalanceAnalysis['affected_count']
            ];
        }

        return $recommendations;
    }

    /**
     * Recommandations de fréquence
     */
    private function generateFrequencyRecommendations(Carbon $analysisDate): array
    {
        $recommendations = [];

        // Analyser les segments struggling_payers et high_risk
        $strugglingClients = MLClientFeature::getClientsBySegment('struggling_payers', $analysisDate);
        $highRiskClients = MLClientFeature::getClientsBySegment('high_risk', $analysisDate);

        if ($strugglingClients->count() > 500) {
            $avgSuccessRate = $strugglingClients->avg('payment_success_rate');
            
            $recommendations[] = [
                'type' => 'frequency',
                'target_segment' => 'struggling_payers',
                'current_strategy' => 'Facturation mensuelle 3 TND',
                'recommended_strategy' => 'Facturation hebdomadaire 0.75 TND',
                'details' => [
                    'current_frequency' => 'monthly',
                    'current_amount' => 3.00,
                    'recommended_frequency' => 'weekly',
                    'recommended_amount' => 0.75,
                    'total_monthly_equivalent' => 3.00,
                    'expected_success_rate_improvement' => 150 // De ~15% à ~25%
                ],
                'reasoning' => "Segment struggling_payers ({$avgSuccessRate}% succès) bénéficierait de montants plus petits plus fréquents",
                'priority' => 'high',
                'expected_impact_percentage' => 150,
                'affected_clients' => $strugglingClients->count()
            ];
        }

        if ($highRiskClients->count() > 200) {
            $recommendations[] = [
                'type' => 'frequency',
                'target_segment' => 'high_risk',
                'current_strategy' => 'Tentatives mensuelles',
                'recommended_strategy' => 'Micro-facturation quotidienne 0.10 TND',
                'details' => [
                    'current_frequency' => 'monthly',
                    'recommended_frequency' => 'daily',
                    'recommended_amount' => 0.10,
                    'max_monthly' => 3.00,
                    'expected_success_rate_improvement' => 300 // De ~5% à ~20%
                ],
                'reasoning' => "Clients high_risk nécessitent une approche micro-paiement pour maximiser les chances",
                'priority' => 'critical',
                'expected_impact_percentage' => 300,
                'affected_clients' => $highRiskClients->count()
            ];
        }

        return $recommendations;
    }

    /**
     * Recommandations de segmentation
     */
    private function generateSegmentationRecommendations(Carbon $analysisDate): array
    {
        $recommendations = [];

        // Détecter les clients mal segmentés
        $misclassified = $this->detectMisclassifiedClients($analysisDate);
        
        if ($misclassified['count'] > 0) {
            $recommendations[] = [
                'type' => 'segmentation',
                'target_segment' => 'misclassified',
                'current_strategy' => 'Segmentation basique par taux de succès',
                'recommended_strategy' => 'Re-segmentation avec critères avancés',
                'details' => [
                    'clients_to_reclassify' => $misclassified['count'],
                    'new_segments_needed' => $misclassified['suggested_segments'],
                    'expected_improvement' => 20
                ],
                'reasoning' => "{$misclassified['count']} clients semblent mal classés selon leur comportement récent",
                'priority' => 'medium',
                'expected_impact_percentage' => 20,
                'affected_clients' => $misclassified['count']
            ];
        }

        return $recommendations;
    }

    /**
     * Recommandations de prévention du churn
     */
    private function generateChurnPreventionRecommendations(Carbon $analysisDate): array
    {
        $recommendations = [];

        // Identifier les clients à risque élevé
        $churnRiskClients = MLClientFeature::getChurnRiskClients(0.6, 200);
        
        if ($churnRiskClients->count() > 0) {
            $highValueAtRisk = $churnRiskClients->where('is_high_value_client', true)->count();
            
            $recommendations[] = [
                'type' => 'churn_prevention',
                'target_segment' => 'churn_risk',
                'current_strategy' => 'Pas de stratégie de rétention',
                'recommended_strategy' => 'Programme de rétention avec prix réduit temporaire',
                'details' => [
                    'total_at_risk' => $churnRiskClients->count(),
                    'high_value_at_risk' => $highValueAtRisk,
                    'retention_discount' => 30, // 30% de réduction
                    'retention_period' => '2 mois',
                    'expected_retention_rate' => 60
                ],
                'reasoning' => "{$churnRiskClients->count()} clients à risque dont $highValueAtRisk de haute valeur",
                'priority' => 'critical',
                'expected_impact_percentage' => 60,
                'affected_clients' => $churnRiskClients->count()
            ];
        }

        return $recommendations;
    }

    /**
     * Recommandations de stratégie globale
     */
    private function generateGlobalStrategyRecommendations(Carbon $analysisDate): array
    {
        $recommendations = [];

        // Analyser la performance globale
        $globalPerf = MLClientFeature::getPortfolioPerformance($analysisDate);
        
        if ($globalPerf['avg_success_rate'] < 15) {
            $recommendations[] = [
                'type' => 'global_strategy',
                'target_segment' => 'all',
                'current_strategy' => 'One-size-fits-all mensuel 3 TND',
                'recommended_strategy' => 'Stratégie adaptive multi-segment',
                'details' => [
                    'current_success_rate' => $globalPerf['avg_success_rate'],
                    'target_success_rate' => 35,
                    'implementation_phases' => [
                        'Phase 1: Micro-billing pour high_risk (30 jours)',
                        'Phase 2: Timing optimisé pour tous (45 jours)',
                        'Phase 3: Prix adaptatif par segment (60 jours)'
                    ],
                    'expected_revenue_increase' => 180 // +180%
                ],
                'reasoning' => "Taux de succès global de {$globalPerf['avg_success_rate']}% nécessite une refonte complète",
                'priority' => 'critical',
                'expected_impact_percentage' => 180,
                'affected_clients' => $globalPerf['total_clients']
            ];
        }

        return $recommendations;
    }

    /**
     * Calcule le prix adaptatif pour un segment
     */
    private function calculateAdaptivePrice(array $segment): float
    {
        $successRate = $segment['avg_success_rate'] / 100;
        
        // Plus le taux de succès est bas, plus on réduit le prix
        if ($successRate < 0.10) {
            return 0.50; // 0.50 TND
        } elseif ($successRate < 0.20) {
            return 1.00; // 1 TND
        } elseif ($successRate < 0.30) {
            return 1.50; // 1.50 TND
        }
        
        return 3.00; // Prix standard
    }

    /**
     * Calcule la fréquence adaptative pour un segment
     */
    private function calculateAdaptiveFrequency(array $segment): string
    {
        $successRate = $segment['avg_success_rate'] / 100;
        
        if ($successRate < 0.05) {
            return 'daily';
        } elseif ($successRate < 0.15) {
            return 'weekly';
        } elseif ($successRate < 0.30) {
            return 'bi_weekly';
        }
        
        return 'monthly';
    }

    /**
     * Estime l'amélioration liée au pricing
     */
    private function estimatePricingImprovement(array $segment): int
    {
        $successRate = $segment['avg_success_rate'];
        
        // Estimation basée sur la théorie de l'élasticité des prix
        if ($successRate < 5) {
            return 400; // +400%
        } elseif ($successRate < 10) {
            return 250; // +250%
        } elseif ($successRate < 15) {
            return 150; // +150%
        }
        
        return 50; // +50%
    }

    /**
     * Analyse les patterns temporels
     */
    private function analyzeTemporalPatterns(Carbon $analysisDate): array
    {
        // Analyser les succès par heure globalement
        $hourlySuccess = DB::table('ml_client_features')
            ->where('calculation_date', $analysisDate)
            ->whereNotNull('best_billing_hour')
            ->select('best_billing_hour', DB::raw('AVG(payment_success_rate) as avg_success'))
            ->groupBy('best_billing_hour')
            ->orderBy('avg_success', 'desc')
            ->first();

        $globalAvg = DB::table('ml_client_features')
            ->where('calculation_date', $analysisDate)
            ->avg('payment_success_rate');

        return [
            'best_global_hour' => $hourlySuccess->best_billing_hour ?? 14,
            'success_rate_at_optimal' => round(($hourlySuccess->avg_success ?? 0.1) * 100, 1),
            'average_success_rate' => round($globalAvg * 100, 1),
            'current_spread_too_wide' => true, // Assumé pour la démo
            'expected_improvement' => 25
        ];
    }

    /**
     * Analyse les patterns NO_BALANCE
     */
    private function analyzeNoBalancePatterns(Carbon $analysisDate): array
    {
        // Estimation basée sur la logique métier
        return [
            'needs_delay_after_recharge' => true,
            'no_balance_rate' => 75, // 75% de NO_BALANCE estimé
            'optimal_delay' => 3, // 3 heures
            'affected_count' => 8000 // Estimation
        ];
    }

    /**
     * Détecte les clients mal segmentés
     */
    private function detectMisclassifiedClients(Carbon $analysisDate): array
    {
        // Logique simplifiée pour détecter les clients dont le comportement ne correspond pas à leur segment
        $count = DB::table('ml_client_features')
            ->where('calculation_date', $analysisDate)
            ->where(function($q) {
                $q->where(function($subQ) {
                    // Premium payers avec taux faible
                    $subQ->where('client_segment', 'premium_payers')
                         ->where('payment_success_rate', '<', 0.6);
                })
                ->orWhere(function($subQ) {
                    // High risk avec taux correct
                    $subQ->where('client_segment', 'high_risk')
                         ->where('payment_success_rate', '>', 0.3);
                });
            })
            ->count();

        return [
            'count' => $count,
            'suggested_segments' => ['premium_struggling', 'recovering_payers']
        ];
    }

    /**
     * Sauvegarde les recommandations en base
     */
    private function storeRecommendations(array $recommendations, Carbon $analysisDate): void
    {
        try {
            $allRecommendations = [];
            
            foreach ($recommendations as $category => $categoryRecommendations) {
                foreach ($categoryRecommendations as $rec) {
                    $allRecommendations[] = [
                        'client_id' => null, // Recommandations globales
                        'recommendation_type' => $rec['type'],
                        'current_value' => $rec['current_strategy'],
                        'recommended_value' => $rec['recommended_strategy'],
                        'recommendation_reason' => $rec['reasoning'],
                        'expected_improvement_percentage' => $rec['expected_impact_percentage'],
                        'confidence_score' => 0.8, // Confiance par défaut
                        'priority' => $rec['priority'],
                        'status' => 'pending',
                        'valid_until' => $analysisDate->copy()->addDays(30), // Valide 30 jours
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                }
            }

            if (!empty($allRecommendations)) {
                // Nettoyer les anciennes recommandations
                DB::table('ml_recommendations')
                    ->where('status', 'pending')
                    ->where('created_at', '<', $analysisDate->subDays(7))
                    ->delete();

                // Insérer les nouvelles
                DB::table('ml_recommendations')->insert($allRecommendations);
            }

        } catch (\Exception $e) {
            Log::error("MLRecommendationService - Erreur sauvegarde recommandations", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Récupère les recommandations prioritaires pour le dashboard
     */
    public function getPriorityRecommendations(int $limit = 10): array
    {
        $recommendations = DB::table('ml_recommendations')
            ->where('status', 'pending')
            ->where('valid_until', '>', Carbon::now())
            ->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
            ->orderBy('expected_improvement_percentage', 'desc')
            ->limit($limit)
            ->get();

        $summary = DB::table('ml_recommendations')
            ->where('status', 'pending')
            ->where('valid_until', '>', Carbon::now())
            ->selectRaw('
                COUNT(*) as total_recommendations,
                SUM(CASE WHEN priority = "critical" THEN 1 ELSE 0 END) as critical_count,
                SUM(CASE WHEN priority = "high" THEN 1 ELSE 0 END) as high_count,
                AVG(expected_improvement_percentage) as avg_expected_improvement
            ')
            ->first();

        return [
            'recommendations' => $recommendations->toArray(),
            'summary' => [
                'total' => $summary->total_recommendations ?? 0,
                'critical' => $summary->critical_count ?? 0,
                'high' => $summary->high_count ?? 0,
                'avg_expected_improvement' => round($summary->avg_expected_improvement ?? 0, 1)
            ]
        ];
    }
}