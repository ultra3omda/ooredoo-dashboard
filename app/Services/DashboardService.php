<?php

namespace App\Services;

use App\Models\TimweDailyStat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class DashboardService
{
    protected TimweStatsService $timweStatsService;

    public function __construct(TimweStatsService $timweStatsService)
    {
        $this->timweStatsService = $timweStatsService;
    }
    /**
     * Récupère l'ID d'un opérateur depuis son nom ou ID
     */
    private function getOperatorId($operator): ?int
    {
        if (is_numeric($operator)) {
            return (int)$operator;
        }
        
        $operatorId = DB::table('country_payments_methods')
            ->whereRaw("TRIM(country_payments_methods_name) = ?", [trim($operator)])
            ->value('country_payments_methods_id');
        
        return $operatorId ? (int)$operatorId : null;
    }
    
    /**
     * Applique le filtre d'opérateur à une requête (gère les IDs et les noms)
     */
    private function applyOperatorFilter($query, string $selectedOperator, string $tableAlias = 'cpm'): void
    {
        if ($selectedOperator !== 'ALL' && !empty($selectedOperator)) {
            $operatorId = $this->getOperatorId($selectedOperator);
            
            if ($operatorId) {
                $query->where("{$tableAlias}.country_payments_methods_id", $operatorId);
            } else {
                // Fallback sur le nom si l'ID n'est pas trouvé
                $query->whereRaw("TRIM({$tableAlias}.country_payments_methods_name) = ?", [trim($selectedOperator)]);
            }
        }
    }
    
    /**
     * Configuration du cache adaptatif selon la période
     */
    private function getCacheTTL(int $periodDays): int
    {
        if ($periodDays <= 7) {
            return 1800; // 30 minutes pour les périodes courtes
        } elseif ($periodDays <= 30) {
            return 3600; // 1 heure pour les périodes moyennes
        } elseif ($periodDays <= 90) {
            return 7200; // 2 heures pour les périodes longues
        } else {
            return 21600; // 6 heures pour les très longues périodes
        }
    }
    
    /**
     * Génère une clé de cache optimisée (sans user_id pour partage)
     */
    private function generateCacheKey(string $startDate, string $endDate, string $comparisonStartDate, string $comparisonEndDate, string $operator): string
    {
        $keyData = [
            // version bump pour utiliser nouvelle table de cache Timwe optimisée
            'dashboard_v5_optimized',
            $startDate,
            $endDate,
            $comparisonStartDate,
            $comparisonEndDate,
            $operator
        ];
        
        return 'dashboard:' . md5(implode(':', $keyData));
    }
    
    /**
     * Récupère les données du dashboard avec optimisations
     */
    public function getDashboardData(string $startDate, string $endDate, string $comparisonStartDate, string $comparisonEndDate, string $selectedOperator): array
    {
        $startTime = microtime(true);
        
        // Calcul de la période et TTL adaptatif
        $periodDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
        $cacheTTL = $this->getCacheTTL($periodDays);
        $cacheKey = $this->generateCacheKey($startDate, $endDate, $comparisonStartDate, $comparisonEndDate, $selectedOperator);
        
        Log::info("DashboardService: Période de {$periodDays} jours, TTL cache: {$cacheTTL}s, Opérateur: {$selectedOperator}");
        
        return Cache::remember($cacheKey, $cacheTTL, function () use ($startDate, $endDate, $comparisonStartDate, $comparisonEndDate, $selectedOperator, $periodDays, $startTime) {
            
            if ($periodDays > 90) {
                Log::info("Mode optimisé activé pour période longue");
                return $this->getOptimizedDashboardData($startDate, $endDate, $comparisonStartDate, $comparisonEndDate, $selectedOperator, $startTime);
            }
            
            return $this->getStandardDashboardData($startDate, $endDate, $comparisonStartDate, $comparisonEndDate, $selectedOperator, $startTime);
        });
    }
    
    /**
     * Mode standard pour périodes courtes/moyennes
     */
    private function getStandardDashboardData(string $startDate, string $endDate, string $comparisonStartDate, string $comparisonEndDate, string $selectedOperator, float $startTime): array
    {
        // Normalisation des dates
        $startBound = Carbon::parse($startDate)->startOfDay();
        $endExclusive = Carbon::parse($endDate)->addDay()->startOfDay();
        $compStartBound = Carbon::parse($comparisonStartDate)->startOfDay();
        $compEndExclusive = Carbon::parse($comparisonEndDate)->addDay()->startOfDay();
        
        // 1. KPIs principaux avec requêtes optimisées
        $kpis = $this->getKPIsOptimized($startBound, $endExclusive, $compStartBound, $compEndExclusive, $selectedOperator);
        
        // 2. Marchands avec correction du problème N+1
        $merchants = $this->getMerchantsOptimized($startBound, $endExclusive, $compStartBound, $compEndExclusive, $selectedOperator);
        
        // 3. Données de transactions agrégées
        $transactions = $this->getTransactionsData($startBound, $endExclusive, $selectedOperator);
        
        // 4. Données d'abonnements
        $subscriptions = $this->getSubscriptionsData($startBound, $endExclusive, $selectedOperator, $compStartBound, $compEndExclusive);
        
        // 5. Données Ooredoo/DGV
        $ooredooStats = [
            'daily_statistics' => $this->getOoredooDailyStatistics($startBound, $endExclusive),
            'daily_statistics_comparison' => $this->getOoredooDailyStatistics($compStartBound, $compEndExclusive)
        ];
        
        // Grouper les statistiques Ooredoo par mois avec détails quotidiens
        $ooredooStats['ooredoo_monthly_stats'] = $this->groupOoredooStatsByMonth($ooredooStats['daily_statistics']);
        $ooredooStats['ooredoo_monthly_stats_comparison'] = $this->groupOoredooStatsByMonth($ooredooStats['daily_statistics_comparison']);
        
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        
        // Log pour déboguer les KPIs Timwe/Ooredoo et Analyses Avancées
        Log::info("getStandardDashboardData - KPIs retournés", [
            'billingRateTimwe' => $kpis['billingRateTimwe'] ?? 'missing',
            'totalTimweClients' => $kpis['totalTimweClients'] ?? 'missing',
            'totalTimweBillings' => $kpis['totalTimweBillings'] ?? 'missing',
            'billingRateOoredoo' => $kpis['billingRateOoredoo'] ?? 'missing',
            'totalOoredooClients' => $kpis['totalOoredooClients'] ?? 'missing',
            'totalOoreodooBillings' => $kpis['totalOoreodooBillings'] ?? 'missing',
            'has_activations_by_channel' => isset($subscriptions['activations_by_channel']),
            'has_plan_distribution' => isset($subscriptions['plan_distribution']),
            'has_renewal_rate' => isset($subscriptions['renewal_rate']),
            'has_average_lifespan' => isset($subscriptions['average_lifespan']),
            'cohorts_count' => isset($subscriptions['cohorts']) ? count($subscriptions['cohorts']) : 0
        ]);
        
        return [
            "periods" => [
                "primary" => Carbon::parse($startDate)->format("M j, Y") . " - " . Carbon::parse($endDate)->format("M j, Y"),
                "comparison" => Carbon::parse($comparisonStartDate)->format("M j, Y") . " - " . Carbon::parse($comparisonEndDate)->format("M j, Y")
            ],
            "kpis" => $kpis,
            "merchants" => $merchants['data'],
            "categoryDistribution" => $merchants['categories'],
            "transactions" => $transactions,
            "subscriptions" => $subscriptions,
            "ooredoo_stats" => $ooredooStats,
            "insights" => $this->generateInsights($kpis, $merchants['data']),
            "last_updated" => now()->toISOString(),
            "data_source" => "optimized_database",
            "execution_time_ms" => $executionTime,
            "cache_mode" => "standard"
        ];
    }
    
    /**
     * KPIs optimisés avec requêtes unifiées
     */
    private function getKPIsOptimized(Carbon $startBound, Carbon $endExclusive, Carbon $compStartBound, Carbon $compEndExclusive, string $selectedOperator): array
    {
        // Requête unifiée pour tous les KPIs d'abonnements
        $subscriptionQuery = DB::table('client_abonnement as ca')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->select([
                // Période principale
                DB::raw("COUNT(CASE WHEN ca.client_abonnement_creation >= '{$startBound}' AND ca.client_abonnement_creation < '{$endExclusive}' THEN 1 END) as activated_current"),
                DB::raw("COUNT(CASE WHEN ca.client_abonnement_creation >= '{$startBound}' AND ca.client_abonnement_creation < '{$endExclusive}' AND (ca.client_abonnement_expiration IS NULL OR ca.client_abonnement_expiration >= '{$endExclusive}') THEN 1 END) as active_current"),
                DB::raw("COUNT(CASE WHEN ca.client_abonnement_expiration >= '{$startBound}' AND ca.client_abonnement_expiration < '{$endExclusive}' THEN 1 END) as deactivated_current"),
                
                // Période de comparaison
                DB::raw("COUNT(CASE WHEN ca.client_abonnement_creation >= '{$compStartBound}' AND ca.client_abonnement_creation < '{$compEndExclusive}' THEN 1 END) as activated_comparison"),
                DB::raw("COUNT(CASE WHEN ca.client_abonnement_creation >= '{$compStartBound}' AND ca.client_abonnement_creation < '{$compEndExclusive}' AND (ca.client_abonnement_expiration IS NULL OR ca.client_abonnement_expiration >= '{$compEndExclusive}') THEN 1 END) as active_comparison"),
                DB::raw("COUNT(CASE WHEN ca.client_abonnement_expiration >= '{$compStartBound}' AND ca.client_abonnement_expiration < '{$compEndExclusive}' THEN 1 END) as deactivated_comparison")
            ]);
        
        $this->applyOperatorFilter($subscriptionQuery, $selectedOperator);
        
        Log::info("Requête KPIs - Opérateur: {$selectedOperator}");
        
        $subMetrics = $subscriptionQuery->first();
        
        Log::info("KPIs abonnements - Activés: {$subMetrics->activated_current}, Actifs: {$subMetrics->active_current}, Désactivés: {$subMetrics->deactivated_current}");
        
        // Requête unifiée pour les transactions
        $transactionQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->select([
                DB::raw("COUNT(CASE WHEN h.time >= '{$startBound}' AND h.time < '{$endExclusive}' THEN 1 END) as transactions_current"),
                DB::raw("COUNT(CASE WHEN h.time >= '{$compStartBound}' AND h.time < '{$compEndExclusive}' THEN 1 END) as transactions_comparison"),
                DB::raw("COUNT(DISTINCT CASE WHEN h.time >= '{$startBound}' AND h.time < '{$endExclusive}' THEN ca.client_id END) as users_current"),
                DB::raw("COUNT(DISTINCT CASE WHEN h.time >= '{$compStartBound}' AND h.time < '{$compEndExclusive}' THEN ca.client_id END) as users_comparison")
            ]);
        
        $this->applyOperatorFilter($transactionQuery, $selectedOperator);
        
        $txMetrics = $transactionQuery->first();
        
        // Transactions de cohorte (transactions effectuées par les abonnements créés dans la période)
        $cohortTransactionsQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->where('h.time', '>=', $startBound)
            ->where('h.time', '<', $endExclusive)
            ->where('ca.client_abonnement_creation', '>=', $startBound)
            ->where('ca.client_abonnement_creation', '<', $endExclusive);
        $this->applyOperatorFilter($cohortTransactionsQuery, $selectedOperator);
        $cohortTransactions = $cohortTransactionsQuery->count();
        
        $cohortTransactionsComparisonQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->where('h.time', '>=', $compStartBound)
            ->where('h.time', '<', $compEndExclusive)
            ->where('ca.client_abonnement_creation', '>=', $compStartBound)
            ->where('ca.client_abonnement_creation', '<', $compEndExclusive);
        $this->applyOperatorFilter($cohortTransactionsComparisonQuery, $selectedOperator);
        $cohortTransactionsComparison = $cohortTransactionsComparisonQuery->count();
        
        // Utilisateurs transactants de cohorte
        $cohortTransactingUsersQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->where('h.time', '>=', $startBound)
            ->where('h.time', '<', $endExclusive)
            ->where('ca.client_abonnement_creation', '>=', $startBound)
            ->where('ca.client_abonnement_creation', '<', $endExclusive);
        $this->applyOperatorFilter($cohortTransactingUsersQuery, $selectedOperator);
        $cohortTransactingUsers = $cohortTransactingUsersQuery->distinct('ca.client_id')->count('ca.client_id');
        
        $cohortTransactingUsersComparisonQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->where('h.time', '>=', $compStartBound)
            ->where('h.time', '<', $compEndExclusive)
            ->where('ca.client_abonnement_creation', '>=', $compStartBound)
            ->where('ca.client_abonnement_creation', '<', $compEndExclusive);
        $this->applyOperatorFilter($cohortTransactingUsersComparisonQuery, $selectedOperator);
        $cohortTransactingUsersComparison = $cohortTransactingUsersComparisonQuery->distinct('ca.client_id')->count('ca.client_id');
        
        // Calculs des taux
        $retentionRate = $subMetrics->activated_current > 0 ? round(($subMetrics->active_current / $subMetrics->activated_current) * 100, 1) : 0;
        $retentionRateComparison = $subMetrics->activated_comparison > 0 ? round(($subMetrics->active_comparison / $subMetrics->activated_comparison) * 100, 1) : 0;
        
        $conversionRate = $subMetrics->active_current > 0 ? round(($txMetrics->users_current / $subMetrics->active_current) * 100, 1) : 0;
        $conversionRateComparison = $subMetrics->active_comparison > 0 ? round(($txMetrics->users_comparison / $subMetrics->active_comparison) * 100, 1) : 0;
        
        // Calcul du churn rate (abonnements perdus dans la période / abonnements activés)
        // Abonnements perdus = activés ET désactivés dans la période
        $lostSubscriptionsQuery = DB::table('client_abonnement as ca')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->whereBetween('ca.client_abonnement_creation', [$startBound->toDateString(), $endExclusive->copy()->subDay()->toDateString()])
            ->whereNotNull('ca.client_abonnement_expiration')
            ->whereBetween('ca.client_abonnement_expiration', [$startBound->toDateString(), $endExclusive->copy()->subDay()->toDateString()]);
        $this->applyOperatorFilter($lostSubscriptionsQuery, $selectedOperator);
        $lostSubscriptions = $lostSubscriptionsQuery->count();
        
        $lostSubscriptionsComparisonQuery = DB::table('client_abonnement as ca')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->whereBetween('ca.client_abonnement_creation', [$compStartBound->toDateString(), $compEndExclusive->copy()->subDay()->toDateString()])
            ->whereNotNull('ca.client_abonnement_expiration')
            ->whereBetween('ca.client_abonnement_expiration', [$compStartBound->toDateString(), $compEndExclusive->copy()->subDay()->toDateString()]);
        $this->applyOperatorFilter($lostSubscriptionsComparisonQuery, $selectedOperator);
        $lostSubscriptionsComparison = $lostSubscriptionsComparisonQuery->count();
        
        $churnRate = $subMetrics->activated_current > 0 ? round(($lostSubscriptions / $subMetrics->activated_current) * 100, 1) : 0;
        $churnRateComparison = $subMetrics->activated_comparison > 0 ? round(($lostSubscriptionsComparison / $subMetrics->activated_comparison) * 100, 1) : 0;
        
        // Calculer les KPIs des marchands
        $merchantKPIs = $this->calculateMerchantKPIs($startBound, $endExclusive, $compStartBound, $compEndExclusive, $selectedOperator, $txMetrics->transactions_current, $txMetrics->transactions_comparison);
        
        // Calculer transactionsPerUser
        $transactionsPerUser = $txMetrics->users_current > 0 ? round($txMetrics->transactions_current / $txMetrics->users_current, 1) : 0;
        $transactionsPerUserComparison = $txMetrics->users_comparison > 0 ? round($txMetrics->transactions_comparison / $txMetrics->users_comparison, 1) : 0;
        
        // Calculer conversionRatePeriod
        $conversionRatePeriod = $subMetrics->active_current > 0 ? round(($txMetrics->users_current / $subMetrics->active_current) * 100, 2) : 0;
        $conversionRatePeriodComparison = $subMetrics->active_comparison > 0 ? round(($txMetrics->users_comparison / $subMetrics->active_comparison) * 100, 2) : 0;
        
        // Calculer le taux de facturation Timwe (uniquement pour les utilisateurs Timwe)
        $billingRateTimweData = $this->calculateTimweBillingRate($startBound, $endExclusive, $selectedOperator);
        $billingRateTimweComparisonData = $this->calculateTimweBillingRate($compStartBound, $compEndExclusive, $selectedOperator);
        
        $billingRateTimwe = $billingRateTimweData['rate'];
        $billingRateTimweComparison = $billingRateTimweComparisonData['rate'];
        $totalTimweClients = $billingRateTimweData['total_clients'];
        $totalTimweClientsComparison = $billingRateTimweComparisonData['total_clients'];
        $totalTimweBillings = $billingRateTimweData['total_billings'];
        $totalTimweBillingsComparison = $billingRateTimweComparisonData['total_billings'];
        
        // Calculer le taux de facturation Ooredoo/DGV
        $billingRateOoredooData = $this->calculateOoredooBillingRate($startBound, $endExclusive, $selectedOperator);
        $billingRateOoredooComparisonData = $this->calculateOoredooBillingRate($compStartBound, $compEndExclusive, $selectedOperator);
        
        $billingRateOoredoo = $billingRateOoredooData['rate'];
        $billingRateOoredooComparison = $billingRateOoredooComparisonData['rate'];
        $totalOoredooClients = $billingRateOoredooData['total_clients'];
        $totalOoredooClientsComparison = $billingRateOoredooComparisonData['total_clients'];
        $totalOoreodooBillings = $billingRateOoredooData['total_billings'];
        $totalOoreodooBillingsComparison = $billingRateOoredooComparisonData['total_billings'];
        
        return [
            "activatedSubscriptions" => [
                "current" => $subMetrics->activated_current,
                "previous" => $subMetrics->activated_comparison,
                "change" => $this->calculatePercentageChange($subMetrics->activated_current, $subMetrics->activated_comparison)
            ],
            "activeSubscriptions" => [
                "current" => $subMetrics->active_current,
                "previous" => $subMetrics->active_comparison,
                "change" => $this->calculatePercentageChange($subMetrics->active_current, $subMetrics->active_comparison)
            ],
            "deactivatedSubscriptions" => [
                "current" => $subMetrics->deactivated_current,
                "previous" => $subMetrics->deactivated_comparison,
                "change" => $this->calculatePercentageChange($subMetrics->deactivated_current, $subMetrics->deactivated_comparison)
            ],
            "periodDeactivated" => [
                "current" => $subMetrics->deactivated_current,
                "previous" => $subMetrics->deactivated_comparison,
                "change" => $this->calculatePercentageChange($subMetrics->deactivated_current, $subMetrics->deactivated_comparison)
            ],
            "cohortDeactivated" => [
                "current" => $lostSubscriptions,
                "previous" => $lostSubscriptionsComparison,
                "change" => $this->calculatePercentageChange($lostSubscriptions, $lostSubscriptionsComparison)
            ],
            "totalTransactions" => [
                "current" => $txMetrics->transactions_current,
                "previous" => $txMetrics->transactions_comparison,
                "change" => $this->calculatePercentageChange($txMetrics->transactions_current, $txMetrics->transactions_comparison)
            ],
            "cohortTransactions" => [
                "current" => $cohortTransactions,
                "previous" => $cohortTransactionsComparison,
                "change" => $this->calculatePercentageChange($cohortTransactions, $cohortTransactionsComparison)
            ],
            "transactingUsers" => [
                "current" => $txMetrics->users_current,
                "previous" => $txMetrics->users_comparison,
                "change" => $this->calculatePercentageChange($txMetrics->users_current, $txMetrics->users_comparison)
            ],
            "cohortTransactingUsers" => [
                "current" => $cohortTransactingUsers,
                "previous" => $cohortTransactingUsersComparison,
                "change" => $this->calculatePercentageChange($cohortTransactingUsers, $cohortTransactingUsersComparison)
            ],
            "retentionRate" => [
                "current" => $retentionRate,
                "previous" => $retentionRateComparison,
                "change" => $this->calculatePercentageChange($retentionRate, $retentionRateComparison)
            ],
            "retentionRateTrue" => [
                "current" => max(0, 100 - $churnRate),
                "previous" => max(0, 100 - $churnRateComparison),
                "change" => $this->calculatePercentageChange(max(0, 100 - $churnRate), max(0, 100 - $churnRateComparison))
            ],
            "conversionRate" => [
                "current" => $conversionRate,
                "previous" => $conversionRateComparison,
                "change" => $this->calculatePercentageChange($conversionRate, $conversionRateComparison)
            ],
            "churnRate" => [
                "current" => $churnRate,
                "previous" => $churnRateComparison,
                "change" => $this->calculatePercentageChange($churnRate, $churnRateComparison)
            ],
            "transactionsPerUser" => [
                "current" => $transactionsPerUser,
                "previous" => $transactionsPerUserComparison,
                "change" => $this->calculatePercentageChange($transactionsPerUser, $transactionsPerUserComparison)
            ],
            "conversionRatePeriod" => [
                "current" => $conversionRatePeriod,
                "previous" => $conversionRatePeriodComparison,
                "change" => $this->calculatePercentageChange($conversionRatePeriod, $conversionRatePeriodComparison)
            ],
            "activeMerchants" => $merchantKPIs['activeMerchants'],
            "activeMerchantRatio" => $merchantKPIs['activeMerchantRatio'],
            "totalPartners" => $merchantKPIs['totalPartners'],
            "totalActivePartnersDB" => $merchantKPIs['totalActivePartnersDB'],
            "totalLocationsActive" => $merchantKPIs['totalLocationsActive'],
            "totalMerchantsEverActive" => $merchantKPIs['totalMerchantsEverActive'],
            "allTransactionsPeriod" => $merchantKPIs['allTransactionsPeriod'],
            "transactionsPerMerchant" => $merchantKPIs['transactionsPerMerchant'],
            "billingRateTimwe" => [
                "current" => $billingRateTimwe,
                "previous" => $billingRateTimweComparison,
                "change" => $this->calculatePercentageChange($billingRateTimwe, $billingRateTimweComparison)
            ],
            "totalTimweClients" => [
                "current" => $totalTimweClients,
                "previous" => $totalTimweClientsComparison,
                "change" => $this->calculatePercentageChange($totalTimweClients, $totalTimweClientsComparison)
            ],
            "totalTimweBillings" => [
                "current" => $totalTimweBillings,
                "previous" => $totalTimweBillingsComparison,
                "change" => $this->calculatePercentageChange($totalTimweBillings, $totalTimweBillingsComparison)
            ],
            "billingRateOoredoo" => [
                "current" => $billingRateOoredoo,
                "previous" => $billingRateOoredooComparison,
                "change" => $this->calculatePercentageChange($billingRateOoredoo, $billingRateOoredooComparison)
            ],
            "totalOoredooClients" => [
                "current" => $totalOoredooClients,
                "previous" => $totalOoredooClientsComparison,
                "change" => $this->calculatePercentageChange($totalOoredooClients, $totalOoredooClientsComparison)
            ],
            "totalOoreodooBillings" => [
                "current" => $totalOoreodooBillings,
                "previous" => $totalOoreodooBillingsComparison,
                "change" => $this->calculatePercentageChange($totalOoreodooBillings, $totalOoreodooBillingsComparison)
            ]
        ];
    }
    
    /**
     * Calcule les KPIs des marchands
     */
    private function calculateMerchantKPIs(Carbon $startBound, Carbon $endExclusive, Carbon $compStartBound, Carbon $compEndExclusive, string $selectedOperator, int $transactionsCurrent, int $transactionsComparison): array
    {
        // Marchands actifs dans la période principale
        $activeMerchantsQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->join('partner as pt', 'p.partner_id', '=', 'pt.partner_id')
            ->where('h.time', '>=', $startBound)
            ->where('h.time', '<', $endExclusive)
            ->whereNotNull('h.promotion_id');
        $this->applyOperatorFilter($activeMerchantsQuery, $selectedOperator);
        $activeMerchants = $activeMerchantsQuery->distinct('pt.partner_id')->count('pt.partner_id');
        
        // Marchands actifs dans la période de comparaison
        $activeMerchantsComparisonQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->join('partner as pt', 'p.partner_id', '=', 'pt.partner_id')
            ->where('h.time', '>=', $compStartBound)
            ->where('h.time', '<', $compEndExclusive)
            ->whereNotNull('h.promotion_id');
        $this->applyOperatorFilter($activeMerchantsComparisonQuery, $selectedOperator);
        $activeMerchantsComparison = $activeMerchantsComparisonQuery->distinct('pt.partner_id')->count('pt.partner_id');
        
        // Total partenaires actifs
        $totalActivePartnersDB = DB::table('partner')->where('partener_active', 1)->count();
        $totalPartners = $totalActivePartnersDB;
        
        // Total marchands ayant déjà eu des transactions
        $totalMerchantsEverActive = DB::table('history as h')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->distinct('p.partner_id')
            ->count('p.partner_id');
        
        // Total transactions toutes catégories (période principale)
        $allTransactionsPeriod = DB::table('history')
            ->where('time', '>=', $startBound)
            ->where('time', '<', $endExclusive)
            ->count();
        
        // Total points de vente actifs
        $totalLocationsActive = 0;
        try {
            if (Schema::hasColumn('partner', 'partener_active')) {
                $totalLocationsActive = DB::table('partner_location')
                    ->join('partner', 'partner_location.partner_id', '=', 'partner.partner_id')
                    ->where('partner.partener_active', 1)
                    ->distinct('partner_location.partner_location_id')
                    ->count('partner_location.partner_location_id');
            } else {
                $totalLocationsActive = DB::table('partner_location')
                    ->distinct('partner_location.partner_location_id')
                    ->count('partner_location.partner_location_id');
            }
        } catch (\Exception $e) {
            Log::warning('Impossible de calculer totalLocationsActive', ['error' => $e->getMessage()]);
        }
        
        // Transactions par marchand
        $transactionsPerMerchant = $activeMerchants > 0 ? round($transactionsCurrent / $activeMerchants, 1) : 0;
        $transactionsPerMerchantComparison = $activeMerchantsComparison > 0 ? round($transactionsComparison / $activeMerchantsComparison, 1) : 0;
        
        return [
            "activeMerchants" => [
                "current" => $activeMerchants,
                "previous" => $activeMerchantsComparison,
                "change" => $this->calculatePercentageChange($activeMerchants, $activeMerchantsComparison)
            ],
            "activeMerchantRatio" => [
                "current" => $totalPartners > 0 ? round(($activeMerchants / $totalPartners) * 100, 1) : 0,
                "previous" => $totalPartners > 0 ? round(($activeMerchantsComparison / $totalPartners) * 100, 1) : 0,
                "change" => $this->calculatePercentageChange(
                    $totalPartners > 0 ? round(($activeMerchants / $totalPartners) * 100, 1) : 0,
                    $totalPartners > 0 ? round(($activeMerchantsComparison / $totalPartners) * 100, 1) : 0
                )
            ],
            "totalPartners" => [
                "current" => $totalPartners,
                "previous" => $totalPartners,
                "change" => 0.0
            ],
            "totalActivePartnersDB" => [
                "current" => $totalActivePartnersDB,
                "previous" => $totalActivePartnersDB,
                "change" => 0.0
            ],
            "totalLocationsActive" => [
                "current" => $totalLocationsActive,
                "previous" => $totalLocationsActive,
                "change" => 0.0
            ],
            "totalMerchantsEverActive" => $totalMerchantsEverActive,
            "allTransactionsPeriod" => $allTransactionsPeriod,
            "transactionsPerMerchant" => [
                "current" => $transactionsPerMerchant,
                "previous" => $transactionsPerMerchantComparison,
                "change" => $this->calculatePercentageChange($transactionsPerMerchant, $transactionsPerMerchantComparison)
            ]
        ];
    }
    
    /**
     * Marchands optimisés - CORRECTION du problème N+1
     */
    private function getMerchantsOptimized(Carbon $startBound, Carbon $endExclusive, Carbon $compStartBound, Carbon $compEndExclusive, string $selectedOperator): array
    {
        // Requête unifiée pour éviter le N+1
        $merchantsQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->join('partner as pt', 'p.partner_id', '=', 'pt.partner_id')
            ->select([
                'pt.partner_name as name',
                'pt.partner_id',
                // Transactions période principale
                DB::raw("COUNT(CASE WHEN h.time >= '{$startBound}' AND h.time < '{$endExclusive}' THEN 1 END) as current"),
                // Transactions période comparaison
                DB::raw("COUNT(CASE WHEN h.time >= '{$compStartBound}' AND h.time < '{$compEndExclusive}' THEN 1 END) as previous")
            ])
            ->whereNotNull('h.promotion_id');
        
        $this->applyOperatorFilter($merchantsQuery, $selectedOperator);
        
        $merchants = $merchantsQuery
            ->groupBy('pt.partner_name', 'pt.partner_id')
            ->having('current', '>', 0) // Seulement les marchands actifs
            ->orderBy('current', 'DESC')
            ->limit(50)
            ->get();
        
        // Total des transactions pour calculer les parts de marché
        $totalTransactions = $merchants->sum('current');
        
        // Récupérer les vraies catégories depuis la base de données
        $partnerIds = $merchants->pluck('partner_id')->toArray();
        $realCategories = $this->getPartnerCategoriesBatch($partnerIds);
        
        // Enrichissement avec catégories réelles et calculs
        $enrichedMerchants = $merchants->map(function($merchant) use ($totalTransactions, $realCategories) {
            // Utiliser la vraie catégorie de la DB, sinon fallback sur le nom
            $category = $realCategories[$merchant->partner_id] ?? $this->categorizePartner($merchant->name ?? 'Unknown');
            $share = $totalTransactions > 0 ? round(($merchant->current / $totalTransactions) * 100, 1) : 0;
            
            return [
                'name' => $merchant->name ?? 'Unknown',
                'category' => $category,
                'current' => (int)$merchant->current,
                'previous' => (int)$merchant->previous,
                'share' => $share,
                'partner_id' => $merchant->partner_id
            ];
        })->toArray();
        
        // Distribution par catégories
        $categoryDistribution = $this->calculateCategoryDistribution($enrichedMerchants, $totalTransactions);
        
        return [
            'data' => $enrichedMerchants,
            'categories' => $categoryDistribution
        ];
    }
    
    /**
     * Mode optimisé pour très longues périodes
     */
    private function getOptimizedDashboardData(string $startDate, string $endDate, string $comparisonStartDate, string $comparisonEndDate, string $selectedOperator, float $startTime): array
    {
        // Implémentation simplifiée pour les très longues périodes
        $startBound = Carbon::parse($startDate)->startOfDay();
        $endExclusive = Carbon::parse($endDate)->addDay()->startOfDay();
        $compStartBound = Carbon::parse($comparisonStartDate)->startOfDay();
        $compEndExclusive = Carbon::parse($comparisonEndDate)->addDay()->startOfDay();
        
        $kpis = $this->getKPIsOptimized($startBound, $endExclusive, $compStartBound, $compEndExclusive, $selectedOperator);
        
        // Récupérer les marchands même pour les longues périodes (limité)
        $merchants = $this->getMerchantsOptimized($startBound, $endExclusive, $compStartBound, $compEndExclusive, $selectedOperator);
        
        // Récupérer les transactions (avec granularité mensuelle pour les longues périodes)
        $transactions = $this->getTransactionsData($startBound, $endExclusive, $selectedOperator);
        
        // Récupérer les données d'abonnements avec activations quotidiennes
        $subscriptions = $this->getSubscriptionsData($startBound, $endExclusive, $selectedOperator, $compStartBound, $compEndExclusive);
        
        // Ajouter les données Ooredoo/DGV (comme dans getStandardDashboardData)
        $ooredooStats = [
            'daily_statistics' => $this->getOoredooDailyStatistics($startBound, $endExclusive),
            'daily_statistics_comparison' => $this->getOoredooDailyStatistics($compStartBound, $compEndExclusive)
        ];
        
        // Grouper les statistiques Ooredoo par mois avec détails quotidiens
        $ooredooStats['ooredoo_monthly_stats'] = $this->groupOoredooStatsByMonth($ooredooStats['daily_statistics']);
        $ooredooStats['ooredoo_monthly_stats_comparison'] = $this->groupOoredooStatsByMonth($ooredooStats['daily_statistics_comparison']);
        
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        
        // Log pour déboguer les KPIs Timwe et Analyses Avancées
        Log::info("getOptimizedDashboardData - KPIs retournés", [
            'billingRateTimwe' => $kpis['billingRateTimwe'] ?? 'missing',
            'totalTimweClients' => $kpis['totalTimweClients'] ?? 'missing',
            'totalTimweBillings' => $kpis['totalTimweBillings'] ?? 'missing',
            'billingRateOoredoo' => $kpis['billingRateOoredoo'] ?? 'missing',
            'totalOoredooClients' => $kpis['totalOoredooClients'] ?? 'missing',
            'totalOoreodooBillings' => $kpis['totalOoreodooBillings'] ?? 'missing',
            'ooredoo_monthly_stats_count' => count($ooredooStats['ooredoo_monthly_stats'] ?? []),
            'has_activations_by_channel' => isset($subscriptions['activations_by_channel']),
            'has_plan_distribution' => isset($subscriptions['plan_distribution']),
            'has_renewal_rate' => isset($subscriptions['renewal_rate']),
            'has_average_lifespan' => isset($subscriptions['average_lifespan']),
            'cohorts_count' => isset($subscriptions['cohorts']) ? count($subscriptions['cohorts']) : 0
        ]);
        
        return [
            "periods" => [
                "primary" => Carbon::parse($startDate)->format("M j, Y") . " - " . Carbon::parse($endDate)->format("M j, Y"),
                "comparison" => Carbon::parse($comparisonStartDate)->format("M j, Y") . " - " . Carbon::parse($comparisonEndDate)->format("M j, Y")
            ],
            "kpis" => $kpis,
            "merchants" => $merchants['data'],
            "categoryDistribution" => $merchants['categories'],
            "transactions" => $transactions,
            "subscriptions" => $subscriptions,
            "ooredoo_stats" => $ooredooStats,
            "insights" => [
                "positive" => ["Mode optimisé activé pour période étendue"],
                "challenges" => ["Analyse détaillée limitée pour optimiser les performances"],
                "recommendations" => ["Réduire la période pour une analyse plus détaillée"],
                "nextSteps" => ["Analyser des sous-périodes spécifiques"]
            ],
            "last_updated" => now()->toISOString(),
            "data_source" => "optimized_database",
            "execution_time_ms" => $executionTime,
            "cache_mode" => "long_period"
        ];
    }
    
    /**
     * Calcul du pourcentage de changement
     */
    private function calculatePercentageChange($current, $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }
    
    /**
     * Catégorisation des partenaires
     */
    private function categorizePartner(string $partnerName): string
    {
        $name = strtoupper($partnerName);
        
        if (str_contains($name, 'KFC') || str_contains($name, 'RESTAURANT') || str_contains($name, 'PIZZA')) {
            return 'Food & Beverage';
        }
        if (str_contains($name, 'BEAUTY') || str_contains($name, 'SPA') || str_contains($name, 'SALON')) {
            return 'Beauty & Wellness';
        }
        if (str_contains($name, 'CLUB') || str_contains($name, 'BAR') || str_contains($name, 'LOUNGE')) {
            return 'Entertainment';
        }
        if (str_contains($name, 'GYM') || str_contains($name, 'FITNESS') || str_contains($name, 'SPORT')) {
            return 'Fitness & Sports';
        }
        if (str_contains($name, 'SHOP') || str_contains($name, 'STORE') || str_contains($name, 'CENTER')) {
            return 'Retail';
        }
        
        return 'Others';
    }
    
    /**
     * Récupère les catégories réelles des partenaires depuis la base de données
     */
    private function getPartnerCategoriesBatch(array $partnerIds): array
    {
        if (empty($partnerIds)) {
            return [];
        }
        
        $categories = [];
        
        try {
            // Essayer d'abord la relation partner_category
            if (Schema::hasColumn('partner', 'partner_category_id') && 
                Schema::hasTable('partner_category') && 
                Schema::hasColumn('partner_category', 'partner_category_name')) {
                
                $results = DB::table('partner')
                    ->leftJoin('partner_category', 'partner.partner_category_id', '=', 'partner_category.partner_category_id')
                    ->whereIn('partner.partner_id', $partnerIds)
                    ->select('partner.partner_id', 'partner_category.partner_category_name as category')
                    ->get();
                
                foreach ($results as $result) {
                    if ($result->category && trim($result->category) !== '') {
                        $categories[$result->partner_id] = trim($result->category);
                    }
                }
            }
            
            // Pour les partenaires sans catégorie, essayer une colonne catégorie directe
            $missingIds = array_diff($partnerIds, array_keys($categories));
            if (!empty($missingIds)) {
                foreach (['partner_category', 'category', 'business_category', 'sector', 'industry', 'partner_type'] as $column) {
                    if (Schema::hasColumn('partner', $column)) {
                        $results = DB::table('partner')
                            ->whereIn('partner_id', $missingIds)
                            ->select('partner_id', $column . ' as category')
                            ->get();
                        
                        foreach ($results as $result) {
                            if ($result->category && trim($result->category) !== '' && !isset($categories[$result->partner_id])) {
                                $categories[$result->partner_id] = trim($result->category);
                            }
                        }
                        
                        $missingIds = array_diff($missingIds, array_keys($categories));
                        if (empty($missingIds)) break;
                    }
                }
            }
            
            // Pour les partenaires restants, utiliser le fallback basé sur le nom
            $missingIds = array_diff($partnerIds, array_keys($categories));
            if (!empty($missingIds)) {
                $partners = DB::table('partner')
                    ->whereIn('partner_id', $missingIds)
                    ->select('partner_id', 'partner_name')
                    ->get();
                
                foreach ($partners as $partner) {
                    $categories[$partner->partner_id] = $this->categorizePartner($partner->partner_name ?? 'Unknown');
                }
            }
        } catch (\Exception $e) {
            Log::warning("Erreur lors de la récupération des catégories batch: " . $e->getMessage());
            // Fallback: utiliser le nom pour tous
            $partners = DB::table('partner')
                ->whereIn('partner_id', $partnerIds)
                ->select('partner_id', 'partner_name')
                ->get();
            
            foreach ($partners as $partner) {
                $categories[$partner->partner_id] = $this->categorizePartner($partner->partner_name ?? 'Unknown');
            }
        }
        
        return $categories;
    }
    
    /**
     * Calcul de la distribution par catégories (utilise les vraies catégories des partenaires)
     */
    private function calculateCategoryDistribution(array $merchants, int $totalTransactions): array
    {
        $categories = [];
        
        foreach ($merchants as $merchant) {
            $category = $merchant['category'] ?? 'Others';
            if (!isset($categories[$category])) {
                $categories[$category] = ['transactions' => 0, 'merchants' => 0];
            }
            $categories[$category]['transactions'] += (int)($merchant['current'] ?? 0);
            $categories[$category]['merchants']++;
        }
        
        $distribution = [];
        foreach ($categories as $category => $data) {
            $percentage = $totalTransactions > 0 ? round(($data['transactions'] / $totalTransactions) * 100, 1) : 0;
            $distribution[] = [
                'category' => $category,
                'transactions' => (int)$data['transactions'],
                'merchants' => (int)$data['merchants'],
                'percentage' => $percentage
            ];
        }
        
        // Trier par nombre de transactions décroissant
        usort($distribution, function($a, $b) {
            return $b['transactions'] - $a['transactions'];
        });
        
        return $distribution;
    }
    
    /**
     * Génération d'insights basés sur les données
     */
    private function generateInsights(array $kpis, array $merchants): array
    {
        $positive = [];
        $challenges = [];
        $recommendations = [];
        
        // Insights positifs
        if ($kpis['activatedSubscriptions']['change'] > 50) {
            $positive[] = "Excellente croissance des abonnements (+{$kpis['activatedSubscriptions']['change']}%)";
        }
        if ($kpis['retentionRate']['current'] > 80) {
            $positive[] = "Taux de rétention élevé de {$kpis['retentionRate']['current']}%";
        }
        
        // Défis
        if ($kpis['conversionRate']['current'] < 10) {
            $challenges[] = "Taux de conversion faible ({$kpis['conversionRate']['current']}%) à améliorer";
        }
        if (count($merchants) < 5) {
            $challenges[] = "Réseau de marchands limité (" . count($merchants) . " actifs)";
        }
        
        // Recommandations
        $recommendations[] = "Optimiser l'expérience utilisateur pour améliorer la conversion";
        $recommendations[] = "Développer le réseau de partenaires marchands";
        
        return [
            "positive" => $positive,
            "challenges" => $challenges,
            "recommendations" => $recommendations,
            "nextSteps" => ["Analyser les parcours utilisateurs", "Lancer des campagnes d'engagement"]
        ];
    }
    
    /**
     * Données de transactions avec volume quotidien filtré par opérateur
     */
    private function getTransactionsData(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        // Calculer la granularité selon la période (forcer le mode quotidien pour toutes les périodes < 365 jours)
        $periodDays = $startBound->diffInDays($endExclusive);
        $granularity = $periodDays > 365 ? 'month' : 'day'; // Mode quotidien pour périodes <= 365 jours
        $historyDateExpr = $granularity === 'month' ? "DATE_FORMAT(h.time, '%Y-%m-01')" : "DATE(h.time)";
        
        Log::info("getTransactionsData - Période: {$periodDays} jours, Granularité: {$granularity}");
        
        // Transactions agrégées par jour ou par mois selon la période
        $transactionsQuery = DB::table("history as h")
            ->join("client_abonnement as ca", "h.client_abonnement_id", "=", "ca.client_abonnement_id")
            ->join("country_payments_methods as cpm", "ca.country_payments_methods_id", "=", "cpm.country_payments_methods_id")
            ->select(DB::raw("$historyDateExpr as date"), DB::raw("COUNT(*) as transactions"), DB::raw("COUNT(DISTINCT ca.client_id) as users"))
            ->where("h.time", ">=", $startBound)
            ->where("h.time", "<", $endExclusive);
        
        // Appliquer le filtre d'opérateur
        $this->applyOperatorFilter($transactionsQuery, $selectedOperator);
        
        $transactionsRaw = $transactionsQuery
            ->groupBy(DB::raw($historyDateExpr))
            ->orderBy("date")
            ->get()
            ->keyBy('date')
            ->toArray();
        
        Log::info("Transactions raw - Nombre de dates: " . count($transactionsRaw) . ", Exemple de clés: " . implode(', ', array_slice(array_keys($transactionsRaw), 0, 5)));
        
        // Générer la série complète avec intervalle adaptatif
        $startDate = $startBound->copy();
        $endDate = $endExclusive->copy()->subDay();
        $dailyVolume = [];
        
        // Pour les longues périodes, utiliser des intervalles plus grands
        $intervalDays = max(1, intval($periodDays / 30)); // Maximum 30 points
        
        if ($granularity === 'month') {
            $cursor = $startDate->copy()->firstOfMonth();
            while ($cursor->lte($endDate)) {
                $key = $cursor->copy()->firstOfMonth()->toDateString();
                $row = $transactionsRaw[$key] ?? null;
                $dailyVolume[] = [
                    'date' => $key,
                    'transactions' => $row ? (int)$row->transactions : 0,
                    'users' => $row ? (int)$row->users : 0
                ];
                $cursor->addMonth();
            }
        } else {
            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                $dateStr = $cursor->toDateString();
                $dayTransactions = isset($transactionsRaw[$dateStr]) ? $transactionsRaw[$dateStr] : null;
                $dailyVolume[] = [
                    'date' => $dateStr,
                    'transactions' => $dayTransactions ? (int)$dayTransactions->transactions : 0,
                    'users' => $dayTransactions ? (int)$dayTransactions->users : 0
                ];
                $cursor->addDays($intervalDays); // Intervalle adaptatif
            }
        }
        
        return [
            "daily_volume" => $dailyVolume,
            "by_category" => [],
            "analytics" => [
                "byOperator" => $this->getTransactionsByOperator($startBound, $endExclusive, $selectedOperator),
                "byPlan" => $this->getTransactionsByPlan($startBound, $endExclusive, $selectedOperator),
                "byChannel" => []
            ]
        ];
    }
    
    /**
     * Transactions par opérateur
     */
    private function getTransactionsByOperator(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        $query = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->select('cpm.country_payments_methods_name as operator', DB::raw('COUNT(*) as count'))
            ->where('h.time', '>=', $startBound)
            ->where('h.time', '<', $endExclusive);
        
        $this->applyOperatorFilter($query, $selectedOperator);
        
        return $query->groupBy('cpm.country_payments_methods_name')
            ->get()
            ->map(function($item) {
        return [
                    'operator' => $item->operator,
                    'count' => (int)$item->count
                ];
            })
            ->toArray();
    }
    
    /**
     * Transactions par plan
     */
    private function getTransactionsByPlan(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        $query = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->leftJoin('abonnement_tarifs as at', 'ca.tarif_id', '=', 'at.abonnement_tarifs_id')
            ->select(
                DB::raw("CASE 
                    -- PRIORITÉ 0 : Opérateurs spéciaux (toujours en premier, peu importe duration/frequence)
                    WHEN LOWER(TRIM(cpm.country_payments_methods_name)) LIKE '%carte%cadeau%' THEN 'Annuel'
                    WHEN LOWER(TRIM(cpm.country_payments_methods_name)) LIKE '%timwe%' THEN 'Mensuel'
                    -- PRIORITÉ 1A : Si duration > 0, utiliser duration
                    WHEN at.abonnement_tarifs_duration = 1 THEN 'Journalier'
                    WHEN at.abonnement_tarifs_duration = 3 THEN 'Trial'
                    WHEN at.abonnement_tarifs_duration BETWEEN 28 AND 31 THEN 'Mensuel'
                    WHEN at.abonnement_tarifs_duration >= 365 THEN 'Annuel'
                    -- PRIORITÉ 1B : Si duration = 0, utiliser frequence
                    WHEN at.abonnement_tarifs_duration = 0 THEN
                        CASE 
                            WHEN at.abonnement_tarifs_frequence = 1 THEN 'Journalier'
                            WHEN at.abonnement_tarifs_frequence = 7 THEN 'Hebdomadaire'
                            WHEN at.abonnement_tarifs_frequence BETWEEN 28 AND 31 THEN 'Mensuel'
                            WHEN at.abonnement_tarifs_frequence >= 365 THEN 'Annuel'
                            ELSE 'Autre'
                        END
                    -- PRIORITÉ 2 : Utiliser les dates si disponibles
                    WHEN ca.client_abonnement_expiration IS NOT NULL THEN
                        CASE 
                            WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) = 1 THEN 'Journalier'
                            WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) = 3 THEN 'Trial'
                            WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) BETWEEN 28 AND 31 THEN 'Mensuel'
                            WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) >= 365 THEN 'Annuel'
                            ELSE 'Autre'
                        END
                    ELSE 'Autre'
                END as plan"),
                DB::raw('COUNT(*) as count')
            )
            ->where('h.time', '>=', $startBound)
            ->where('h.time', '<', $endExclusive);
        
        $this->applyOperatorFilter($query, $selectedOperator);
        
        return $query->groupBy(DB::raw("CASE 
                    WHEN LOWER(TRIM(cpm.country_payments_methods_name)) LIKE '%carte%cadeau%' THEN 'Annuel'
                    WHEN LOWER(TRIM(cpm.country_payments_methods_name)) LIKE '%timwe%' THEN 'Mensuel'
                    WHEN at.abonnement_tarifs_duration = 1 THEN 'Journalier'
                    WHEN at.abonnement_tarifs_duration = 3 THEN 'Trial'
                    WHEN at.abonnement_tarifs_duration BETWEEN 28 AND 31 THEN 'Mensuel'
                    WHEN at.abonnement_tarifs_duration >= 365 THEN 'Annuel'
                    WHEN at.abonnement_tarifs_duration = 0 THEN
                        CASE 
                            WHEN at.abonnement_tarifs_frequence = 1 THEN 'Journalier'
                            WHEN at.abonnement_tarifs_frequence = 7 THEN 'Hebdomadaire'
                            WHEN at.abonnement_tarifs_frequence BETWEEN 28 AND 31 THEN 'Mensuel'
                            WHEN at.abonnement_tarifs_frequence >= 365 THEN 'Annuel'
                            ELSE 'Autre'
                        END
                    WHEN ca.client_abonnement_expiration IS NOT NULL THEN
                        CASE 
                            WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) = 1 THEN 'Journalier'
                            WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) = 3 THEN 'Trial'
                            WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) BETWEEN 28 AND 31 THEN 'Mensuel'
                            WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) >= 365 THEN 'Annuel'
                            ELSE 'Autre'
                        END
                    ELSE 'Autre'
                END"))
            ->get()
            ->map(function($item) {
                return [
                    'plan' => $item->plan,
                    'count' => (int)$item->count
                ];
            })
            ->toArray();
    }
    
    /**
     * Données d'abonnements avec activations quotidiennes filtrées par opérateur
     */
    private function getSubscriptionsData(Carbon $startBound, Carbon $endExclusive, string $selectedOperator, ?Carbon $compStartBound = null, ?Carbon $compEndExclusive = null): array
    {
        // Calculer la granularité selon la période (forcer le mode quotidien pour toutes les périodes < 365 jours)
        $periodDays = $startBound->diffInDays($endExclusive);
        $granularity = $periodDays > 365 ? 'month' : 'day'; // Mode quotidien pour périodes <= 365 jours
        $caDateExpr = $granularity === 'month' ? "DATE_FORMAT(client_abonnement_creation, '%Y-%m-01')" : "DATE(client_abonnement_creation)";
        
        Log::info("getSubscriptionsData - Période: {$periodDays} jours, Granularité: {$granularity}");
        
        // Requête pour les activations quotidiennes/mensuelles avec filtre opérateur
        $activationsQuery = DB::table("client_abonnement as ca")
            ->join("country_payments_methods as cpm", "ca.country_payments_methods_id", "=", "cpm.country_payments_methods_id")
            ->select(DB::raw("$caDateExpr as date"), DB::raw("COUNT(*) as activations"))
            ->where("ca.client_abonnement_creation", ">=", $startBound)
            ->where("ca.client_abonnement_creation", "<", $endExclusive);
        
        // Appliquer le filtre d'opérateur
        $this->applyOperatorFilter($activationsQuery, $selectedOperator);
        
        Log::info("Requête activations quotidiennes - Opérateur: {$selectedOperator}, Période: {$startBound->toDateString()} à {$endExclusive->toDateString()}");
        
        $activationsRaw = $activationsQuery
            ->groupBy(DB::raw($caDateExpr))
            ->orderBy("date")
            ->get()
            ->keyBy('date')
            ->toArray();
        
        Log::info("Activations trouvées: " . count($activationsRaw) . " jours/mois avec données");
        
        // Générer la série complète avec toutes les dates
        $startDate = $startBound->copy();
        $endDate = $endExclusive->copy()->subDay();
        $dailyActivations = [];
        
        if ($granularity === 'month') {
            $cursor = $startDate->copy()->firstOfMonth();
            while ($cursor->lte($endDate)) {
                $key = $cursor->copy()->firstOfMonth()->toDateString();
                $activations = isset($activationsRaw[$key]) ? (int)$activationsRaw[$key]->activations : 0;
                $dailyActivations[] = [
                    'date' => $key,
                    'activations' => $activations,
                    'active' => round($activations * 0.95)
                ];
                $cursor->addMonth();
            }
        } else {
            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                $dateStr = $cursor->toDateString();
                $activations = isset($activationsRaw[$dateStr]) ? (int)$activationsRaw[$dateStr]->activations : 0;
                $dailyActivations[] = [
                    'date' => $dateStr,
                    'activations' => $activations,
                    'active' => round($activations * 0.95)
                ];
                $cursor->addDay();
            }
        }
        
        // Calculer retention_trend (optimisé - éviter les requêtes par jour)
        $retentionTrend = $this->calculateRetentionTrendOptimized($startBound, $endExclusive, $selectedOperator);
        
        // Calculer quarterly_active_locations (simplifié pour éviter les timeouts)
        $quarterlyActiveLocations = $this->calculateQuarterlyActiveLocations($endExclusive->copy()->subDay()->toDateString());
        
        // Récupérer les détails des abonnements (limité pour éviter les timeouts)
        $subscriptionDetails = $this->getSubscriptionDetails($startBound, $endExclusive, $selectedOperator);
        
        // Calculer activations_by_channel (avec comparaison)
        Log::info("getSubscriptionsData - Calcul activations_by_channel", [
            'startBound' => $startBound->toDateString(),
            'endExclusive' => $endExclusive->toDateString(),
            'selectedOperator' => $selectedOperator
        ]);
        $activationsCurrent = $this->calculateActivationsByPaymentMethod($startBound, $endExclusive, $selectedOperator);
        Log::info("getSubscriptionsData - Activations par canal (current)", $activationsCurrent);
        $activationsPrevious = ($compStartBound && $compEndExclusive) 
            ? $this->calculateActivationsByPaymentMethod($compStartBound, $compEndExclusive, $selectedOperator)
            : ['cb' => 0, 'recharge' => 0, 'phone_balance' => 0, 'other' => 0];
        Log::info("getSubscriptionsData - Activations par canal (previous)", $activationsPrevious);
        
        $activationsByChannel = [
            "cb" => [
                "current" => $activationsCurrent['cb'] ?? 0,
                "previous" => $activationsPrevious['cb'] ?? 0,
                "change" => $this->calculatePercentageChange($activationsCurrent['cb'] ?? 0, $activationsPrevious['cb'] ?? 0)
            ],
            "recharge" => [
                "current" => $activationsCurrent['recharge'] ?? 0,
                "previous" => $activationsPrevious['recharge'] ?? 0,
                "change" => $this->calculatePercentageChange($activationsCurrent['recharge'] ?? 0, $activationsPrevious['recharge'] ?? 0)
            ],
            "phone_balance" => [
                "current" => $activationsCurrent['phone_balance'] ?? 0,
                "previous" => $activationsPrevious['phone_balance'] ?? 0,
                "change" => $this->calculatePercentageChange($activationsCurrent['phone_balance'] ?? 0, $activationsPrevious['phone_balance'] ?? 0)
            ],
            "other" => [
                "current" => $activationsCurrent['other'] ?? 0,
                "previous" => $activationsPrevious['other'] ?? 0,
                "change" => $this->calculatePercentageChange($activationsCurrent['other'] ?? 0, $activationsPrevious['other'] ?? 0)
            ]
        ];
        
        // Calculer plan_distribution (avec comparaison)
        Log::info("getSubscriptionsData - Calcul plan_distribution", [
            'startBound' => $startBound->toDateString(),
            'endExclusive' => $endExclusive->toDateString(),
            'selectedOperator' => $selectedOperator
        ]);
        $plansCurrent = $this->calculatePlanDistribution($startBound, $endExclusive, $selectedOperator);
        Log::info("getSubscriptionsData - Plan distribution (current)", $plansCurrent);
        $plansPrevious = ($compStartBound && $compEndExclusive)
            ? $this->calculatePlanDistribution($compStartBound, $compEndExclusive, $selectedOperator)
            : ['daily' => 0, 'monthly' => 0, 'annual' => 0, 'other' => 0];
        Log::info("getSubscriptionsData - Plan distribution (previous)", $plansPrevious);
        
        $planDistribution = [
            "daily" => [
                "current" => $plansCurrent['daily'] ?? 0,
                "previous" => $plansPrevious['daily'] ?? 0,
                "change" => $this->calculatePercentageChange($plansCurrent['daily'] ?? 0, $plansPrevious['daily'] ?? 0)
            ],
            "monthly" => [
                "current" => $plansCurrent['monthly'] ?? 0,
                "previous" => $plansPrevious['monthly'] ?? 0,
                "change" => $this->calculatePercentageChange($plansCurrent['monthly'] ?? 0, $plansPrevious['monthly'] ?? 0)
            ],
            "annual" => [
                "current" => $plansCurrent['annual'] ?? 0,
                "previous" => $plansPrevious['annual'] ?? 0,
                "change" => $this->calculatePercentageChange($plansCurrent['annual'] ?? 0, $plansPrevious['annual'] ?? 0)
            ],
            "other" => [
                "current" => $plansCurrent['other'] ?? 0,
                "previous" => $plansPrevious['other'] ?? 0,
                "change" => $this->calculatePercentageChange($plansCurrent['other'] ?? 0, $plansPrevious['other'] ?? 0)
            ]
        ];
        
        // Calculer cohorts
        Log::info("getSubscriptionsData - Calcul cohorts", [
            'startDate' => $startBound->format('Y-m-d'),
            'endDate' => $endExclusive->copy()->subDay()->format('Y-m-d'),
            'selectedOperator' => $selectedOperator
        ]);
        $cohorts = $this->calculateCohorts($startBound->format('Y-m-d'), $endExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator);
        Log::info("getSubscriptionsData - Cohorts calculées", ['count' => count($cohorts), 'sample' => $cohorts[0] ?? null]);
        
        // Calculer renewal_rate, average_lifespan, reactivation_rate (avec comparaison)
        Log::info("getSubscriptionsData - Calcul renewal_rate, average_lifespan, reactivation_rate");
        $renewalCurrent = $this->calculateRenewalRate($startBound->format('Y-m-d'), $endExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator);
        Log::info("getSubscriptionsData - Renewal rate (current)", ['value' => $renewalCurrent]);
        $renewalPrevious = ($compStartBound && $compEndExclusive)
            ? $this->calculateRenewalRate($compStartBound->format('Y-m-d'), $compEndExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator)
            : 0;
        
        $lifespanCurrent = $this->calculateAverageLifespan($startBound->format('Y-m-d'), $endExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator);
        Log::info("getSubscriptionsData - Average lifespan (current)", ['value' => $lifespanCurrent]);
        $lifespanPrevious = ($compStartBound && $compEndExclusive)
            ? $this->calculateAverageLifespan($compStartBound->format('Y-m-d'), $compEndExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator)
            : 0;
        
        $reactivationCurrent = $this->calculateReactivationRate($startBound->format('Y-m-d'), $endExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator);
        Log::info("getSubscriptionsData - Reactivation rate (current)", ['value' => $reactivationCurrent]);
        $reactivationPrevious = ($compStartBound && $compEndExclusive)
            ? $this->calculateReactivationRate($compStartBound->format('Y-m-d'), $compEndExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator)
            : 0;
        
        $renewalRate = [
            "current" => $renewalCurrent,
            "previous" => $renewalPrevious,
            "change" => $this->calculatePercentageChange($renewalCurrent, $renewalPrevious)
        ];
        $averageLifespan = [
            "current" => $lifespanCurrent,
            "previous" => $lifespanPrevious,
            "change" => $this->calculatePercentageChange($lifespanCurrent, $lifespanPrevious)
        ];
        $reactivationRate = [
            "current" => $reactivationCurrent,
            "previous" => $reactivationPrevious,
            "change" => $this->calculatePercentageChange($reactivationCurrent, $reactivationPrevious)
        ];
        
        // Calculer les statistiques quotidiennes (tableau similaire à Eklektik)
        // Gérer les erreurs de mémoire pour cette méthode
        try {
            $dailyStatistics = $this->getDailyStatistics($startBound, $endExclusive, $selectedOperator);
            Log::info("getSubscriptionsData - Statistiques quotidiennes calculées", ['count' => count($dailyStatistics)]);
        } catch (\Exception $e) {
            Log::error("getSubscriptionsData - Erreur lors du calcul des statistiques quotidiennes: " . $e->getMessage());
            $dailyStatistics = []; // Retourner un tableau vide en cas d'erreur
        }
        
        // Calculer les statistiques de la période de comparaison si elle existe
        $dailyStatisticsComparison = [];
        if ($compStartBound && $compEndExclusive) {
            try {
                $dailyStatisticsComparison = $this->getDailyStatistics($compStartBound, $compEndExclusive, $selectedOperator);
                Log::info("getSubscriptionsData - Statistiques quotidiennes de comparaison calculées", [
                    'count' => count($dailyStatisticsComparison),
                    'compStart' => $compStartBound->toDateString(),
                    'compEnd' => $compEndExclusive->toDateString()
                ]);
            } catch (\Exception $e) {
                Log::error("getSubscriptionsData - Erreur lors du calcul des statistiques de comparaison: " . $e->getMessage());
                $dailyStatisticsComparison = [];
            }
        }
        
        // DÉSACTIVÉ POUR OPTIMISATION : Timwe Transactions by User
        // Ce tableau est désactivé définitivement pour améliorer les performances
        $timweTransactionsByUser = [];
        // $periodDays = $startBound->diffInDays($endExclusive);
        // if ($periodDays <= 90) {
        //     $timweTransactionsByUser = $this->getTimweTransactionsByUser($startBound, $endExclusive);
        // }
        
        // Grouper les statistiques Timwe par mois avec détails quotidiens
        $timweMonthlyStats = $this->groupTimweStatsByMonth($dailyStatistics);
        $timweMonthlyStatsComparison = $this->groupTimweStatsByMonth($dailyStatisticsComparison);
        
        // Grouper les statistiques Ooredoo par mois avec détails quotidiens
        $ooredooMonthlyStats = $this->groupOoredooStatsByMonth($ooredooStats['daily_statistics'] ?? []);
        $ooredooMonthlyStatsComparison = $this->groupOoredooStatsByMonth($ooredooStats['daily_statistics_comparison'] ?? []);
        
        return [
            "daily_activations" => $dailyActivations,
            "retention_trend" => $retentionTrend,
            "quarterly_active_locations" => $quarterlyActiveLocations,
            "details" => $subscriptionDetails,
            "daily_statistics" => $dailyStatistics,
            "daily_statistics_comparison" => $dailyStatisticsComparison,
            "timwe_monthly_stats" => $timweMonthlyStats,
            "timwe_monthly_stats_comparison" => $timweMonthlyStatsComparison,
            "timwe_transactions_by_user" => $timweTransactionsByUser,
            "activations_by_channel" => $activationsByChannel,
            "plan_distribution" => $planDistribution,
            "cohorts" => $cohorts,
            "renewal_rate" => $renewalRate,
            "average_lifespan" => $averageLifespan,
            "reactivation_rate" => $reactivationRate
        ];
    }
    
    /**
     * Calcule la tendance de rétention jour par jour (VERSION OPTIMISÉE - une seule requête)
     */
    private function calculateRetentionTrendOptimized(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        try {
            $periodDays = $startBound->diffInDays($endExclusive);
            
            // Pour les longues périodes, utiliser des intervalles plus grands
            $intervalDays = max(1, intval($periodDays / 30)); // Maximum 30 points
            
            // Requête optimisée : récupérer toutes les données en une seule fois
            $endDateStr = $endExclusive->toDateString();
            $activationsQuery = DB::table('client_abonnement as ca')
                ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
                ->select(
                    DB::raw("DATE(ca.client_abonnement_creation) as date"),
                    DB::raw("COUNT(*) as activated"),
                    DB::raw("SUM(CASE WHEN ca.client_abonnement_expiration IS NULL OR ca.client_abonnement_expiration > '{$endDateStr}' THEN 1 ELSE 0 END) as active")
                )
                ->where('ca.client_abonnement_creation', '>=', $startBound)
                ->where('ca.client_abonnement_creation', '<', $endExclusive);
            
            $this->applyOperatorFilter($activationsQuery, $selectedOperator);
            
            $results = $activationsQuery
                ->groupBy(DB::raw("DATE(ca.client_abonnement_creation)"))
                ->orderBy('date')
                ->get()
                ->keyBy('date');
            
            // Générer la série complète avec intervalles adaptatifs
            $trend = [];
            $cursor = $startBound->copy();
            $endDate = $endExclusive->copy()->subDay();
            
            while ($cursor->lte($endDate)) {
                $dateStr = $cursor->toDateString();
                $result = $results->get($dateStr);
                
                if ($result) {
                    $rate = $result->activated > 0 ? round(($result->active / $result->activated) * 100, 1) : 100.0;
                } else {
                    $rate = 100.0; // Pas d'activations ce jour = 100% de rétention
                }
                
                $trend[] = [
                    'date' => $dateStr,
                    'rate' => $rate,
                    'value' => $rate
                ];
                
                // Utiliser l'intervalle calculé pour les longues périodes
                $cursor->addDays($intervalDays);
            }
            
            return $trend;
        } catch (\Exception $e) {
            Log::error("Erreur lors du calcul de la tendance de rétention: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calcule la tendance de rétention jour par jour (ANCIENNE VERSION - trop lente)
     */
    private function calculateRetentionTrend(string $startDate, string $endDate, string $selectedOperator): array
    {
        // Déléguer à la version optimisée
        return $this->calculateRetentionTrendOptimized(
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->addDay()->startOfDay(),
            $selectedOperator
        );
    }
    
    /**
     * Calcule les points de vente actifs par trimestre (optimisé avec calcul réel par trimestre)
     */
    private function calculateQuarterlyActiveLocations(string $endDate): array
    {
        try {
            $quarterlyActiveLocations = [];
            $quarterCursor = Carbon::parse($endDate)->firstOfQuarter()->subQuarters(7);
            $quarterEnd = Carbon::parse($endDate)->firstOfQuarter();
            
            while ($quarterCursor->lte($quarterEnd)) {
                $qEnd = $quarterCursor->copy()->endOfQuarter();
                
                // Calculer les points de vente actifs pour ce trimestre spécifique
                $countLocations = 0;
                try {
                    if (Schema::hasColumn('partner', 'partener_active')) {
                        $countLocations = DB::table('partner_location')
                            ->join('partner', 'partner_location.partner_id', '=', 'partner.partner_id')
                            ->where('partner.partener_active', 1)
                            ->when(Schema::hasColumn('partner_location', 'created_at'), function($q) use ($qEnd) {
                                return $q->where('partner_location.created_at', '<=', $qEnd);
                            })
                            ->distinct('partner_location.partner_location_id')
                            ->count('partner_location.partner_location_id');
                    } else {
                        $countLocations = DB::table('partner_location')
                            ->when(Schema::hasColumn('partner_location', 'created_at'), function($q) use ($qEnd) {
                                return $q->where('partner_location.created_at', '<=', $qEnd);
                            })
                            ->distinct('partner_location.partner_location_id')
                            ->count('partner_location.partner_location_id');
                    }
                } catch (\Exception $e) {
                    Log::warning("Erreur calcul locations pour {$quarterCursor->format('Y-Q')}: " . $e->getMessage());
                    // Utiliser le total actuel en fallback
                    if (Schema::hasColumn('partner', 'partener_active')) {
                        $countLocations = DB::table('partner_location')
                            ->join('partner', 'partner_location.partner_id', '=', 'partner.partner_id')
                            ->where('partner.partener_active', 1)
                            ->distinct('partner_location.partner_location_id')
                            ->count('partner_location.partner_location_id');
                    } else {
                        $countLocations = DB::table('partner_location')
                            ->distinct('partner_location.partner_location_id')
                            ->count('partner_location.partner_location_id');
                    }
                }
                
                $quarterlyActiveLocations[] = [
                    'quarter' => $quarterCursor->format('Y') . '-Q' . $quarterCursor->quarter,
                    'locations' => (int)$countLocations
                ];
                
                $quarterCursor->addQuarter();
            }
            
            return $quarterlyActiveLocations;
        } catch (\Exception $e) {
            Log::error("Erreur lors du calcul des points de vente actifs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupère les détails des abonnements (actifs ou créés dans la période)
     */
    private function getSubscriptionDetails(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        try {
            $periodDays = $startBound->diffInDays($endExclusive);
            
            // Limiter à 1000 résultats maximum pour éviter les timeouts
            $limit = min(1000, max(100, intval($periodDays * 10)));
            
            // Subquery optimisée pour récupérer la première transaction de chaque client
            $firstTransactionSubquery = DB::table('transactions_history as th1')
                ->select([
                    'th1.client_id',
                    'th1.result',
                    'th1.created_at'
                ])
                ->whereRaw('th1.created_at = (
                    SELECT MIN(th2.created_at) 
                    FROM transactions_history th2 
                    WHERE th2.client_id = th1.client_id 
                    AND (th2.status LIKE "%TIMWE_RENEWED_NOTIF%" OR th2.status LIKE "%TIMWE_CHARGE_DELIVERED%")
                )')
                ->where(function($q) {
                    $q->where('th1.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                      ->orWhere('th1.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
                });
            
            $query = DB::table('client_abonnement as ca')
                ->leftJoin('client as c', 'ca.client_id', '=', 'c.client_id')
                ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
                // LEFT JOIN optimisé avec la subquery pour récupérer les transactions en une seule requête
                ->leftJoinSub($firstTransactionSubquery, 'ft', function($join) {
                    $join->on('ca.client_id', '=', 'ft.client_id');
                })
                ->select([
                    'ca.client_id',
                    'c.client_prenom as first_name',
                    'c.client_nom as last_name',
                    'c.client_telephone as phone',
                    'cpm.country_payments_methods_name as operator',
                    'ca.client_abonnement_creation as activation_date',
                    'ca.client_abonnement_expiration as end_date',
                    'ft.result as transaction_result', // Ajouter le résultat de la transaction
                    DB::raw("CASE 
                        -- Pour Timwe : 3 jours = Trial, ~30 jours = Mensuel
                        WHEN LOWER(TRIM(cpm.country_payments_methods_name)) LIKE '%timwe%' THEN
                            CASE 
                                WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) = 3 THEN 'Trial'
                                WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) BETWEEN 20 AND 40 THEN 'Mensuel'
                                ELSE 'Mensuel'
                            END
                        -- Autres opérateurs : logique par durée
                        WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) = 1 THEN 'Journalier'
                        WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) BETWEEN 20 AND 40 THEN 'Mensuel'
                        WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) >= 330 THEN 'Annuel'
                        ELSE 'Autre'
                    END as plan")
                ])
                ->where(function($q) use ($startBound, $endExclusive) {
                    // Afficher les abonnements créés dans la période OU actifs à la fin de la période
                    $q->where(function($subQ) use ($startBound, $endExclusive) {
                        $subQ->where('ca.client_abonnement_creation', '>=', $startBound)
                             ->where('ca.client_abonnement_creation', '<', $endExclusive);
                    })
                    ->orWhere(function($subQ) use ($endExclusive) {
                        // Abonnements actifs (expiration NULL ou >= endExclusive)
                        $subQ->where(function($activeQ) use ($endExclusive) {
                            $activeQ->whereNull('ca.client_abonnement_expiration')
                                    ->orWhere('ca.client_abonnement_expiration', '>=', $endExclusive);
                        });
                    });
                });
            
            $this->applyOperatorFilter($query, $selectedOperator);
            
            // Compter le total avec une estimation pour les grandes tables (optimisation)
            $totalCount = $query->count();
            
            Log::info("getSubscriptionDetails - Total abonnements trouvés", [
                'totalCount' => $totalCount,
                'operator' => $selectedOperator,
                'period' => $startBound->toDateString() . ' - ' . $endExclusive->copy()->subDay()->toDateString()
            ]);
            
            // Limiter les résultats
            $results = $query->orderByDesc('ca.client_abonnement_creation')->limit($limit)->get();
            
            // PPID constants pour Timwe
            $billingPpid = env('TIMWE_BILLING_PPID', '63980');
            $trial3DaysPpid = env('TIMWE_FREE_TRIAL_PPID_3_DAYS', '63981');
            $trial30DaysPpid = env('TIMWE_FREE_TRIAL_PPID_30_DAYS', '63982');
            
            // Convertir en tableau et déterminer le plan basé sur pricepointId ET la durée
            // Les transactions sont déjà récupérées via le LEFT JOIN optimisé
            $dataArray = $results->map(function($item) use ($billingPpid, $trial3DaysPpid, $trial30DaysPpid) {
                // Convertir l'objet stdClass en tableau associatif
                $array = (array)$item;
                // S'assurer que client_id est présent même si null
                if (!isset($array['client_id'])) {
                    $array['client_id'] = null;
                }
                
                // Pour Timwe, déterminer le plan basé sur pricepointId ET la durée de l'abonnement
                $operator = $array['operator'] ?? '';
                $activationDate = $array['activation_date'] ?? null;
                $endDate = $array['end_date'] ?? null;
                $transactionResult = $array['transaction_result'] ?? null;
                
                if (stripos($operator, 'timwe') !== false && $activationDate && $endDate) {
                    // Calculer la durée de l'abonnement
                    $duration = Carbon::parse($activationDate)->diffInDays(Carbon::parse($endDate));
                    
                    // Extraire le PPID de la transaction (déjà récupérée via JOIN)
                    if ($transactionResult) {
                        $ppid = $this->extractPricepointId($transactionResult);
                        
                        // Logique finale : PRIORITÉ à la DURÉE (3j ET 30j) puis PPID
                        // 1. Durée = 3 jours → TOUJOURS Trial gratuit
                        // 2. Durée ≈ 30 jours → TOUJOURS Mensuel payant (peu importe PPID)
                        // 3. Autres durées → Utiliser le PPID pour déterminer
                        
                        if ($duration === 3) {
                            // Durée = 3 jours → TOUJOURS Trial gratuit
                            $array['plan'] = 'Trial';
                        } elseif ($duration >= 20 && $duration <= 40) {
                            // Durée ≈ 30 jours → TOUJOURS Mensuel payant (peu importe PPID)
                            $array['plan'] = 'Mensuel';
                        } elseif ($ppid === $trial3DaysPpid || $ppid === $trial30DaysPpid) {
                            // PPID Trial pour autres durées → Trial gratuit
                            $array['plan'] = 'Trial';
                        } elseif ($ppid === $billingPpid) {
                            // PPID Billing pour autres durées → Mensuel payant
                            $array['plan'] = 'Mensuel';
                        } else {
                            // PPID inconnu → fallback sur Trial
                            $array['plan'] = 'Trial';
                        }
                    }
                }
                
                // Retirer transaction_result du tableau final (pas besoin de l'envoyer au frontend)
                unset($array['transaction_result']);
                
                return $array;
            })->toArray();
            
            Log::info("getSubscriptionDetails - Données retournées", [
                'count' => count($dataArray),
                'sample_client_id' => $dataArray[0]['client_id'] ?? 'N/A' ?? null
            ]);
            
            return [
                'data' => $dataArray,
                'meta' => [
                    'total_count' => $totalCount,
                    'displayed_count' => $results->count(),
                    'limit' => $limit,
                    'execution_time_ms' => 0,
                    'period' => $startBound->toDateString() . ' - ' . $endExclusive->copy()->subDay()->toDateString()
                ]
            ];
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des détails des abonnements: " . $e->getMessage());
            return [
                'data' => [],
                'meta' => [
                    'total_count' => 0,
                    'error' => $e->getMessage()
                ]
            ];
        }
    }
    
    /**
     * Extrait le pricepointId du champ result JSON
     * 
     * @param string|null $result JSON string du champ result
     * @return string|null Le pricepointId ou null si non trouvé
     */
    private function extractPricepointId($result): ?string
    {
        if (empty($result)) {
            return null;
        }
        
        try {
            $data = is_string($result) ? json_decode($result, true) : $result;
            if (!$data || !is_array($data)) {
                return null;
            }
            
            // Chercher pricepointId dans différentes structures possibles
            $fields = ['pricepointId', 'pricepoint_id', 'pricePointId', 'price_point_id', 'ppid', 'PPID'];
            
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    return (string)$data[$field];
                }
            }
            
            // Chercher dans des sous-objets
            if (isset($data['user']['pricepointId'])) {
                return (string)$data['user']['pricepointId'];
            }
            if (isset($data['response']['pricepointId'])) {
                return (string)$data['response']['pricepointId'];
            }
            if (isset($data['data']['pricepointId'])) {
                return (string)$data['data']['pricepointId'];
            }
            
            return null;
            
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Extrait le totalCharged depuis le champ result JSON
     * 
     * @param string|null $result JSON string du champ result
     * @return float Le montant totalCharged ou 0 si non trouvé
     */
    private function extractTotalCharged($result): float
    {
        if (empty($result)) {
            return 0.0;
        }
        
        try {
            $data = is_string($result) ? json_decode($result, true) : $result;
            if (!$data || !is_array($data)) {
                return 0.0;
            }
            
            // Chercher totalCharged directement
            if (isset($data['totalCharged']) && is_numeric($data['totalCharged'])) {
                return floatval($data['totalCharged']);
            }
            
            // Chercher dans des variantes
            $variants = ['total_charged', 'totalCharged', 'totalChargedAmount', 'chargedAmount'];
            foreach ($variants as $variant) {
                if (isset($data[$variant]) && is_numeric($data[$variant])) {
                    return floatval($data[$variant]);
                }
            }
            
            return 0.0;
        } catch (\Exception $e) {
            return 0.0;
        }
    }
    
    /**
     * Vérifie si une transaction a été livrée avec succès (mnoDeliveryCode = DELIVERED)
     * 
     * @param string|null $result JSON string du champ result
     * @return bool True si la transaction a été livrée avec succès
     */
    private function isTransactionDelivered($result): bool
    {
        if (empty($result)) {
            return false;
        }
        
        try {
            // Vérifier d'abord avec une recherche de chaîne simple (plus rapide)
            $resultString = is_string($result) ? $result : json_encode($result);
            
            // Chercher mnoDeliveryCode":"DELIVERED" ou "mnoDeliveryCode": "DELIVERED"
            if (stripos($resultString, '"mnoDeliveryCode":"DELIVERED"') !== false ||
                stripos($resultString, '"mnoDeliveryCode": "DELIVERED"') !== false ||
                stripos($resultString, '"mnoDeliveryCode":"delivered"') !== false ||
                stripos($resultString, '"mnoDeliveryCode": "delivered"') !== false) {
                return true;
            }
            
            // Si la recherche simple ne trouve rien, parser le JSON
            $data = is_string($result) ? json_decode($result, true) : $result;
            if (!$data || !is_array($data)) {
                return false;
            }
            
            // Chercher mnoDeliveryCode dans différentes structures
            $deliveryCode = null;
            if (isset($data['mnoDeliveryCode'])) {
                $deliveryCode = $data['mnoDeliveryCode'];
            } elseif (isset($data['mno_delivery_code'])) {
                $deliveryCode = $data['mno_delivery_code'];
            } elseif (isset($data['response']['mnoDeliveryCode'])) {
                $deliveryCode = $data['response']['mnoDeliveryCode'];
            } elseif (isset($data['data']['mnoDeliveryCode'])) {
                $deliveryCode = $data['data']['mnoDeliveryCode'];
            }
            
            // Vérifier si c'est DELIVERED (insensible à la casse)
            if ($deliveryCode && strtoupper(trim($deliveryCode)) === 'DELIVERED') {
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Calcule le taux de facturation Timwe uniquement pour les utilisateurs Timwe
     * Basé sur client_abonnement (source principale) et transactions_history (pour vérifier les facturations)
     * 
     * Formule: (Nombre de clients facturés) / (Nombre de clients avec abonnements Timwe) * 100
     * 
     * Numérateur: Clients uniques qui ont eu au moins une transaction avec pricepointId = 63980 (billing) DANS LA PÉRIODE
     * Dénominateur: Clients uniques qui ont des abonnements Timwe (créés dans la période OU actifs à la fin)
     * 
     * Note: Seuls les abonnements avec pricepointId = 63980 sont facturés
     *       Les autres (63981 = trial 3 jours, 63982 = trial 30 jours) sont gratuits
     * 
     * @return array ['rate' => float, 'total_clients' => int, 'billed_clients' => int, 'total_billings' => int]
     */
    private function calculateTimweBillingRate(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        try {
            // Essayer d'utiliser la table de cache d'abord
            $endDate = $endExclusive->copy()->subDay(); // endExclusive -1 jour pour avoir la vraie fin
            $stats = TimweDailyStat::getStatsForPeriod($startBound, $endDate);

            if ($stats->isNotEmpty()) {
                // Utiliser les données de la table de cache
                $lastDayStat = $stats->last();
                
                return [
                    'rate' => $lastDayStat->billing_rate,
                    'total_clients' => $lastDayStat->total_clients,
                    'billed_clients' => 0, // Non utilisé dans l'interface
                    'total_billings' => $stats->sum('total_billings')
                ];
            }

            // Si pas de données dans le cache, retourner 0 et loguer un avertissement
            $periodDays = $startBound->diffInDays($endExclusive);
            Log::warning("calculateTimweBillingRate - Aucune donnée dans le cache", [
                'period_days' => $periodDays,
                'start' => $startBound->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'suggestion' => 'Exécuter: php artisan timwe:calculate-historical --from=' . $startBound->format('Y-m-d') . ' --to=' . $endDate->format('Y-m-d')
            ]);
            
            return [
                'rate' => 0.0,
                'total_clients' => 0,
                'billed_clients' => 0,
                'total_billings' => 0
            ];
            
            
            // PPID constants
            $billingPpid = env('TIMWE_BILLING_PPID', '63980');
            
            Log::info("calculateTimweBillingRate - Début calcul", [
                'startBound' => $startBound->toDateTimeString(),
                'endExclusive' => $endExclusive->toDateTimeString(),
                'selectedOperator' => $selectedOperator,
                'billingPpid' => $billingPpid,
                'periodDays' => $periodDays
            ]);
            
            // TOUJOURS récupérer les IDs d'opérateurs Timwe, peu importe l'opérateur sélectionné globalement
            // Car les KPIs Timwe doivent toujours afficher les chiffres de Timwe uniquement
            $timweOperatorIds = DB::table('country_payments_methods')
                ->whereRaw("TRIM(country_payments_methods_name) LIKE ?", ['%timwe%'])
                ->pluck('country_payments_methods_id')
                ->toArray();
            
            if (empty($timweOperatorIds)) {
                Log::info("calculateTimweBillingRate - Aucun opérateur Timwe trouvé dans la base, retour 0");
                return [
                    'rate' => 0.0,
                    'total_clients' => 0,
                    'billed_clients' => 0,
                    'total_billings' => 0
                ];
            }
            
            // 1. Compter les clients uniques avec abonnements Timwe (créés dans la période OU actifs)
            $totalTimweClientsQuery = DB::table('client_abonnement as ca')
                ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
                ->where(function($q) use ($startBound, $endExclusive) {
                    // Abonnements créés dans la période
                    $q->where(function($subQ) use ($startBound, $endExclusive) {
                        $subQ->where('ca.client_abonnement_creation', '>=', $startBound)
                             ->where('ca.client_abonnement_creation', '<', $endExclusive);
                    })
                    // OU abonnements actifs à la fin de la période
                    ->orWhere(function($subQ) use ($endExclusive) {
                        $subQ->where(function($activeQ) use ($endExclusive) {
                            $activeQ->whereNull('ca.client_abonnement_expiration')
                                    ->orWhere('ca.client_abonnement_expiration', '>=', $endExclusive);
                        });
                    });
                })
                ->select('ca.client_id')
                ->distinct();
            
            $totalTimweClients = $totalTimweClientsQuery->count();
            $timweClientIds = $totalTimweClientsQuery->pluck('client_id')->toArray();
            
            Log::info("calculateTimweBillingRate - Total clients Timwe", [
                'totalTimweClients' => $totalTimweClients,
                'timweOperatorIds' => $timweOperatorIds
            ]);
            
            if ($totalTimweClients == 0 || empty($timweClientIds)) {
                return [
                    'rate' => 0.0,
                    'total_clients' => 0,
                    'billed_clients' => 0,
                    'total_billings' => 0
                ];
            }
            
            // 2. Récupérer toutes les transactions RENEW ou CHARGE pour ces clients DANS LA PÉRIODE
            // IMPORTANT: Filtrer par client_id ET par période pour s'assurer que les transactions correspondent
            $transactions = DB::table('transactions_history as th')
                ->whereIn('th.client_id', $timweClientIds)
                ->where('th.created_at', '>=', $startBound)
                ->where('th.created_at', '<', $endExclusive)
                ->where(function($q) {
                    $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                      ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
                })
                ->select('th.client_id', 'th.result', 'th.transaction_history_id')
                ->get();
            
            // Filtrer les transactions avec pricepointId = 63980 (billing) ET mnoDeliveryCode = DELIVERED
            $billedClientIds = [];
            $totalBillings = 0;
            foreach ($transactions as $transaction) {
                $ppid = $this->extractPricepointId($transaction->result);
                $isDelivered = $this->isTransactionDelivered($transaction->result);
                
                // Seules les transactions avec pricepointId = 63980 ET mnoDeliveryCode = DELIVERED sont comptées
                if ($ppid === $billingPpid && $isDelivered) {
                    // Compter le client comme facturé (une seule fois par client)
                    $billedClientIds[$transaction->client_id] = true;
                    // Compter le nombre total de facturations
                    $totalBillings++;
                }
            }
            
            $billedClients = count($billedClientIds);
            
            Log::info("calculateTimweBillingRate - Clients facturés", [
                'billedClients' => $billedClients,
                'totalTimweClients' => $totalTimweClients,
                'totalTransactions' => $transactions->count(),
                'totalBillings' => $totalBillings,
                'billingPpid' => $billingPpid,
                'period' => $startBound->toDateString() . ' - ' . $endExclusive->copy()->subDay()->toDateString(),
                'formula' => '(Clients avec pricepointId=' . $billingPpid . ' ET mnoDeliveryCode=DELIVERED dans la période) / (Clients avec abonnements Timwe) * 100',
                'filter' => 'pricepointId=' . $billingPpid . ' AND mnoDeliveryCode=DELIVERED'
            ]);
            
            // Calculer le taux de facturation
            $rate = 0.0;
            if ($totalTimweClients > 0) {
                $rate = round(($billedClients / $totalTimweClients) * 100, 2);
            }
            
            Log::info("calculateTimweBillingRate - Résultat final", [
                'billedClients' => $billedClients,
                'totalTimweClients' => $totalTimweClients,
                'totalBillings' => $totalBillings,
                'rate' => $rate
            ]);
            
            return [
                'rate' => $rate,
                'total_clients' => $totalTimweClients,
                'billed_clients' => $billedClients,
                'total_billings' => $totalBillings
            ];
            
        } catch (\Exception $e) {
            Log::error("Erreur lors du calcul du taux de facturation Timwe", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'rate' => 0.0,
                'total_clients' => 0,
                'billed_clients' => 0,
                'total_billings' => 0
            ];
        }
    }
    
    /**
     * Récupère tous les abonnements d'un client spécifique
     */
    public function getUserSubscriptions(int $clientId): array
    {
        try {
            Log::info("getUserSubscriptions - Début", ['client_id' => $clientId]);
            
            // PPID constants pour Timwe
            $billingPpid = env('TIMWE_BILLING_PPID', '63980');
            $trial3DaysPpid = env('TIMWE_FREE_TRIAL_PPID_3_DAYS', '63981');
            $trial30DaysPpid = env('TIMWE_FREE_TRIAL_PPID_30_DAYS', '63982');
            
            // Subquery optimisée pour récupérer toutes les transactions du client
            $transactionsSubquery = DB::table('transactions_history')
                ->select(['result', 'created_at'])
                ->where('client_id', $clientId)
                ->where(function($q) {
                    $q->where('status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                      ->orWhere('status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
                })
                ->orderBy('created_at', 'asc');
            
            $subscriptions = DB::table('client_abonnement as ca')
                ->leftJoin('client as c', 'ca.client_id', '=', 'c.client_id')
                ->leftJoin('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
                ->leftJoin('abonnement_tarifs as at', 'ca.tarif_id', '=', 'at.abonnement_tarifs_id')
                ->leftJoin('abonnement as a', 'at.abonnement_id', '=', 'a.abonnement_id')
                ->select([
                    'ca.client_abonnement_id',
                    'ca.client_id',
                    'c.client_prenom as first_name',
                    'c.client_nom as last_name',
                    'c.client_telephone as phone',
                    'cpm.country_payments_methods_name as operator',
                    'ca.client_abonnement_creation as activation_date',
                    'ca.client_abonnement_expiration as end_date',
                    'ca.subscription_type',
                    'a.abonnement_nom as subscription_name',
                    'at.abonnement_tarifs_prix as price',
                    DB::raw("CASE 
                        -- Pour Timwe : 3 jours = Trial, ~30 jours = Mensuel (fallback sur durée)
                        WHEN LOWER(TRIM(cpm.country_payments_methods_name)) LIKE '%timwe%' THEN
                            CASE 
                                WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) = 3 THEN 'Trial'
                                WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) BETWEEN 20 AND 40 THEN 'Mensuel'
                                ELSE 'Mensuel'
                            END
                        -- Autres opérateurs : logique par durée
                        WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) = 1 THEN 'Journalier'
                        WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) BETWEEN 20 AND 40 THEN 'Mensuel'
                        WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) >= 330 THEN 'Annuel'
                        ELSE 'Autre'
                    END as plan"),
                    DB::raw("CASE 
                        WHEN ca.client_abonnement_expiration IS NULL OR ca.client_abonnement_expiration >= NOW() THEN 'Actif'
                        ELSE 'Expiré'
                    END as status")
                ])
                ->where('ca.client_id', $clientId)
                ->orderByDesc('ca.client_abonnement_creation')
                ->get();
            
            // Récupérer les transactions pour déterminer le pricepointId (une seule fois pour tous les abonnements)
            $transactions = $transactionsSubquery->get();
            
            // Corriger le plan basé sur pricepointId pour chaque abonnement Timwe
            // ET corriger le prix pour les plans Trial (doit être 0)
            $subscriptionsArray = $subscriptions->map(function($subscription) use ($transactions, $billingPpid, $trial3DaysPpid, $trial30DaysPpid) {
                $subArray = (array)$subscription;
                $operator = $subArray['operator'] ?? '';
                $activationDate = $subArray['activation_date'] ?? null;
                $endDate = $subArray['end_date'] ?? null;
                
                // Pour Timwe, déterminer le plan basé sur pricepointId ET la durée de l'abonnement
                if (stripos($operator, 'timwe') !== false && $transactions->isNotEmpty() && $activationDate && $endDate) {
                    // Calculer la durée de l'abonnement
                    $duration = Carbon::parse($activationDate)->diffInDays(Carbon::parse($endDate));
                    
                    // Trouver la transaction la plus proche de la date d'activation
                    $relevantTransaction = $transactions->sortBy(function($t) use ($activationDate) {
                        return abs(Carbon::parse($t->created_at)->diffInSeconds(Carbon::parse($activationDate)));
                    })->first();
                    
                    if ($relevantTransaction && $relevantTransaction->result) {
                        $ppid = $this->extractPricepointId($relevantTransaction->result);
                        
                        // Logique finale : PRIORITÉ à la DURÉE (3j ET 30j) puis PPID
                        // 1. Durée = 3 jours → TOUJOURS Trial gratuit
                        // 2. Durée ≈ 30 jours → TOUJOURS Mensuel payant (peu importe PPID)
                        //    → Un cycle de 30j est un abonnement Mensuel complet
                        // 3. Autres durées → Utiliser le PPID pour déterminer
                        
                        if ($duration === 3) {
                            // Durée = 3 jours → TOUJOURS Trial gratuit
                            $subArray['plan'] = 'Trial';
                            $subArray['price'] = 0;
                        } elseif ($duration >= 20 && $duration <= 40) {
                            // Durée ≈ 30 jours → TOUJOURS Mensuel payant (peu importe PPID)
                            $subArray['plan'] = 'Mensuel';
                            // Prix reste celui de la base de données (3 DT)
                        } elseif ($ppid === $trial3DaysPpid || $ppid === $trial30DaysPpid) {
                            // PPID Trial pour autres durées → Trial gratuit
                            $subArray['plan'] = 'Trial';
                            $subArray['price'] = 0;
                        } elseif ($ppid === $billingPpid) {
                            // PPID Billing pour autres durées → Mensuel payant
                            $subArray['plan'] = 'Mensuel';
                        } else {
                            // PPID inconnu → fallback sur Trial
                            $subArray['plan'] = 'Trial';
                            $subArray['price'] = 0;
                        }
                    }
                }
                
                // ⭐ CORRECTION DU PRIX : Les plans Trial sont toujours gratuits (0 TND)
                if (isset($subArray['plan']) && $subArray['plan'] === 'Trial') {
                    $subArray['price'] = 0;
                }
                
                return $subArray;
            })->toArray();
            
            Log::info("getUserSubscriptions - Résultat", [
                'client_id' => $clientId,
                'count' => count($subscriptionsArray)
            ]);
            
            return [
                'success' => true,
                'client_id' => $clientId,
                'total_subscriptions' => count($subscriptionsArray),
                'subscriptions' => $subscriptionsArray
            ];
            
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des abonnements du client {$clientId}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'client_id' => $clientId,
                'subscriptions' => []
            ];
        }
    }
    
    /**
     * Calculer le taux de facturation et les KPIs Ooredoo/DGV
     */
    private function calculateOoredooBillingRate(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        try {
            // Essayer d'utiliser la table de cache d'abord
            $endDate = $endExclusive->copy()->subDay();
            
            Log::info("calculateOoredooBillingRate - DÉBUT", [
                'start' => $startBound->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ]);
            
            $stats = \App\Models\OoredooDailyStat::getStatsForPeriod($startBound, $endDate);

            Log::info("calculateOoredooBillingRate - Stats récupérées", [
                'count' => $stats->count()
            ]);

            if ($stats->isNotEmpty()) {
                // Utiliser les données de la table de cache
                $lastDayStat = $stats->last();
                
                $result = [
                    'rate' => $lastDayStat->billing_rate,
                    'total_clients' => $lastDayStat->total_clients,
                    'billed_clients' => 0,
                    'total_billings' => $stats->sum('total_billings')
                ];
                
                Log::info("calculateOoredooBillingRate - RETOUR avec données", $result);
                return $result;
            }

            // Si pas de données dans le cache, retourner 0 et loguer un avertissement
            $periodDays = $startBound->diffInDays($endExclusive);
            Log::warning("calculateOoredooBillingRate - Aucune donnée dans le cache", [
                'period_days' => $periodDays,
                'start' => $startBound->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'suggestion' => 'Exécuter: php artisan ooredoo:calculate-historical --start-date=' . $startBound->format('Y-m-d') . ' --end-date=' . $endDate->format('Y-m-d')
            ]);
            
            return [
                'rate' => 0.0,
                'total_clients' => 0,
                'billed_clients' => 0,
                'total_billings' => 0
            ];

            // Récupérer les IDs d'opérateurs Ooredoo
            $ooredooOperatorIds = DB::table('country_payments_methods')
                ->whereRaw("TRIM(country_payments_methods_name) LIKE ?", ['%Ooredoo%'])
                ->pluck('country_payments_methods_id')
                ->toArray();
            
            if (empty($ooredooOperatorIds)) {
                return [
                    'rate' => 0.0,
                    'total_clients' => 0,
                    'billed_clients' => 0,
                    'total_billings' => 0
                ];
            }
            
            // Total clients Ooredoo actifs à la fin de la période
            $totalClients = DB::table('client_abonnement as ca')
                ->whereIn('ca.country_payments_methods_id', $ooredooOperatorIds)
                ->where('ca.client_abonnement_creation', '<=', $endExclusive)
                ->where(function($q) use ($endExclusive) {
                    $q->whereNull('ca.client_abonnement_expiration')
                      ->orWhere('ca.client_abonnement_expiration', '>', $endExclusive);
                })
                ->distinct('ca.client_id')
                ->count('ca.client_id');
            
            if ($totalClients == 0) {
                return [
                    'rate' => 0.0,
                    'total_clients' => 0,
                    'billed_clients' => 0,
                    'total_billings' => 0
                ];
            }
            
            // Total facturations Ooredoo dans la période (type=INVOICE)
            $totalBillings = DB::table('transactions_history')
                ->whereIn('status', ['OOREDOO_PAYMENT_OFFLINE_INIT', 'OOREDOO_PAYMENT_OFFLINE'])
                ->whereBetween('created_at', [$startBound, $endExclusive])
                ->whereRaw("JSON_EXTRACT(result, '$.type') = 'INVOICE'")
                ->whereRaw("JSON_EXTRACT(result, '$.status') = 'SUCCESS'")
                ->count();
            
            $billingRate = $totalClients > 0 ? ($totalBillings / $totalClients) * 100 : 0;
            
            return [
                'rate' => round($billingRate, 2),
                'total_clients' => $totalClients,
                'billed_clients' => 0,
                'total_billings' => $totalBillings
            ];
            
        } catch (\Exception $e) {
            Log::error("calculateOoredooBillingRate - Erreur: " . $e->getMessage());
            return [
                'rate' => 0.0,
                'total_clients' => 0,
                'billed_clients' => 0,
                'total_billings' => 0
            ];
        }
    }

    /**
     * Récupère les statistiques quotidiennes Ooredoo/DGV pour affichage dans le tableau
     * OPTIMISATION: Les données sont pré-calculées dans ooredoo_daily_stats, pas de timeout
     */
    private function getOoredooDailyStatistics(Carbon $startBound, Carbon $endExclusive): array
    {
        try {
            $endDate = $endExclusive->copy()->subDay();
            $periodDays = $startBound->diffInDays($endDate) + 1;
            
            Log::info("getOoredooDailyStatistics - Récupération depuis cache", [
                'period_days' => $periodDays,
                'start' => $startBound->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ]);
            
            // Récupération depuis la table de cache - pas de limitation de période
            // Les données sont pré-calculées quotidiennement via les commandes artisan
            $stats = \App\Models\OoredooDailyStat::getStatsForPeriod($startBound, $endDate);

            if ($stats->isEmpty()) {
                Log::warning("getOoredooDailyStatistics - Aucune donnée trouvée, exécuter: php artisan ooredoo:calculate-historical");
                return [];
            }

            Log::info("getOoredooDailyStatistics - Stats récupérées: " . $stats->count() . " jours");
            return $stats->toArray();
            
        } catch (\Exception $e) {
            Log::error("getOoredooDailyStatistics - Erreur: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupère les statistiques quotidiennes (similaire au tableau Eklektik) - VERSION OPTIMISÉE
     * 
     * Retourne un tableau avec les colonnes :
     * - dimension (date)
     * - offre (nom de l'offre)
     * - new_sub (nouveaux abonnements)
     * - unsub (désabonnements)
     * - simchurn (abonnements créés et expirés le même jour)
     * - rev_simchurn (revenu des simchurn - calculé depuis transactions_history)
     * - active_sub (abonnés actifs)
     * - nb_facturation (nombre de facturations)
     * - taux_facturation (taux de facturation %)
     * - revenu_ttc_local (revenu total TTC en devise locale)
     * - revenu_ttc_usd (revenu total TTC en USD)
     * - revenu_ttc_tnd (revenu total TTC en TND)
     */
    private function getDailyStatistics(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        try {
            // Essayer d'utiliser la table de cache d'abord
            $endDate = $endExclusive->copy()->subDay();
            $periodDays = $startBound->diffInDays($endDate) + 1;
            
            Log::info("getDailyStatistics - Récupération depuis cache Timwe", [
                'period_days' => $periodDays,
                'start' => $startBound->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ]);
            
            // Récupération depuis la table de cache - pas de limitation de période
            $stats = TimweDailyStat::getStatsForPeriod($startBound, $endDate);
            $missingDays = $periodDays - $stats->count();
            
            // Seulement calculer les jours manquants si :
            // 1. Il y a moins de 7 jours manquants
            // 2. La période totale est < 30 jours
            if ($missingDays > 0 && $missingDays <= 7 && $periodDays <= 30) {
                Log::info("getDailyStatistics - Calcul des jours manquants", [
                    'missing_days' => $missingDays
                ]);
                
                // Calculer les jours manquants silencieusement
                $existingDates = $stats->pluck('stat_date')->map(function($date) {
                    return $date->format('Y-m-d');
                })->toArray();
                
                $currentDate = $startBound->copy();
                while ($currentDate->lte($endDate)) {
                    if (!in_array($currentDate->format('Y-m-d'), $existingDates)) {
                        $this->timweStatsService->calculateAndStoreStatsForDate($currentDate);
                    }
                    $currentDate->addDay();
                }
                
                // Recharger les stats après calcul
                $stats = TimweDailyStat::getStatsForPeriod($startBound, $endDate);
            } elseif ($missingDays > 0) {
                Log::warning("getDailyStatistics - Données Timwe incomplètes", [
                    'missing_days' => $missingDays,
                    'found_days' => $stats->count(),
                    'expected_days' => $periodDays,
                    'suggestion' => 'Exécuter: php artisan timwe:calculate-historical --from=' . $startBound->format('Y-m-d') . ' --to=' . $endDate->format('Y-m-d') . ' --force'
                ]);
            }

            if ($stats->isNotEmpty()) {
                // Convertir les stats du cache au format attendu
                $dailyStats = [];
                
                foreach ($stats as $stat) {
                    // Récupérer le détail par offre depuis le JSON
                    $offersBreakdown = $stat->offers_breakdown ?? [];
                    
                    if (!empty($offersBreakdown)) {
                        // Créer une ligne par offre
                        foreach ($offersBreakdown as $offer) {
                            $dailyStats[] = [
                                'dimension' => $stat->stat_date->format('Y-m-d'),
                                'offre' => $offer->offre_name ?? 'N/A',
                                'new_sub' => $offer->count ?? 0,
                                'unsub' => 0, // Non détaillé par offre
                                'simchurn' => 0, // Non détaillé par offre
                                'rev_simchurn' => 0,
                                'active_sub' => $stat->active_subscriptions,
                                'nb_facturation' => $stat->total_billings,
                                'taux_facturation' => $stat->billing_rate,
                                'revenu_ttc_local' => $stat->revenue_tnd,
                                'revenu_ttc_usd' => $stat->revenue_usd,
                                'revenu_ttc_tnd' => $stat->revenue_tnd
                            ];
                        }
                    } else {
                        // Pas de détail par offre, créer une ligne générale
                        $dailyStats[] = [
                            'dimension' => $stat->stat_date->format('Y-m-d'),
                            'offre' => 'Timwe (Total)',
                            'new_sub' => $stat->new_subscriptions,
                            'unsub' => $stat->unsubscriptions,
                            'simchurn' => $stat->simchurn,
                            'rev_simchurn' => $stat->simchurn_revenue,
                            'active_sub' => $stat->active_subscriptions,
                            'nb_facturation' => $stat->total_billings,
                            'taux_facturation' => $stat->billing_rate,
                            'revenu_ttc_local' => $stat->revenue_tnd,
                            'revenu_ttc_usd' => $stat->revenue_usd,
                            'revenu_ttc_tnd' => $stat->revenue_tnd
                        ];
                    }
                }

                return $dailyStats;
            }

            // Si pas de données dans le cache, vérifier si on peut calculer à la volée
            $periodDays = $startBound->diffInDays($endExclusive);
            
            if ($periodDays > 90) {
                // Période trop longue sans cache, retourner vide
                return [];
            }

            // Code de calcul à la volée pour les périodes courtes uniquement
            // Récupérer tous les IDs d'opérateurs Timwe
            $timweOperatorIds = DB::table('country_payments_methods')
                ->whereRaw("LOWER(country_payments_methods_name) LIKE ?", ['%timwe%'])
                ->pluck('country_payments_methods_id')
                ->toArray();
            
            if (empty($timweOperatorIds)) {
                Log::warning("getDailyStatistics - Aucun opérateur Timwe trouvé !");
                return [];
            }
            
            // PPID constants pour Timwe
            $billingPpid = env('TIMWE_BILLING_PPID', '63980');
            
            // 1. Nouveaux abonnements par jour
            $newSubsQuery = DB::table('client_abonnement as ca')
                ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
                ->whereBetween('ca.client_abonnement_creation', [$startBound, $endExclusive->copy()->subSecond()])
                ->select(DB::raw('DATE(ca.client_abonnement_creation) as date'), DB::raw('COUNT(*) as count'));
            $newSubsByDayRaw = $newSubsQuery->groupBy(DB::raw('DATE(ca.client_abonnement_creation)'))->get();
            
            Log::info("getDailyStatistics - Nouveaux abonnements", [
                'count' => count($newSubsByDayRaw),
                'sample' => $newSubsByDayRaw->take(3)->toArray()
            ]);
            $newSubsByDay = [];
            foreach ($newSubsByDayRaw as $row) {
                $dateKey = Carbon::parse($row->date)->format('Y-m-d');
                $newSubsByDay[$dateKey] = (int)$row->count;
            }
            
            // 2. Désabonnements par jour
            $unsubsQuery = DB::table('client_abonnement as ca')
                ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
                ->whereNotNull('ca.client_abonnement_expiration')
                ->whereBetween('ca.client_abonnement_expiration', [$startBound, $endExclusive->copy()->subSecond()])
                ->select(DB::raw('DATE(ca.client_abonnement_expiration) as date'), DB::raw('COUNT(*) as count'));
            $unsubsByDayRaw = $unsubsQuery->groupBy(DB::raw('DATE(ca.client_abonnement_expiration)'))->get();
            
            Log::info("getDailyStatistics - Désabonnements", [
                'count' => count($unsubsByDayRaw),
                'sample' => $unsubsByDayRaw->take(3)->toArray()
            ]);
            $unsubsByDay = [];
            foreach ($unsubsByDayRaw as $row) {
                $dateKey = Carbon::parse($row->date)->format('Y-m-d');
                $unsubsByDay[$dateKey] = (int)$row->count;
            }
            
            // 3. Simchurn par jour (créés ET expirés le même jour) + calcul du revenu
            $simchurnQuery = DB::table('client_abonnement as ca')
                ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
                ->whereBetween('ca.client_abonnement_creation', [$startBound, $endExclusive->copy()->subSecond()])
                ->whereNotNull('ca.client_abonnement_expiration')
                ->whereColumn(DB::raw('DATE(ca.client_abonnement_creation)'), DB::raw('DATE(ca.client_abonnement_expiration)'))
                ->select(
                    DB::raw('DATE(ca.client_abonnement_creation) as date'),
                    'ca.client_abonnement_id',
                    'ca.client_id'
                );
            $simchurnByDayRaw = $simchurnQuery->get();
            
            Log::info("getDailyStatistics - Simchurn", [
                'count' => count($simchurnByDayRaw)
            ]);
            $simchurnByDay = [];
            $simchurnRevenueByDay = [];
            
            // Grouper par date et calculer le revenu
            foreach ($simchurnByDayRaw as $row) {
                $dateKey = Carbon::parse($row->date)->format('Y-m-d');
                if (!isset($simchurnByDay[$dateKey])) {
                    $simchurnByDay[$dateKey] = 0;
                    $simchurnRevenueByDay[$dateKey] = 0;
                }
                $simchurnByDay[$dateKey]++;
                
                // Récupérer le revenu pour ce simchurn depuis transactions_history
                $simchurnTransaction = DB::table('transactions_history as th')
                    ->where('th.client_id', $row->client_id)
                    ->where(function($q) {
                        $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                          ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
                    })
                    ->whereDate('th.created_at', $dateKey)
                    ->orderBy('th.created_at', 'desc')
                    ->first();
                
                if ($simchurnTransaction && $simchurnTransaction->result) {
                    $ppid = $this->extractPricepointId($simchurnTransaction->result);
                    $isDelivered = $this->isTransactionDelivered($simchurnTransaction->result);
                    $totalCharged = $this->extractTotalCharged($simchurnTransaction->result);
                    
                    if ($ppid === $billingPpid && $isDelivered && $totalCharged > 0) {
                        $simchurnRevenueByDay[$dateKey] += $totalCharged;
                    }
                }
            }
            
            // 4. Facturations par jour - OPTIMISÉ : Traiter par chunks pour éviter la saturation mémoire
            $billingsByDay = [];
            $revenueByDay = [];
            
            // Récupérer les transactions par chunks pour éviter la saturation mémoire
            $chunkSize = 500; // Réduire la taille des chunks
            $hasMore = true;
            $lastId = 0;
            
            while ($hasMore) {
                $billingsChunk = DB::table('transactions_history as th')
                    ->join('client_abonnement as ca', 'th.client_id', '=', 'ca.client_id')
                    ->leftJoin('abonnement_tarifs as at', 'ca.tarif_id', '=', 'at.abonnement_tarifs_id')
                    ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
                    ->whereBetween('th.created_at', [$startBound, $endExclusive->copy()->subSecond()])
                    ->where('th.transaction_history_id', '>', $lastId)
                    ->where(function($q) {
                        $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                          ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%')
                          ->orWhere('th.status', 'LIKE', '%RENEWED%')
                          ->orWhere('th.status', 'LIKE', '%CHARGE_DELIVERED%');
                    })
                    ->select(
                        'th.transaction_history_id',
                        DB::raw('DATE(th.created_at) as date'),
                        'th.result',
                        'at.abonnement_tarifs_prix as tarif_prix'
                    )
                    ->orderBy('th.transaction_history_id', 'asc')
                    ->limit($chunkSize);
                
                $billingsRaw = $billingsChunk->get();
                
                if ($lastId === 0) {
                    Log::info("getDailyStatistics - Facturations (premier chunk)", [
                        'count' => count($billingsRaw),
                        'timweOperatorIds' => $timweOperatorIds
                    ]);
                }
                
                if ($billingsRaw->isEmpty()) {
                    $hasMore = false;
                    break;
                }
                
                // Traiter le chunk
                foreach ($billingsRaw as $billing) {
                    $lastId = $billing->transaction_history_id;
                    
                    $ppid = $this->extractPricepointId($billing->result);
                    $isDelivered = $this->isTransactionDelivered($billing->result);
                    
                    // Seules les transactions avec pricepointId = 63980 ET mnoDeliveryCode = DELIVERED ET totalCharged > 0
                    $totalCharged = $this->extractTotalCharged($billing->result);
                    
                    if ($ppid === $billingPpid && $isDelivered && $totalCharged > 0) {
                        $date = Carbon::parse($billing->date)->format('Y-m-d');
                        if (!isset($billingsByDay[$date])) {
                            $billingsByDay[$date] = 0;
                            $revenueByDay[$date] = 0;
                        }
                        $billingsByDay[$date]++;
                        
                        // Le montant est toujours trouvé car totalCharged > 0 est garanti
                        $revenueByDay[$date] += $totalCharged;
                    }
                }
                
                // Récupérer le count avant de libérer la mémoire
                $count = $billingsRaw->count();
                
                // Libérer la mémoire immédiatement
                unset($billingsRaw);
                
                // Si on a moins de résultats que le chunk size, on a fini
                if ($count < $chunkSize) {
                    $hasMore = false;
                }
            }
            
            // 5. Abonnés actifs par jour - Logique normale : compter les abonnements actifs à la fin de chaque journée
            // Un abonnement est actif si : créé avant ou le jour J ET (pas d'expiration OU expiration après le jour J)
            $activeSubsByDayRaw = [];
            $currentDateForActive = $startBound->copy();
            $endDateForActive = $endExclusive->copy()->subDay();
            
            while ($currentDateForActive->lte($endDateForActive)) {
                $dateStr = $currentDateForActive->format('Y-m-d');
                $endOfDay = $currentDateForActive->copy()->endOfDay();
                
                $activeSubsQuery = DB::table('client_abonnement as ca')
                    ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
                    ->where('ca.client_abonnement_creation', '<=', $endOfDay)
                    ->where(function($q) use ($endOfDay) {
                        $q->whereNull('ca.client_abonnement_expiration')
                          ->orWhere('ca.client_abonnement_expiration', '>', $endOfDay);
                    });
                
                $activeCount = $activeSubsQuery->count();
                $activeSubsByDayRaw[$dateStr] = (int)$activeCount;
                
                $currentDateForActive->addDay();
            }
            
            Log::info("getDailyStatistics - Abonnés actifs calculés", [
                'daysCount' => count($activeSubsByDayRaw),
                'sample' => array_slice($activeSubsByDayRaw, 0, 3, true)
            ]);
            
            // 6. Récupérer les noms d'offres par jour
            $offersQuery = DB::table('client_abonnement as ca')
                ->leftJoin('abonnement_tarifs as at', 'ca.tarif_id', '=', 'at.abonnement_tarifs_id')
                ->leftJoin('abonnement as a', 'at.abonnement_id', '=', 'a.abonnement_id')
                ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
                ->whereBetween('ca.client_abonnement_creation', [$startBound, $endExclusive->copy()->subSecond()])
                ->select(DB::raw('DATE(ca.client_abonnement_creation) as date'), DB::raw('MAX(a.abonnement_nom) as offer_name'));
            $offersByDayRaw = $offersQuery->groupBy(DB::raw('DATE(ca.client_abonnement_creation)'))->get();
            $offersByDay = [];
            foreach ($offersByDayRaw as $row) {
                $dateKey = Carbon::parse($row->date)->format('Y-m-d');
                $offersByDay[$dateKey] = $row->offer_name ?? 'N/A';
            }
            
            // Construire le tableau final - une ligne par jour de la période
            $statistics = [];
            $currentDate = $startBound->copy();
            $endDate = $endExclusive->copy()->subDay(); // Inclure le dernier jour de la période
            
            // Démarrer le timer juste avant la boucle (les requêtes SQL sont déjà terminées)
            $loopStartTs = microtime(true);
            
            Log::info("getDailyStatistics - Construction du tableau", [
                'startDate' => $currentDate->toDateString(),
                'endDate' => $endDate->toDateString(),
                'endExclusive' => $endExclusive->toDateString(),
                'periodDays' => $periodDays,
                'newSubsByDay_count' => count($newSubsByDay),
                'unsubsByDay_count' => count($unsubsByDay),
                'simchurnByDay_count' => count($simchurnByDay),
                'activeSubsByDayRaw_count' => count($activeSubsByDayRaw),
                'billingsByDay_count' => count($billingsByDay),
                'offersByDay_count' => count($offersByDay),
                'newSubsByDay_sample' => array_slice($newSubsByDay, 0, 3, true)
            ]);
            
            $loopCount = 0;
            while ($currentDate->lte($endDate)) {
                $loopCount++;
                $dateStr = $currentDate->format('Y-m-d');
                
                $newSubs = $newSubsByDay[$dateStr] ?? 0;
                $unsubs = $unsubsByDay[$dateStr] ?? 0;
                $simchurn = $simchurnByDay[$dateStr] ?? 0;
                $activeSubs = $activeSubsByDayRaw[$dateStr] ?? 0;
                $nbFacturation = $billingsByDay[$dateStr] ?? 0;
                $offerName = $offersByDay[$dateStr] ?? 'N/A';
                
                // Taux de facturation
                $tauxFacturation = $activeSubs > 0 ? round(($nbFacturation / $activeSubs) * 100, 2) : 0;
                
                // Revenu TTC réel depuis les transactions (en TND)
                $revenuTTC = $revenueByDay[$dateStr] ?? 0;
                // Conversion USD (taux approximatif 1 USD = 2.915 TND, donc 1 TND = 0.343 USD)
                $revenuTTCUSD = $revenuTTC * 0.343;
                
                $revSimchurn = $simchurnRevenueByDay[$dateStr] ?? 0;
                
                $statistics[] = [
                    'dimension' => $dateStr,
                    'offre' => $offerName,
                    'new_sub' => (int)$newSubs,
                    'unsub' => (int)$unsubs,
                    'simchurn' => (int)$simchurn,
                    'rev_simchurn' => round($revSimchurn, 2),
                    'active_sub' => (int)$activeSubs,
                    'nb_facturation' => (int)$nbFacturation,
                    'taux_facturation' => round($tauxFacturation, 2),
                    'revenu_ttc_local' => round($revenuTTC, 2),
                    'revenu_ttc_usd' => round($revenuTTCUSD, 2),
                    'revenu_ttc_tnd' => round($revenuTTC, 2)
                ];
                
                $currentDate->addDay();

                // Vérifier le timeout uniquement pour la boucle (pas pour les requêtes SQL)
                // La boucle devrait être très rapide, donc on peut utiliser un timeout plus court
                if ((microtime(true) - $loopStartTs) > 10) {
                    Log::warning("getDailyStatistics - Arrêt anticipé de la boucle pour éviter timeout", [
                        'built' => count($statistics),
                        'periodDays' => $periodDays,
                        'currentDate' => $currentDate->toDateString(),
                        'endDate' => $endDate->toDateString(),
                        'loopCount' => $loopCount
                    ]);
                    break;
                }
            }
            
            Log::info("getDailyStatistics - Boucle terminée", [
                'loopCount' => $loopCount,
                'statisticsCount' => count($statistics),
                'finalCurrentDate' => $currentDate->toDateString(),
                'endDate' => $endDate->toDateString(),
                'loopTime' => round(microtime(true) - $loopStartTs, 2) . 's'
            ]);
            
            Log::info("getDailyStatistics - Résultat", [
                'count' => count($statistics),
                'periodDays' => $periodDays,
                'startBound' => $startBound->toDateString(),
                'endExclusive' => $endExclusive->toDateString(),
                'sample' => $statistics[0] ?? null,
                'last' => $statistics[count($statistics) - 1] ?? null
            ]);
            
            return $statistics;
            
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des statistiques quotidiennes: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return [];
        }
    }
    
    /**
     * Extrait le montant depuis le champ result JSON d'une transaction
     */
    private function extractAmountFromResult($result): float
    {
        if (empty($result)) {
            return 0.0;
        }
        
        try {
            // Si c'est déjà un tableau (Laravel peut le caster automatiquement)
            if (is_array($result)) {
                $data = $result;
            } elseif (is_string($result)) {
                // Décoder le JSON
                $data = json_decode($result, true);
                if (!$data || json_last_error() !== JSON_ERROR_NONE) {
                    return 0.0;
                }
            } else {
                return 0.0;
            }
            
            // Chercher dans différentes structures possibles (ordre de priorité)
            $amountFields = [
                'amount', 'price', 'cost', 'value', 'total', 
                'montant', 'prix', 'revenue', 'revenu',
                'charge_amount', 'billing_amount', 'transaction_amount',
                'totalCharged', 'total_charged'
            ];
            
            foreach ($amountFields as $field) {
                if (isset($data[$field]) && is_numeric($data[$field])) {
                    $amount = floatval($data[$field]);
                    if ($amount > 0) {
                        return $amount;
                    }
                }
            }
            
            // Chercher dans des sous-objets (ordre de priorité)
            $nestedPaths = [
                ['user', 'amount'],
                ['response', 'amount'],
                ['data', 'amount'],
                ['result', 'amount'],
                ['transaction', 'amount'],
                ['billing', 'amount'],
                ['charge', 'amount'],
                ['user', 'price'],
                ['response', 'price'],
                ['data', 'price'],
                ['user', 'total'],
                ['response', 'total'],
                ['data', 'total'],
                ['user', 'totalCharged'],
                ['response', 'totalCharged']
            ];
            
            foreach ($nestedPaths as $path) {
                $value = $data;
                foreach ($path as $key) {
                    if (!isset($value[$key])) {
                        $value = null;
                        break;
                    }
                    $value = $value[$key];
                }
                if ($value !== null && is_numeric($value)) {
                    $amount = floatval($value);
                    if ($amount > 0) {
                        return $amount;
                    }
                }
            }
            
            // Si aucun montant trouvé, retourner 0
            return 0.0;
            
        } catch (\Exception $e) {
            return 0.0;
        }
    }
    
    /**
     * Calculer les activations par méthode de paiement
     */
    private function calculateActivationsByPaymentMethod(Carbon $startBound, Carbon $endExclusive, string $operatorFilter): array
    {
        try {
            $query = DB::table('client_abonnement as ca')
                ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
                ->where('ca.client_abonnement_creation', '>=', $startBound)
                ->where('ca.client_abonnement_creation', '<', $endExclusive);
            
            $this->applyOperatorFilter($query, $operatorFilter);
            
            $rows = $query->select('cpm.country_payments_methods_name as cpm_name', DB::raw('COUNT(*) as cnt'))
                ->groupBy('cpm.country_payments_methods_name')
                ->get();
            
            $totals = ['cb' => 0, 'recharge' => 0, 'phone_balance' => 0, 'other' => 0];
            
            foreach ($rows as $row) {
                $name = mb_strtolower($row->cpm_name);
                
                // CB: cibler explicitement la carte bancaire
                if (str_contains($name, 'banc') || str_contains($name, 'cb')) {
                    $totals['cb'] += (int) $row->cnt;
                // Recharge: cartes cadeaux / vouchers / recharge
                } elseif (str_contains($name, 'cadeau') || str_contains($name, 'voucher') || str_contains($name, 'recharg')) {
                    $totals['recharge'] += (int) $row->cnt;
                // Solde téléphonique / opérateurs (agrégateurs)
                } elseif (
                    str_contains($name, 'solde') ||
                    str_contains($name, 'téléphon') || str_contains($name, 'teleph') ||
                    str_contains($name, 'orange') || str_contains($name, " tt") || str_contains($name, 'timwe')
                ) {
                    $totals['phone_balance'] += (int) $row->cnt;
                } else {
                    $totals['other'] += (int) $row->cnt;
                }
            }
            
            return $totals;
        } catch (\Exception $e) {
            Log::error("Erreur calcul activations par méthode de paiement: " . $e->getMessage());
            return ['cb' => 0, 'recharge' => 0, 'phone_balance' => 0, 'other' => 0];
        }
    }
    
    /**
     * Calculer la répartition par plan d'abonnement
     */
    private function calculatePlanDistribution(Carbon $startBound, Carbon $endExclusive, string $operatorFilter): array
    {
        try {
            $query = DB::table('client_abonnement as ca')
                ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
                ->where('ca.client_abonnement_creation', '>=', $startBound)
                ->where('ca.client_abonnement_creation', '<', $endExclusive);
            
            $this->applyOperatorFilter($query, $operatorFilter);
            
            $subs = $query->select('ca.client_abonnement_creation', 'ca.client_abonnement_expiration', 'cpm.country_payments_methods_name as cpm_name')->get();
            
            $totals = ['daily' => 0, 'monthly' => 0, 'annual' => 0, 'other' => 0];
            foreach ($subs as $s) {
                $name = mb_strtolower($s->cpm_name ?? '');
                
                $isTimwe = str_contains($name, 'timwe');
                $isPhoneBalance = (
                    str_contains($name, 'solde') ||
                    str_contains($name, 'téléphon') || str_contains($name, 'teleph') ||
                    str_contains($name, 'orange') || str_contains($name, ' tt')
                );
                $isCarteRecharge = (
                    str_contains($name, 'carte') ||
                    str_contains($name, 'cadeau') ||
                    str_contains($name, 'recharge')
                );
                
                // RÈGLE 1: Timwe = toujours Mensuel
                if ($isTimwe) {
                    $totals['monthly']++;
                    continue;
                }
                
                // RÈGLE 2: Solde téléphonique (sauf Timwe) = toujours Journalier  
                if ($isPhoneBalance) {
                    $totals['daily']++;
                    continue;
                }
                
                // RÈGLE 3: Cartes cadeaux - calculer la durée exacte
                if ($isCarteRecharge) {
                    if (empty($s->client_abonnement_expiration)) {
                        $totals['other']++;
                        continue;
                    }
                    
                    $days = Carbon::parse($s->client_abonnement_creation)->diffInDays(Carbon::parse($s->client_abonnement_expiration));
                    if ($days == 1) {
                        $totals['daily']++;
                    } elseif ($days == 30) {
                        $totals['monthly']++;
                    } elseif ($days == 365) {
                        $totals['annual']++;
                    } else {
                        $totals['other']++;
                    }
                    continue;
                }
                
                // RÈGLE 4: Autres méthodes - classification par défaut
                if (empty($s->client_abonnement_expiration)) {
                    $totals['other']++;
                } else {
                    $days = Carbon::parse($s->client_abonnement_creation)->diffInDays(Carbon::parse($s->client_abonnement_expiration));
                    if ($days <= 2) {
                        $totals['daily']++;
                    } elseif ($days >= 20 && $days <= 40) {
                        $totals['monthly']++;
                    } elseif ($days >= 330) {
                        $totals['annual']++;
                    } else {
                        $totals['other']++;
                    }
                }
            }
            
            return $totals;
        } catch (\Exception $e) {
            Log::error("Erreur calcul répartition par plan: " . $e->getMessage());
            return ['daily' => 0, 'monthly' => 0, 'annual' => 0, 'other' => 0];
        }
    }
    
    /**
     * Calculer l'analyse de cohortes (survie J+30 et J+60)
     */
    private function calculateCohorts(string $startDate, string $endDate, string $operatorFilter): array
    {
        try {
            $cohorts = [];
            // Utiliser la date de fin pour calculer les 6 derniers mois (comme dans l'ancien contrôleur)
            $endCarbon = Carbon::parse($endDate);
            
            for ($i = 5; $i >= 0; $i--) {
                $cohortMonth = $endCarbon->copy()->subMonths($i);
                $monthStart = $cohortMonth->copy()->startOfMonth();
                $monthEnd = $cohortMonth->copy()->endOfMonth();
                
                $query = DB::table('client_abonnement as ca')
                    ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
                    ->whereBetween('ca.client_abonnement_creation', [$monthStart, $monthEnd]);
                
                $this->applyOperatorFilter($query, $operatorFilter);
                
                $totalSubscribers = $query->count();
                
                if ($totalSubscribers == 0) {
                    $cohorts[] = [
                        'month' => $cohortMonth->format('M Y'),
                        'total' => 0,
                        'survival_d30' => 0,
                        'survival_d60' => 0
                    ];
                    continue;
                }
                
                // Survivants à J+30
                $survivalD30 = $query->clone()
                    ->where(function($q) use ($monthStart) {
                        $q->whereNull('ca.client_abonnement_expiration')
                          ->orWhere('ca.client_abonnement_expiration', '>=', $monthStart->copy()->addDays(30));
                    })->count();
                
                // Survivants à J+60
                $survivalD60 = $query->clone()
                    ->where(function($q) use ($monthStart) {
                        $q->whereNull('ca.client_abonnement_expiration')
                          ->orWhere('ca.client_abonnement_expiration', '>=', $monthStart->copy()->addDays(60));
                    })->count();
                
                $cohorts[] = [
                    'month' => $cohortMonth->format('M Y'),
                    'total' => $totalSubscribers,
                    'survival_d30' => round(($survivalD30 / $totalSubscribers) * 100, 1),
                    'survival_d60' => round(($survivalD60 / $totalSubscribers) * 100, 1)
                ];
            }
            
            return $cohorts;
        } catch (\Exception $e) {
            Log::error("Erreur calcul cohortes: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculer le taux de renouvellement
     */
    private function calculateRenewalRate(string $startDate, string $endDate, string $operatorFilter): float
    {
        try {
            $endCarbon = Carbon::parse($endDate)->endOfDay();
            
            $expiredQuery = DB::table('client_abonnement as ca')
                ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
                ->whereBetween('ca.client_abonnement_expiration', [$startDate, $endCarbon]);
            
            $this->applyOperatorFilter($expiredQuery, $operatorFilter);
            
            $expiredSubscriptions = $expiredQuery->count();
            if ($expiredSubscriptions == 0) return 0;
            
            $windowDays = 60; // fenêtre de renouvellement
            
            $renewedQuery = DB::table('client_abonnement as ca1')
                ->join('country_payments_methods as cpm1', 'ca1.country_payments_methods_id', '=', 'cpm1.country_payments_methods_id')
                ->join('client_abonnement as ca2', 'ca1.client_id', '=', 'ca2.client_id')
                ->whereBetween('ca1.client_abonnement_expiration', [$startDate, $endCarbon])
                ->where('ca2.client_abonnement_creation', '>', DB::raw('ca1.client_abonnement_expiration'))
                ->where('ca2.client_abonnement_creation', '<=', DB::raw("DATE_ADD(ca1.client_abonnement_expiration, INTERVAL $windowDays DAY)"));
            
            $this->applyOperatorFilter($renewedQuery, $operatorFilter, 'cpm1');
            
            $renewedSubscriptions = $renewedQuery->distinct('ca1.client_abonnement_id')->count();
            
            return round(($renewedSubscriptions / $expiredSubscriptions) * 100, 1);
        } catch (\Exception $e) {
            Log::error("Erreur calcul taux de renouvellement: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Calculer la durée de vie moyenne
     */
    private function calculateAverageLifespan(string $startDate, string $endDate, string $operatorFilter): float
    {
        try {
            $endCarbon = Carbon::parse($endDate)->endOfDay();
            
            $query = DB::table('client_abonnement as ca')
                ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
                ->where('ca.client_abonnement_creation', '>=', $startDate)
                ->where('ca.client_abonnement_creation', '<=', $endCarbon);
            
            $this->applyOperatorFilter($query, $operatorFilter);
            
            $subscriptions = $query->select('ca.client_abonnement_creation', 'ca.client_abonnement_expiration')->get();
            if ($subscriptions->count() == 0) return 0;
            
            $totalDays = 0;
            foreach ($subscriptions as $s) {
                $start = Carbon::parse($s->client_abonnement_creation);
                $end = $s->client_abonnement_expiration ? Carbon::parse($s->client_abonnement_expiration) : Carbon::now();
                $totalDays += $start->diffInDays($end);
            }
            
            return round($totalDays / $subscriptions->count(), 1);
        } catch (\Exception $e) {
            Log::error("Erreur calcul durée de vie moyenne: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Calculer le taux de réactivation
     */
    private function calculateReactivationRate(string $startDate, string $endDate, string $operatorFilter): float
    {
        try {
            $endCarbon = Carbon::parse($endDate)->endOfDay();
            
            // Clients qui ont eu un abonnement expiré avant la période
            $expiredBeforePeriod = DB::table('client_abonnement as ca')
                ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
                ->where('ca.client_abonnement_expiration', '<', $startDate);
            
            $this->applyOperatorFilter($expiredBeforePeriod, $operatorFilter);
            
            $expiredClients = $expiredBeforePeriod->distinct('ca.client_id')->pluck('ca.client_id');
            
            $expiredCount = $expiredClients->count();
            // Éviter l'explosion du nombre de placeholders (erreur 1390) sur de très gros volumes
            if ($expiredCount == 0 || $expiredCount > 15000) {
                Log::warning("calculateReactivationRate - Skipped (too many expired clients)", [
                    'expiredCount' => $expiredCount,
                    'operator' => $operatorFilter
                ]);
                return 0;
            }
            
            // Clients réactivés pendant la période
            $reactivatedQuery = DB::table('client_abonnement as ca')
                ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
                ->whereIn('ca.client_id', $expiredClients)
                ->where('ca.client_abonnement_creation', '>=', $startDate)
                ->where('ca.client_abonnement_creation', '<=', $endCarbon);
            
            $this->applyOperatorFilter($reactivatedQuery, $operatorFilter);
            
            $reactivatedClients = $reactivatedQuery->distinct('ca.client_id')->count();
            
            return round(($reactivatedClients / $expiredClients->count()) * 100, 1);
        } catch (\Exception $e) {
            Log::error("Erreur calcul taux de réactivation: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * DÉSACTIVÉ POUR OPTIMISATION
     * Récupère les transactions Timwe groupées par utilisateur
     * (Renouvellements et désabonnements uniquement)
     * 
     * Cette fonction n'est plus utilisée pour améliorer les performances du dashboard
     */
    private function getTimweTransactionsByUser(Carbon $startBound, Carbon $endExclusive): array
    {
        try {
            // Récupérer les transactions regroupées par client (exclure FROM_TIMWE_RENEWED_NOTIF)
            $transactions = DB::select("
                SELECT 
                    client_id,
                    COUNT(*) as nb_transactions,
                    MAX(transaction_history_id) as derniere_transaction_id,
                    MAX(created_at) as derniere_date,
                    (SELECT status FROM transactions_history th2 
                     WHERE th2.client_id = th.client_id 
                     AND (
                         th2.reference LIKE '%TIMWE-OPTIN%' 
                         OR th2.reference LIKE '%FROM_TIMWE_RENEWED%'
                     )
                     AND (
                         (th2.status LIKE '%TIMWE_RENEWED_NOTIF%' AND th2.status NOT LIKE '%FROM_TIMWE_RENEWED_NOTIF%')
                         OR th2.status LIKE '%UNSUBSCRIPTION%'
                     )
                     ORDER BY th2.transaction_history_id DESC 
                     LIMIT 1
                    ) as last_status_raw,
                    (SELECT IFNULL(
                        MAX(CASE 
                            WHEN JSON_VALID(result) AND JSON_EXTRACT(result, '$.totalCharged') > 0 
                            THEN JSON_EXTRACT(result, '$.totalCharged')
                            ELSE NULL
                        END), 
                        0
                    )
                     FROM transactions_history th3
                     WHERE th3.client_id = th.client_id
                     AND (
                         th3.reference LIKE '%TIMWE-OPTIN%' 
                         OR th3.reference LIKE '%FROM_TIMWE_RENEWED%'
                     )
                    ) as has_billing
                FROM transactions_history th
                WHERE (
                    reference LIKE '%TIMWE-OPTIN%' 
                    OR reference LIKE '%FROM_TIMWE_RENEWED%'
                )
                AND (
                    (status LIKE '%TIMWE_RENEWED_NOTIF%' AND status NOT LIKE '%FROM_TIMWE_RENEWED_NOTIF%')
                    OR status LIKE '%UNSUBSCRIPTION%'
                )
                AND created_at >= ?
                AND created_at < ?
                GROUP BY client_id
                ORDER BY nb_transactions DESC
                LIMIT 500
            ", [$startBound, $endExclusive]);
            
            $result = array_map(function($row) {
                // Déterminer le statut basé sur la facturation
                $hasBilling = $row->has_billing !== null && floatval($row->has_billing) > 0;
                $displayStatus = $hasBilling ? 'RENOUVELÉ' : 'NON RENOUVELÉ';
                
                // Log pour debug (premier client seulement)
                static $first = true;
                if ($first) {
                    Log::info("getTimweTransactionsByUser - Premier client", [
                        'client_id' => $row->client_id,
                        'has_billing' => $row->has_billing,
                        'has_billing_value' => floatval($row->has_billing ?? 0),
                        'hasBilling_bool' => $hasBilling,
                        'displayStatus' => $displayStatus
                    ]);
                    $first = false;
                }
                
                return [
                    'client_id' => $row->client_id,
                    'nb_transactions' => $row->nb_transactions,
                    'derniere_transaction_id' => $row->derniere_transaction_id,
                    'derniere_date' => $row->derniere_date,
                    'last_status' => $displayStatus,
                    'has_billing' => $hasBilling
                ];
            }, $transactions);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error("Erreur récupération transactions Timwe par utilisateur: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Groupe les statistiques quotidiennes Timwe par mois
     * Retourne un tableau avec :
     * - month (ex: "janvier 2025 (12)")  
     * - month_key (ex: "2025-01" pour le tri)
     * - daily_details (array des jours du mois)
     * - totaux mensuels calculés selon le contrat
     */
    private function groupTimweStatsByMonth(array $dailyStats): array
    {
        if (empty($dailyStats)) {
            return [];
        }
        
        $grouped = [];
        $totalStats = count($dailyStats);
        $includeDetails = $totalStats < 500; // Ne garder les détails que si < 500 lignes
        
        Log::info("groupTimweStatsByMonth - Début", [
            'total_stats' => $totalStats,
            'include_details' => $includeDetails
        ]);
        
        foreach ($dailyStats as $stat) {
            $date = Carbon::parse($stat['dimension']);
            $monthKey = $date->format('Y-m'); // Ex: "2025-12"
            $monthLabel = $date->locale('fr')->isoFormat('MMMM YYYY'); // Ex: "décembre 2025"
            
            if (!isset($grouped[$monthKey])) {
                $grouped[$monthKey] = [
                    'month_key' => $monthKey,
                    'month_label' => $monthLabel,
                    'year' => $date->year,
                    'month_num' => $date->month,
                    'daily_details' => [],
                    // Totaux mensuels
                    'total_new_sub' => 0,
                    'total_unsub' => 0,
                    'total_simchurn' => 0,
                    'total_rev_simchurn' => 0,
                    'total_active_sub' => 0, // On prendra le dernier jour du mois
                    'total_nb_facturation' => 0,
                    'total_taux_facturation' => 0,
                    'sum_taux_facturation' => 0, // Pour calculer la moyenne
                    'total_revenu_ttc_tnd' => 0,
                    'ca_bigdeal_ht' => 0,
                    'days_count' => 0
                ];
            }
            
            // Ajouter les détails du jour seulement si pas trop de données
            if ($includeDetails) {
                $grouped[$monthKey]['daily_details'][] = $stat;
            }
            
            // Cumuler les totaux
            $grouped[$monthKey]['total_new_sub'] += floatval($stat['new_sub'] ?? 0);
            $grouped[$monthKey]['total_unsub'] += floatval($stat['unsub'] ?? 0);
            $grouped[$monthKey]['total_simchurn'] += floatval($stat['simchurn'] ?? 0);
            $grouped[$monthKey]['total_rev_simchurn'] += floatval($stat['rev_simchurn'] ?? 0);
            $grouped[$monthKey]['total_nb_facturation'] += floatval($stat['nb_facturation'] ?? 0);
            $grouped[$monthKey]['sum_taux_facturation'] += floatval($stat['taux_facturation'] ?? 0);
            
            // Sommer le revenu TTC qui est déjà dans les stats quotidiennes (en TND)
            $grouped[$monthKey]['total_revenu_ttc_tnd'] += floatval($stat['revenu_ttc_tnd'] ?? 0);
            
            $grouped[$monthKey]['days_count']++;
            
            // Pour active_sub, on prend le dernier jour du mois
            $grouped[$monthKey]['total_active_sub'] = floatval($stat['active_sub'] ?? 0);
        }
        
        // Calculer les métriques finales pour chaque mois selon le contrat
        foreach ($grouped as $monthKey => &$month) {
            // 1. Taux de facturation = MOYENNE des taux quotidiens
            if ($month['days_count'] > 0) {
                $month['total_taux_facturation'] = $month['sum_taux_facturation'] / $month['days_count'];
            }
            
            // 3. Calculer le CA BigDeal HT selon les règles du contrat
            $nbFacturation = $month['total_nb_facturation'];
            
            if ($nbFacturation < 100000) {
                // Moins de 100K : 1.2 DT HT par facturation
                $month['ca_bigdeal_ht'] = $nbFacturation * 1.2;
            } elseif ($nbFacturation >= 100000 && $nbFacturation < 250000) {
                // Entre 100K et 250K : 1.0 DT HT par facturation
                $month['ca_bigdeal_ht'] = $nbFacturation * 1.0;
            } else {
                // 250K et plus : plafonné à 250K DT HT
                $month['ca_bigdeal_ht'] = 250000;
            }
            
            // Formater le label avec le nombre de jours
            $month['display_label'] = $month['month_label'] . ' (' . $month['days_count'] . ')';
            
            // Nettoyer les champs temporaires
            unset($month['sum_taux_facturation']);
        }
        
        // Retourner en ordre chronologique décroissant
        krsort($grouped);
        
        $result = array_values($grouped);
        
        Log::info("groupTimweStatsByMonth - Fin", [
            'months_count' => count($result),
            'first_month' => $result[0]['month_key'] ?? null,
            'last_month' => $result[count($result)-1]['month_key'] ?? null
        ]);
        
        return $result;
    }
    
    /**
     * Groupe les statistiques quotidiennes Ooredoo par mois
     * Retourne un tableau avec les totaux mensuels et détails quotidiens
     */
    private function groupOoredooStatsByMonth(array $dailyStats): array
    {
        if (empty($dailyStats)) {
            return [];
        }
        
        $grouped = [];
        $totalStats = count($dailyStats);
        $includeDetails = $totalStats < 500; // Ne garder les détails que si < 500 lignes
        
        Log::info("groupOoredooStatsByMonth - Début", [
            'total_stats' => $totalStats,
            'include_details' => $includeDetails
        ]);
        
        foreach ($dailyStats as $stat) {
            $date = Carbon::parse($stat['stat_date']);
            $monthKey = $date->format('Y-m'); // Ex: "2025-12"
            $monthLabel = $date->locale('fr')->isoFormat('MMMM YYYY'); // Ex: "décembre 2025"
            
            if (!isset($grouped[$monthKey])) {
                $grouped[$monthKey] = [
                    'month_key' => $monthKey,
                    'month_label' => $monthLabel,
                    'year' => $date->year,
                    'month_num' => $date->month,
                    'daily_details' => [],
                    // Totaux mensuels
                    'total_new_sub' => 0,
                    'total_unsub' => 0,
                    'total_active_sub' => 0, // On prendra le dernier jour du mois
                    'total_nb_facturation' => 0,
                    'total_taux_facturation' => 0,
                    'sum_taux_facturation' => 0, // Pour calculer la moyenne
                    'total_revenu_tnd' => 0,
                    'days_count' => 0
                ];
            }
            
            // Ajouter les détails du jour seulement si pas trop de données
            if ($includeDetails) {
                $grouped[$monthKey]['daily_details'][] = $stat;
            }
            
            // Cumuler les totaux
            $grouped[$monthKey]['total_new_sub'] += floatval($stat['new_subscriptions'] ?? 0);
            $grouped[$monthKey]['total_unsub'] += floatval($stat['unsubscriptions'] ?? 0);
            $grouped[$monthKey]['total_nb_facturation'] += floatval($stat['total_billings'] ?? 0);
            $grouped[$monthKey]['total_revenu_tnd'] += floatval($stat['revenue_tnd'] ?? 0);
            $grouped[$monthKey]['sum_taux_facturation'] += floatval($stat['billing_rate'] ?? 0);
            $grouped[$monthKey]['total_active_sub'] = floatval($stat['active_subscriptions'] ?? 0); // Dernier du mois
            $grouped[$monthKey]['days_count']++;
        }
        
        // Calculer les métriques finales pour chaque mois
        foreach ($grouped as $monthKey => &$month) {
            // Taux de facturation = MOYENNE des taux quotidiens
            if ($month['days_count'] > 0) {
                $month['total_taux_facturation'] = $month['sum_taux_facturation'] / $month['days_count'];
            }
            
            // Formater le label avec le nombre de jours
            $month['display_label'] = $month['month_label'] . ' (' . $month['days_count'] . ')';
            
            // Nettoyer les champs temporaires
            unset($month['sum_taux_facturation']);
        }
        
        // Retourner en ordre chronologique décroissant
        krsort($grouped);
        
        $result = array_values($grouped);
        
        Log::info("groupOoredooStatsByMonth - Fin", [
            'months_count' => count($result),
            'first_month' => $result[0]['month_key'] ?? null,
            'last_month' => $result[count($result)-1]['month_key'] ?? null
        ]);
        
        return $result;
    }
}

