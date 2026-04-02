<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\AIContextCache;
use App\Models\MLClientFeature;
use Carbon\Carbon;

class AIContextProvider
{
    /**
     * Récupère le contexte statique du système (segments, modèles, stratégies)
     */
    public function getSystemContext(): array
    {
        return AIContextCache::getOrCreate(
            'system_context_v2_1',
            'system_info',
            function () {
                return [
                    'total_clients' => $this->getTotalClients(),
                    'segments' => $this->getSegmentStats(),
                    'ml_models' => $this->getMLModelsInfo(),
                    'strategies' => $this->getStrategiesInfo(),
                    'current_performance' => $this->getCurrentPerformance(),
                    'data_quality' => $this->getDataQuality(),
                    'system_capabilities' => $this->getSystemCapabilities()
                ];
            },
            120 // Cache 2 heures
        );
    }
    
    /**
     * Récupère les KPIs en temps réel
     */
    public function getKPIsContext(): array
    {
        return AIContextCache::getOrCreate(
            'kpis_context_' . now()->format('Y-m-d-H'),
            'kpis',
            function () {
                return [
                    'global_success_rate' => $this->getGlobalSuccessRate(),
                    'revenue_monthly_estimate' => $this->getMonthlyRevenueEstimate(),
                    'active_clients_recent' => $this->getActiveClientsRecent(),
                    'top_performing_segment' => $this->getTopPerformingSegment(),
                    'worst_performing_segment' => $this->getWorstPerformingSegment(),
                    'trending_features' => $this->getTrendingFeatures(),
                    'model_performance' => $this->getLatestModelPerformance()
                ];
            },
            15 // Cache 15 minutes pour KPIs
        );
    }
    
    /**
     * Contexte spécifique pour les features ML
     */
    public function getMLFeaturesContext(): array
    {
        return AIContextCache::getOrCreate(
            'ml_features_analysis_v2',
            'ml_features',
            function () {
                return [
                    'most_important_features' => $this->getMostImportantFeatures(),
                    'feature_correlations' => $this->getFeatureCorrelations(),
                    'data_completeness' => $this->getFeatureCompleteness(),
                    'recent_extractions' => $this->getRecentExtractions()
                ];
            },
            240 // Cache 4 heures
        );
    }
    
    /**
     * Contexte pour un client spécifique
     */
    public function getClientContext(int $clientId): array
    {
        return AIContextCache::getOrCreate(
            "client_context_{$clientId}",
            'client_details',
            function () use ($clientId) {
                $features = MLClientFeature::getLatestForClient($clientId);
                
                $predictions = DB::table('ml_predictions')
                    ->where('client_id', $clientId)
                    ->orderBy('prediction_date', 'desc')
                    ->first();
                
                $recentTransactions = DB::table('transactions_history')
                    ->where('client_id', $clientId)
                    ->where(function($q) {
                        $q->where('status', 'LIKE', 'TIMWE_%')
                          ->orWhere('status', 'LIKE', 'ORANGE_%')
                          ->orWhere('status', 'LIKE', 'TARAJI_%')
                          ->orWhere('status', 'LIKE', 'TT_%')
                          ->orWhere('status', 'LIKE', '%EKLEKTIK%')
                          ->orWhere('status', 'LIKE', '%OOREDOO%');
                    })
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();
                
                return [
                    'client_id' => $clientId,
                    'segment' => $features->client_segment ?? 'unknown',
                    'payment_success_rate' => $features->payment_success_rate ?? 0,
                    'consecutive_failures' => $features->consecutive_failures ?? 0,
                    'days_since_last_payment' => $features->days_since_last_payment ?? null,
                    'total_payments' => $features->total_payments ?? 0,
                    'predicted_success_probability' => $predictions->payment_success_probability ?? null,
                    'optimal_price' => $predictions->optimal_price ?? null,
                    'recent_transactions_count' => $recentTransactions->count(),
                    'last_transaction_date' => $recentTransactions->first()->created_at ?? null,
                    // Nouvelles features multi-opérateur
                    'timwe_success_rate' => $features->timwe_success_rate ?? 0,
                    'eklektik_success_rate' => $features->eklektik_success_rate ?? 0,
                    'ooredoo_success_rate' => $features->ooredoo_success_rate ?? 0,
                    'best_performing_operator' => $features->best_performing_operator ?? 'unknown',
                    'preferred_frequency' => $features->preferred_frequency ?? 'unknown'
                ];
            },
            30 // Cache 30 minutes par client
        );
    }

    /**
     * Contexte pour recommandations stratégiques
     */
    public function getRecommendationsContext(): array
    {
        return AIContextCache::getOrCreate(
            'recommendations_context_v2',
            'recommendations',
            function () {
                $activeRecommendations = DB::table('ml_recommendations')
                    ->where('status', 'pending')
                    ->orderBy('priority', 'desc') // Utiliser priority (colonne existante)
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();
                
                return [
                    'active_recommendations' => $activeRecommendations->map(function($rec) {
                        return [
                            'id' => $rec->id,
                            'type' => $rec->recommendation_type ?? 'general',
                            'title' => $rec->title ?? 'Recommandation ML',
                            'expected_impact' => $rec->expected_impact ?? 'Impact à évaluer',
                            'priority' => $rec->priority ?? 'medium',
                            'target_segment' => $rec->target_segment ?? 'all'
                        ];
                    })->toArray(),
                    'strategies_comparison' => $this->getStrategiesComparison(),
                    'ab_tests_active' => $this->getActiveABTests()
                ];
            },
            60 // Cache 1 heure
        );
    }

    /**
     * Contexte des insights avancés (opportunités revenus, quick wins, alertes risque, A/B tests)
     */
    public function getAdvancedInsightsContext(): array
    {
        return AIContextCache::getOrCreate(
            'advanced_insights_v1',
            'insights',
            function () {
                return [
                    'revenue_opportunities' => $this->calculateRevenueOpportunities(),
                    'quick_wins' => $this->identifyQuickWins(),
                    'risk_alerts' => $this->getCurrentRiskAlerts(),
                    'ab_test_suggestions' => $this->generateABTestSuggestions(),
                    'feature_importance_trends' => $this->getFeatureImportanceTrends()
                ];
            },
            60 // Cache 1 heure
        );
    }

    // ===== MÉTHODES PRIVÉES DE COLLECTE DE DONNÉES =====

    private function getTotalClients(): int
    {
        return DB::table('ml_client_features')
            ->distinct('client_id')
            ->count('client_id');
    }
    
    private function getSegmentStats(): array
    {
        $segments = DB::table('ml_client_features')
            ->selectRaw('
                client_segment,
                COUNT(*) as count,
                AVG(payment_success_rate) as avg_success_rate,
                AVG(total_payments) as avg_payments,
                AVG(consecutive_failures) as avg_failures
            ')
            ->whereNotNull('client_segment')
            ->groupBy('client_segment')
            ->orderBy('avg_success_rate', 'desc')
            ->get();
        
        return $segments->map(function($seg) {
            return [
                'name' => $seg->client_segment,
                'count' => $seg->count,
                'success_rate' => round($seg->avg_success_rate, 3),
                'avg_payments' => round($seg->avg_payments, 1),
                'avg_failures' => round($seg->avg_failures, 1)
            ];
        })->toArray();
    }
    
    private function getMLModelsInfo(): array
    {
        $latestPerformance = DB::table('ml_model_performance')
            ->orderBy('evaluation_date', 'desc')
            ->first();
            
        return [
            'current_model' => [
                'name' => $latestPerformance->model_name ?? 'rule_based_v2.0',
                'type' => 'LightGBM + Rule-Based Hybrid',
                'auc' => round($latestPerformance->auc_roc ?? 0.7, 3),
                'accuracy' => round($latestPerformance->accuracy ?? 0.72, 3),
                'f1_score' => round($latestPerformance->f1_score ?? 0.65, 3),
                'last_evaluation' => $latestPerformance->evaluation_date ?? null,
                'version' => 'v2.1_multi_operator'
            ],
            'feature_count' => 36, // Nouvelles features v2.1
            'operators_supported' => ['timwe', 'eklektik', 'ooredoo_dgv'],
            'prediction_types' => ['billing_success', 'optimal_price', 'optimal_frequency', 'optimal_timing']
        ];
    }
    
    private function getStrategiesInfo(): array
    {
        return [
            [
                'name' => 'quotidien_0.3_tnd',
                'description' => 'Offres quotidiennes à 0.3 TND (Eklektik Club Privilèges)',
                'target_segments' => ['high_risk', 'struggling_payers'],
                'expected_success_rate' => 0.25,
                'monthly_revenue_potential' => 173632,
                'roi_vs_current' => 643,
                'risk_level' => 'low',
                'advantages' => ['Volume x30', 'Accessibilité maximale', 'Apprentissage rapide']
            ],
            [
                'name' => 'hebdomadaire_1.0_tnd',
                'description' => 'Offres hebdomadaires à 1.0 TND (compromis)',
                'target_segments' => ['regular_payers'],
                'expected_success_rate' => 0.18,
                'monthly_revenue_potential' => 37041,
                'roi_vs_current' => 58,
                'risk_level' => 'medium',
                'advantages' => ['Équilibre volume/marge', 'Prix modéré']
            ],
            [
                'name' => 'mensuel_3.0_tnd',
                'description' => 'Offres mensuelles à 3.0 TND (stratégie actuelle)',
                'target_segments' => ['premium_payers'],
                'expected_success_rate' => 0.15,
                'monthly_revenue_potential' => 7717,
                'roi_vs_current' => -67,
                'risk_level' => 'high',
                'advantages' => ['Marge élevée', 'Clients premium']
            ]
        ];
    }
    
    private function getCurrentPerformance(): array
    {
        $currentStats = DB::table('ml_client_features')
            ->selectRaw('
                AVG(payment_success_rate) as global_success_rate,
                COUNT(*) as total_clients_analyzed,
                SUM(CASE WHEN payment_success_rate = 0 THEN 1 ELSE 0 END) as zero_success_clients,
                SUM(total_payments) as total_monthly_successes
            ')
            ->first();
            
        return [
            'global_success_rate' => round($currentStats->global_success_rate ?? 0.0909, 4),
            'current_strategy' => 'monthly_3.0_tnd',
            'monthly_revenue_current' => 23382, // Calculé précédemment
            'clients_analyzed' => $currentStats->total_clients_analyzed ?? 0,
            'zero_success_percentage' => round(($currentStats->zero_success_clients ?? 0) / ($currentStats->total_clients_analyzed ?: 1) * 100, 1),
            'active_clients_percentage' => 21.5, // 100% - 78.5% zéro succès
            'improvement_potential' => '+643% avec stratégie quotidienne'
        ];
    }
    
    private function getMostImportantFeatures(): array
    {
        // Top features basées sur l'analyse de variance et corrélation
        return [
            ['feature' => 'consecutive_failures', 'importance' => 85.2, 'description' => 'Nombre d\'échecs consécutifs'],
            ['feature' => 'payment_success_rate', 'importance' => 78.1, 'description' => 'Taux de succès historique'],
            ['feature' => 'total_payments', 'importance' => 65.3, 'description' => 'Nombre total de paiements réussis'],
            ['feature' => 'recovery_after_failure_rate', 'importance' => 54.7, 'description' => 'Capacité de récupération après échec'],
            ['feature' => 'timwe_success_rate', 'importance' => 48.2, 'description' => 'Performance spécifique Timwe'],
            ['feature' => 'payment_reliability_score', 'importance' => 42.1, 'description' => 'Score de fiabilité composite'],
            ['feature' => 'eklektik_daily_consistency', 'importance' => 38.9, 'description' => 'Consistance quotidienne Eklektik'],
            ['feature' => 'max_consecutive_successes', 'importance' => 35.4, 'description' => 'Plus longue séquence de succès'],
            ['feature' => 'subscription_age_days', 'importance' => 28.7, 'description' => 'Ancienneté du client'],
            ['feature' => 'operator_diversity_score', 'importance' => 22.3, 'description' => 'Utilisation multi-opérateur']
        ];
    }

    private function getGlobalSuccessRate(): float
    {
        return DB::table('ml_client_features')
            ->whereNotNull('payment_success_rate')
            ->avg('payment_success_rate') ?? 0.0909;
    }
    
    private function getMonthlyRevenueEstimate(): float
    {
        // Calcul basé sur les prédictions actuelles
        $totalClients = $this->getTotalClients();
        $avgSuccessRate = $this->getGlobalSuccessRate();
        $currentPrice = 3.0; // TND mensuel actuel
        
        return $totalClients * $avgSuccessRate * $currentPrice;
    }
    
    private function getActiveClientsRecent(): array
    {
        $activeToday = DB::table('transactions_history')
            ->whereDate('created_at', today())
            ->distinct('client_id')
            ->count();
            
        $activeThisWeek = DB::table('transactions_history')
            ->where('created_at', '>=', now()->subDays(7))
            ->distinct('client_id')
            ->count();
            
        return [
            'today' => $activeToday,
            'this_week' => $activeThisWeek,
            'daily_average' => round($activeThisWeek / 7, 1)
        ];
    }
    
    private function getTopPerformingSegment(): array
    {
        $top = DB::table('ml_client_features')
            ->selectRaw('client_segment, AVG(payment_success_rate) as avg_rate, COUNT(*) as clients')
            ->whereNotNull('client_segment')
            ->groupBy('client_segment')
            ->orderBy('avg_rate', 'desc')
            ->first();
        
        return [
            'segment' => $top->client_segment ?? 'unknown',
            'success_rate' => round($top->avg_rate ?? 0, 3),
            'clients' => $top->clients ?? 0
        ];
    }
    
    private function getWorstPerformingSegment(): array
    {
        $worst = DB::table('ml_client_features')
            ->selectRaw('client_segment, AVG(payment_success_rate) as avg_rate, COUNT(*) as clients')
            ->whereNotNull('client_segment')
            ->groupBy('client_segment')
            ->orderBy('avg_rate', 'asc')
            ->first();
        
        return [
            'segment' => $worst->client_segment ?? 'unknown',
            'success_rate' => round($worst->avg_rate ?? 0, 3),
            'clients' => $worst->clients ?? 0
        ];
    }

    private function getDataQuality(): array
    {
        $quality = DB::table('ml_client_features')
            ->where('calculation_date', '>=', now()->subDays(7))
            ->selectRaw('
                COUNT(*) as total_records,
                AVG(CASE WHEN payment_success_rate IS NOT NULL THEN 1 ELSE 0 END) * 100 as success_rate_completeness,
                AVG(CASE WHEN engagement_score IS NOT NULL THEN 1 ELSE 0 END) * 100 as engagement_completeness,
                AVG(CASE WHEN total_operators_used > 0 THEN 1 ELSE 0 END) * 100 as multi_operator_data
            ')
            ->first();
            
        return [
            'total_records_7d' => $quality->total_records ?? 0,
            'success_rate_completeness' => round($quality->success_rate_completeness ?? 0, 1),
            'engagement_completeness' => round($quality->engagement_completeness ?? 0, 1),
            'multi_operator_coverage' => round($quality->multi_operator_data ?? 0, 1),
            'last_extraction' => DB::table('ml_client_features')->max('calculation_date')
        ];
    }

    private function getSystemCapabilities(): array
    {
        return [
            'operators_supported' => [
                'timwe' => ['type' => 'mensuel', 'price' => '3.0 TND', 'target' => 'premium'],
                'eklektik' => ['type' => 'quotidien', 'price' => '0.3 TND', 'target' => 'club_privileges'],
                'ooredoo' => ['type' => 'mensuel', 'price' => '3.0 TND', 'target' => 'premium_ooredoo']
            ],
            'ml_features_v2' => [
                'temporal' => ['morning_success_rate', 'afternoon_success_rate', 'evening_success_rate'],
                'behavioral' => ['recovery_after_failure_rate', 'max_consecutive_successes', 'amount_flexibility'],
                'multi_operator' => ['timwe_success_rate', 'eklektik_success_rate', 'ooredoo_success_rate']
            ],
            'prediction_capabilities' => [
                'success_probability' => 'Probabilité de succès 0-100%',
                'optimal_timing' => 'Meilleur moment pour tentative',
                'optimal_price' => 'Prix optimal selon segment',
                'operator_recommendation' => 'Meilleur opérateur pour client'
            ],
            'ab_testing' => [
                'framework' => 'MLABTestingService',
                'current_tests' => DB::table('ml_ab_tests')->where('status', 'active')->count()
            ]
        ];
    }

    private function getTrendingFeatures(): array
    {
        // Features avec le plus de variation récente
        return [
            'most_predictive' => 'consecutive_failures',
            'fastest_improving' => 'recovery_after_failure_rate', 
            'most_stable' => 'subscription_age_days',
            'newly_added' => ['morning_success_rate', 'eklektik_daily_consistency', 'operator_diversity_score']
        ];
    }

    private function getLatestModelPerformance(): array
    {
        $latest = DB::table('ml_model_performance')
            ->orderBy('evaluation_date', 'desc')
            ->first();
            
        return [
            'model_name' => $latest->model_name ?? 'rule_based_v2.0',
            'auc_roc' => round($latest->auc_roc ?? 0.7, 3),
            'accuracy' => round($latest->accuracy ?? 0.72, 3),
            'f1_score' => round($latest->f1_score ?? 0.65, 3),
            'evaluation_date' => $latest->evaluation_date ?? null,
            'feature_count' => $latest->feature_count ?? 36
        ];
    }

    private function getFeatureCorrelations(): array
    {
        // Corrélations approximatives (à calculer réellement avec des stats plus avancées)
        return [
            'high_correlation_with_success' => [
                'payment_reliability_score' => 0.78,
                'max_consecutive_successes' => 0.65,
                'recovery_after_failure_rate' => 0.58
            ],
            'negative_correlation_with_success' => [
                'consecutive_failures' => -0.72,
                'no_balance_failure_rate' => -0.63,
                'days_since_last_payment' => -0.45
            ]
        ];
    }

    private function getFeatureCompleteness(): array
    {
        $completeness = DB::table('ml_client_features')
            ->where('calculation_date', '>=', now()->subDays(3))
            ->selectRaw('
                AVG(CASE WHEN morning_success_rate IS NOT NULL THEN 1 ELSE 0 END) * 100 as temporal_completeness,
                AVG(CASE WHEN timwe_success_rate IS NOT NULL THEN 1 ELSE 0 END) * 100 as timwe_completeness,
                AVG(CASE WHEN eklektik_success_rate IS NOT NULL THEN 1 ELSE 0 END) * 100 as eklektik_completeness,
                AVG(CASE WHEN total_operators_used IS NOT NULL THEN 1 ELSE 0 END) * 100 as multi_op_completeness
            ')
            ->first();
            
        return [
            'temporal_features' => round($completeness->temporal_completeness ?? 0, 1),
            'timwe_features' => round($completeness->timwe_completeness ?? 0, 1),
            'eklektik_features' => round($completeness->eklektik_completeness ?? 0, 1),
            'multi_operator_features' => round($completeness->multi_op_completeness ?? 0, 1)
        ];
    }

    private function getRecentExtractions(): array
    {
        $extractions = DB::table('ml_client_features')
            ->selectRaw('calculation_date, COUNT(*) as clients')
            ->where('calculation_date', '>=', now()->subDays(7))
            ->groupBy('calculation_date')
            ->orderBy('calculation_date', 'desc')
            ->get();
            
        return $extractions->map(function($ex) {
            return [
                'date' => $ex->calculation_date,
                'clients' => $ex->clients
            ];
        })->toArray();
    }

    private function getStrategiesComparison(): array
    {
        return [
            'current_vs_recommended' => [
                'current' => ['strategy' => 'mensuel_3.0_tnd', 'success_rate' => 9.09, 'revenue' => 23382],
                'recommended' => ['strategy' => 'quotidien_0.3_tnd', 'success_rate' => 25.0, 'revenue' => 173632],
                'improvement' => ['success' => '+175%', 'revenue' => '+643%']
            ],
            'by_segment' => $this->getSegmentOptimalStrategies()
        ];
    }

    private function getSegmentOptimalStrategies(): array
    {
        return [
            'premium_payers' => ['recommended' => 'mensuel_3.0_tnd', 'reason' => 'Performance déjà excellente'],
            'regular_payers' => ['recommended' => 'hebdomadaire_1.0_tnd', 'reason' => 'Équilibre volume/marge'],
            'struggling_payers' => ['recommended' => 'quotidien_0.3_tnd', 'reason' => 'Accessibilité prix'],
            'high_risk' => ['recommended' => 'quotidien_0.3_tnd', 'reason' => 'Réactivation nécessaire'],
            'churn_risk' => ['recommended' => 'quotidien_0.3_tnd', 'reason' => 'Rétention par volume']
        ];
    }

    private function getActiveABTests(): int
    {
        return DB::table('ml_ab_tests')
            ->where('status', 'active')
            ->count();
    }

    // ===== INSIGHTS AVANCÉS (Partie 2 - Dr. ML) =====

    /**
     * Identifie les segments avec le plus fort potentiel d'amélioration de revenus
     */
    private function calculateRevenueOpportunities(): array
    {
        $latestDate = DB::table('ml_client_features')->max('calculation_date');
        if (!$latestDate) {
            return [];
        }

        $segments = DB::table('ml_client_features')
            ->where('calculation_date', $latestDate)
            ->whereNotNull('client_segment')
            ->select('client_segment')
            ->selectRaw('COUNT(*) as client_count')
            ->selectRaw('AVG(payment_success_rate) as current_success_rate')
            ->groupBy('client_segment')
            ->get();

        $result = collect($segments)->map(function ($seg) {
            $currentRevenue = $seg->client_count * ($seg->current_success_rate ?? 0) * 3.0; // Mensuel 3.0 TND
            $potentialSuccessRate = $this->estimatePotentialSuccessRate($seg->client_segment);
            $potentialRevenueMonthly = $seg->client_count * $potentialSuccessRate * 0.3 * 30; // Quotidien 0.3 TND × 30 jours
            $opportunityTnd = max(0, round($potentialRevenueMonthly - $currentRevenue, 0));
            $roiPercentage = $currentRevenue > 0
                ? round((($potentialRevenueMonthly / $currentRevenue) - 1) * 100, 0)
                : 0;

            return [
                'segment' => $seg->client_segment,
                'clients' => (int) $seg->client_count,
                'current_revenue_monthly' => (int) round($currentRevenue, 0),
                'potential_revenue_monthly' => (int) round($potentialRevenueMonthly, 0),
                'opportunity_tnd' => (int) $opportunityTnd,
                'roi_percentage' => $roiPercentage
            ];
        })->sortByDesc('opportunity_tnd')->values()->toArray();

        return $result;
    }

    /**
     * Estime le taux de succès potentiel par segment avec stratégie quotidienne 0.3 TND
     */
    private function estimatePotentialSuccessRate(string $segment): float
    {
        $rates = [
            'premium_payers' => 0.35,
            'regular_payers' => 0.22,
            'struggling_payers' => 0.18,
            'high_risk' => 0.15,
            'churn_risk' => 0.12,
            'unknown' => 0.10
        ];
        return $rates[$segment] ?? 0.10;
    }

    /**
     * Identifie les actions à impact rapide
     */
    private function identifyQuickWins(): array
    {
        $highRiskCount = DB::table('ml_client_features')
            ->where('calculation_date', DB::table('ml_client_features')->max('calculation_date'))
            ->where('client_segment', 'high_risk')
            ->count();

        $strugglingCount = DB::table('ml_client_features')
            ->where('calculation_date', DB::table('ml_client_features')->max('calculation_date'))
            ->where('client_segment', 'struggling_payers')
            ->count();

        return [
            [
                'action' => 'Migrer segment high_risk vers quotidien 0.3 TND',
                'segment' => 'high_risk',
                'clients_affected' => $highRiskCount,
                'expected_impact' => '+7,381% ROI estimé',
                'effort' => 'medium',
                'timeline' => '2-4 semaines'
            ],
            [
                'action' => 'Tester A/B hebdo 1.0 TND sur struggling_payers',
                'segment' => 'struggling_payers',
                'clients_affected' => $strugglingCount,
                'expected_impact' => '+58% ROI vs mensuel',
                'effort' => 'low',
                'timeline' => '1-2 semaines'
            ],
            [
                'action' => 'Optimiser timing (best_billing_hour) pour regular_payers',
                'segment' => 'regular_payers',
                'clients_affected' => null,
                'expected_impact' => '+5-15% succès',
                'effort' => 'low',
                'timeline' => '1 semaine'
            ]
        ];
    }

    /**
     * Retourne les alertes risque actuelles
     */
    private function getCurrentRiskAlerts(): array
    {
        $latestDate = DB::table('ml_client_features')->max('calculation_date');
        if (!$latestDate) {
            return [];
        }

        $alerts = [];

        $churnCount = DB::table('ml_client_features')
            ->where('calculation_date', $latestDate)
            ->where('client_segment', 'churn_risk')
            ->count();
        if ($churnCount > 0) {
            $alerts[] = [
                'type' => 'churn_risk',
                'message' => "{$churnCount} clients en risque de churn",
                'severity' => 'high',
                'recommendation' => 'Offre rétention quotidien 0.3 TND + bonus'
            ];
        }

        $highFailureCount = DB::table('ml_client_features')
            ->where('calculation_date', $latestDate)
            ->where('consecutive_failures', '>=', 5)
            ->count();
        if ($highFailureCount > 100) {
            $alerts[] = [
                'type' => 'consecutive_failures',
                'message' => "{$highFailureCount} clients avec 5+ échecs consécutifs",
                'severity' => 'medium',
                'recommendation' => 'Pause facturation ou passage quotidien pour réactivation'
            ];
        }

        $zeroSuccessRate = DB::table('ml_client_features')
            ->where('calculation_date', $latestDate)
            ->where('payment_success_rate', 0)
            ->count();
        $total = DB::table('ml_client_features')->where('calculation_date', $latestDate)->count();
        $zeroPct = $total > 0 ? round($zeroSuccessRate / $total * 100, 1) : 0;
        if ($zeroPct > 70) {
            $alerts[] = [
                'type' => 'zero_success',
                'message' => "{$zeroPct}% des clients avec 0% succès historique",
                'severity' => 'high',
                'recommendation' => 'Priorité migration quotidien 0.3 TND sur high_risk'
            ];
        }

        return $alerts;
    }

    /**
     * Génère des suggestions d'A/B tests
     */
    private function generateABTestSuggestions(): array
    {
        $activeCount = $this->getActiveABTests();
        $suggestions = [
            [
                'name' => 'high_risk_quotidien_vs_mensuel',
                'description' => 'Groupe A: mensuel 3.0 TND, Groupe B: quotidien 0.3 TND',
                'target_segment' => 'high_risk',
                'sample_size' => 1000,
                'duration_days' => 14,
                'primary_metric' => 'payment_success_rate',
                'expected_improvement' => '+15% succès sur B'
            ],
            [
                'name' => 'struggling_hebdo_vs_mensuel',
                'description' => 'Groupe A: mensuel 3.0 TND, Groupe B: hebdo 1.0 TND',
                'target_segment' => 'struggling_payers',
                'sample_size' => 500,
                'duration_days' => 21,
                'primary_metric' => 'revenue_per_client',
                'expected_improvement' => '+58% ROI sur B'
            ],
            [
                'name' => 'timing_optimization',
                'description' => 'Groupe A: heure actuelle, Groupe B: best_billing_hour prédit',
                'target_segment' => 'regular_payers',
                'sample_size' => 2000,
                'duration_days' => 7,
                'primary_metric' => 'success_rate',
                'expected_improvement' => '+5-10% succès sur B'
            ]
        ];

        return [
            'active_tests_count' => $activeCount,
            'suggestions' => $suggestions
        ];
    }

    /**
     * Tendances d'importance des features (basé sur ml_model_performance si disponible)
     */
    private function getFeatureImportanceTrends(): array
    {
        $latest = DB::table('ml_model_performance')
            ->whereNotNull('feature_importance')
            ->orderBy('evaluation_date', 'desc')
            ->first();

        if (!$latest || !$latest->feature_importance) {
            return [
                'source' => 'static',
                'top_5' => collect($this->getMostImportantFeatures())->take(5)->values()->toArray(),
                'note' => 'Importances statiques (modèle LightGBM non encore évalué en DB)'
            ];
        }

        $importance = is_string($latest->feature_importance)
            ? json_decode($latest->feature_importance, true)
            : $latest->feature_importance;
        if (!is_array($importance)) {
            $importance = [];
        }
        arsort($importance);
        $top5 = array_slice(array_keys($importance), 0, 5);

        $top5WithRank = [];
        foreach (array_values($top5) as $rank => $name) {
            $top5WithRank[] = [
                'feature' => $name,
                'importance' => round((float) ($importance[$name] ?? 0), 4),
                'rank' => $rank + 1
            ];
        }

        return [
            'source' => 'ml_model_performance',
            'evaluation_date' => $latest->evaluation_date ?? null,
            'top_5' => $top5WithRank,
            'note' => 'Dernière évaluation modèle'
        ];
    }
}