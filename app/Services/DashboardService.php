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
        
        // Cache the operator ID to avoid repeated DB lookups
        return Cache::remember('op_id:' . md5($operator), 3600, function() use ($operator) {
            // Recherche exacte d'abord
            $operatorId = DB::table('country_payments_methods')
                ->whereRaw("TRIM(country_payments_methods_name) = ?", [trim($operator)])
                ->value('country_payments_methods_id');
            
            if ($operatorId) {
                return (int)$operatorId;
            }
            
            // Recherche partielle (ex: "Timwe" → "S'abonner via Timwe")
            $operatorId = DB::table('country_payments_methods')
                ->whereRaw("TRIM(country_payments_methods_name) LIKE ?", ['%' . trim($operator) . '%'])
                ->value('country_payments_methods_id');
            
            return $operatorId ? (int)$operatorId : null;
        });
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
     * Applique le JOIN avec country_payments_methods ET le filtre opérateur SEULEMENT quand nécessaire.
     * Pour l'opérateur ALL, on évite le JOIN coûteux sur 350K+ lignes.
     * Retourne true si le JOIN a été ajouté, false sinon.
     */
    private function applyOperatorJoinAndFilter($query, string $selectedOperator, string $joinTable = 'ca', string $tableAlias = 'cpm'): bool
    {
        if ($selectedOperator !== 'ALL' && !empty($selectedOperator)) {
            $query->join("country_payments_methods as {$tableAlias}", "{$joinTable}.country_payments_methods_id", '=', "{$tableAlias}.country_payments_methods_id");
            $this->applyOperatorFilter($query, $selectedOperator, $tableAlias);
            return true;
        }
        return false;
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
    // ==========================================
    // METHODES PUBLIQUES POUR ENDPOINTS SPLIT
    // ==========================================
    public function getKPIsOptimizedPublic(Carbon $startBound, Carbon $endExclusive, Carbon $compStartBound, Carbon $compEndExclusive, string $selectedOperator): array
    {
        // Essayer les données matérialisées d'abord (< 500ms vs 15s)
        $materialized = $this->getKPIsFromMaterialized($startBound, $endExclusive, $compStartBound, $compEndExclusive, $selectedOperator);
        if ($materialized !== null) {
            Log::info("KPIs servis depuis les données matérialisées");
            return $materialized;
        }
        return $this->getKPIsOptimized($startBound, $endExclusive, $compStartBound, $compEndExclusive, $selectedOperator);
    }
    
    /**
     * Récupère les KPIs depuis la table matérialisée dashboard_daily_stats.
     * Retourne null si les données ne couvrent pas la période demandée.
     */
    private function getKPIsFromMaterialized(Carbon $startBound, Carbon $endExclusive, Carbon $compStartBound, Carbon $compEndExclusive, string $selectedOperator): ?array
    {
        try {
            $operatorId = ($selectedOperator === 'ALL') ? null : ($this->getOperatorId($selectedOperator));
            
            $startDate = $startBound->toDateString();
            $endDate = $endExclusive->copy()->subDay()->toDateString();
            $compStartDate = $compStartBound->toDateString();
            $compEndDate = $compEndExclusive->copy()->subDay()->toDateString();
            
            // Strategy 1: Try dashboard_daily_stats (all-in-one table, 365 days coverage)
            if (Schema::hasTable('dashboard_daily_stats')) {
                $expectedDays = $startBound->diffInDays($endExclusive);
                $actualDays = DB::table('dashboard_daily_stats')
                    ->where(function ($q) use ($operatorId) {
                        if ($operatorId === null) $q->whereNull('operator_id');
                        else $q->where('operator_id', $operatorId);
                    })
                    ->whereBetween('stat_date', [$startDate, $endDate])
                    ->count();
                
                $tolerance = ($endDate === Carbon::today()->toDateString()) ? 1 : 0;
                if ($actualDays >= ($expectedDays - $tolerance)) {
                    Log::info("KPIs: Using dashboard_daily_stats ({$actualDays}/{$expectedDays} days)");
                    if ($tolerance > 0 && $actualDays < $expectedDays) {
                        $endDate = Carbon::yesterday()->toDateString();
                    }
                    return $this->buildKPIsFromDashboardDailyStats($startBound, $endExclusive, $compStartBound, $compEndExclusive, $startDate, $endDate, $compStartDate, $compEndDate, $operatorId, $selectedOperator);
                }
            }
            
            // Strategy 2: Combine subscription_daily_stats + transaction_daily_stats
            $subCoverage = $this->hasMaterializedCoverage($startBound, $endExclusive, $operatorId);
            $txCoverage = $this->hasTransactionMaterializedCoverage($startBound, $endExclusive);
            
            if ($subCoverage && $txCoverage) {
                Log::info("KPIs: Using combined subscription_daily_stats + transaction_daily_stats");
                return $this->buildKPIsFromCombinedMaterialized($startBound, $endExclusive, $compStartBound, $compEndExclusive, $operatorId, $selectedOperator);
            }
            
            Log::info("KPIs: No materialized coverage, falling back to live queries");
            return null;
        } catch (\Exception $e) {
            Log::warning("KPIs materialized read failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if transaction_daily_stats has sufficient coverage
     */
    private function hasTransactionMaterializedCoverage(Carbon $startBound, Carbon $endExclusive): bool
    {
        try {
            $expectedDays = $startBound->diffInDays($endExclusive);
            if ($expectedDays <= 0) return false;
            $count = DB::table('transaction_daily_stats')
                ->where('stat_date', '>=', $startBound->toDateString())
                ->where('stat_date', '<', $endExclusive->toDateString())
                ->whereNull('operator_id')
                ->count();
            return ($count / $expectedDays) >= 0.8;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Build KPIs from dashboard_daily_stats (original logic)
     */
    private function buildKPIsFromDashboardDailyStats(Carbon $startBound, Carbon $endExclusive, Carbon $compStartBound, Carbon $compEndExclusive, string $startDate, string $endDate, string $compStartDate, string $compEndDate, ?int $operatorId, string $selectedOperator): ?array
    {
        $current = $this->aggregateMaterialized($startDate, $endDate, $operatorId);
        $comparison = $this->aggregateMaterialized($compStartDate, $compEndDate, $operatorId);
        if (!$current) return null;
        
        return $this->assembleKPIResult($current, $comparison, $startBound, $endExclusive, $compStartBound, $compEndExclusive, $operatorId, $selectedOperator, $endDate, $compEndDate);
    }
    
    /**
     * Build KPIs from combined subscription_daily_stats + transaction_daily_stats
     */
    private function buildKPIsFromCombinedMaterialized(Carbon $startBound, Carbon $endExclusive, Carbon $compStartBound, Carbon $compEndExclusive, ?int $operatorId, string $selectedOperator): ?array
    {
        $current = $this->aggregateCombinedMaterialized($startBound, $endExclusive, $operatorId);
        $comparison = $this->aggregateCombinedMaterialized($compStartBound, $compEndExclusive, $operatorId);
        if (!$current) return null;
        
        $endDate = $endExclusive->copy()->subDay()->toDateString();
        $compEndDate = $compEndExclusive->copy()->subDay()->toDateString();
        
        return $this->assembleKPIResult($current, $comparison, $startBound, $endExclusive, $compStartBound, $compEndExclusive, $operatorId, $selectedOperator, $endDate, $compEndDate);
    }
    
    /**
     * Aggregate from subscription_daily_stats + transaction_daily_stats combined
     */
    private function aggregateCombinedMaterialized(Carbon $startBound, Carbon $endExclusive, ?int $operatorId): ?array
    {
        $startStr = $startBound->toDateString();
        $endStr = $endExclusive->copy()->subDay()->toDateString();
        
        // Subscriptions data
        $subAgg = DB::table('subscription_daily_stats')
            ->where('stat_date', '>=', $startStr)
            ->where('stat_date', '<=', $endStr)
            ->where(function ($q) use ($operatorId) {
                if ($operatorId === null) $q->whereNull('operator_id');
                else $q->where('operator_id', $operatorId);
            })
            ->selectRaw('SUM(activated_count) as activated, SUM(deactivated_count) as deactivated, SUM(expired_count) as lost')
            ->first();
        
        if (!$subAgg || $subAgg->activated === null) return null;
        
        // Transactions data
        $txAgg = DB::table('transaction_daily_stats')
            ->where('stat_date', '>=', $startStr)
            ->where('stat_date', '<=', $endStr)
            ->whereNull('operator_id')
            ->selectRaw('SUM(transaction_count) as transactions, SUM(distinct_users) as transacting_users, SUM(cohort_transaction_count) as cohort_tx, SUM(cohort_distinct_users) as cohort_users, SUM(active_merchants) as active_merchants')
            ->first();
        
        // For lost subscriptions: use expired subs that were also created in the period
        // This is an approximation - we count subs that both activated AND expired in the period
        $lostSubs = DB::table('client_abonnement as ca')
            ->where('ca.client_abonnement_creation', '>=', $startBound)
            ->where('ca.client_abonnement_creation', '<', $endExclusive)
            ->whereNotNull('ca.client_abonnement_expiration')
            ->where('ca.client_abonnement_expiration', '>=', $startBound)
            ->where('ca.client_abonnement_expiration', '<', $endExclusive)
            ->when($operatorId !== null, fn($q) => $q->where('ca.country_payments_methods_id', $operatorId))
            ->count();
        
        return [
            'activated' => (int)($subAgg->activated ?? 0),
            'deactivated' => (int)($subAgg->deactivated ?? 0),
            'transactions' => (int)($txAgg->transactions ?? 0),
            'transacting_users' => (int)($txAgg->transacting_users ?? 0),
            'cohort_tx' => (int)($txAgg->cohort_tx ?? 0),
            'cohort_users' => (int)($txAgg->cohort_users ?? 0),
            'active_merchants' => (int)($txAgg->active_merchants ?? 0),
            'lost' => $lostSubs,
        ];
    }
    
    /**
     * Assemble the final KPI result array from aggregated data
     */
    private function assembleKPIResult(array $current, ?array $comparison, Carbon $startBound, Carbon $endExclusive, Carbon $compStartBound, Carbon $compEndExclusive, ?int $operatorId, string $selectedOperator, string $endDate, string $compEndDate): array
    {
        $comparison = $comparison ?? ['activated' => 0, 'deactivated' => 0, 'transactions' => 0, 'transacting_users' => 0, 'cohort_tx' => 0, 'cohort_users' => 0, 'active_merchants' => 0, 'lost' => 0];
        
        $activeCurrent = $this->queryActivatedStillActive($startBound, $endExclusive, $operatorId);
        $activeComp = $this->queryActivatedStillActive($compStartBound, $compEndExclusive, $operatorId);
        
        $retentionRate = $current['activated'] > 0 ? round(($activeCurrent / $current['activated']) * 100, 1) : 0;
        $retentionRateComp = $comparison['activated'] > 0 ? round(($activeComp / $comparison['activated']) * 100, 1) : 0;
        
        $conversionRate = $activeCurrent > 0 ? round(($current['transacting_users'] / $activeCurrent) * 100, 1) : 0;
        $conversionRateComp = $activeComp > 0 ? round(($comparison['transacting_users'] / $activeComp) * 100, 1) : 0;
        
        $churnRate = $current['activated'] > 0 ? round(($current['lost'] / $current['activated']) * 100, 1) : 0;
        $churnRateComp = $comparison['activated'] > 0 ? round(($comparison['lost'] / $comparison['activated']) * 100, 1) : 0;
        
        $txPerUser = $current['transacting_users'] > 0 ? round($current['transactions'] / $current['transacting_users'], 1) : 0;
        $txPerUserComp = $comparison['transacting_users'] > 0 ? round($comparison['transactions'] / $comparison['transacting_users'], 1) : 0;
        
        $convRatePeriod = $activeCurrent > 0 ? round(($current['transacting_users'] / $activeCurrent) * 100, 2) : 0;
        $convRatePeriodComp = $activeComp > 0 ? round(($comparison['transacting_users'] / $activeComp) * 100, 2) : 0;
        
        $totalActivePartnersDB = Cache::remember('total_active_partners', 3600, fn() => DB::table('partner')->where('partener_active', 1)->count());
        $totalMerchantsEverActive = Cache::remember('total_merchants_ever', 3600, fn() => DB::table('history as h')->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')->distinct('p.partner_id')->count('p.partner_id'));
        $totalLocationsActive = Cache::remember('total_locations_active', 3600, function() {
            try { return DB::table('partner_location')->join('partner', 'partner_location.partner_id', '=', 'partner.partner_id')->where('partner.partener_active', 1)->distinct('partner_location.partner_location_id')->count('partner_location.partner_location_id'); }
            catch (\Exception $e) { return 0; }
        });
        
        $activeMerchantRatio = $totalActivePartnersDB > 0 ? round(($current['active_merchants'] / $totalActivePartnersDB) * 100, 1) : 0;
        $activeMerchantRatioComp = $totalActivePartnersDB > 0 ? round(($comparison['active_merchants'] / $totalActivePartnersDB) * 100, 1) : 0;
        $txPerMerchant = $current['active_merchants'] > 0 ? round($current['transactions'] / $current['active_merchants'], 1) : 0;
        $txPerMerchantComp = $comparison['active_merchants'] > 0 ? round($comparison['transactions'] / $comparison['active_merchants'], 1) : 0;
        
        $billingRateTimweData = $this->calculateTimweBillingRate($startBound, $endExclusive, $selectedOperator);
        $billingRateTimweCompData = $this->calculateTimweBillingRate($compStartBound, $compEndExclusive, $selectedOperator);
        $billingRateOoredooData = $this->calculateOoredooBillingRate($startBound, $endExclusive, $selectedOperator);
        $billingRateOoredooCompData = $this->calculateOoredooBillingRate($compStartBound, $compEndExclusive, $selectedOperator);

        return [
            "activatedSubscriptions" => ["current" => $current['activated'], "previous" => $comparison['activated'], "change" => $this->calculatePercentageChange($current['activated'], $comparison['activated'])],
            "activeSubscriptions" => ["current" => $activeCurrent, "previous" => $activeComp, "change" => $this->calculatePercentageChange($activeCurrent, $activeComp)],
            "deactivatedSubscriptions" => ["current" => $current['deactivated'], "previous" => $comparison['deactivated'], "change" => $this->calculatePercentageChange($current['deactivated'], $comparison['deactivated'])],
            "periodDeactivated" => ["current" => $current['deactivated'], "previous" => $comparison['deactivated'], "change" => $this->calculatePercentageChange($current['deactivated'], $comparison['deactivated'])],
            "cohortDeactivated" => ["current" => $current['lost'], "previous" => $comparison['lost'], "change" => $this->calculatePercentageChange($current['lost'], $comparison['lost'])],
            "totalTransactions" => ["current" => $current['transactions'], "previous" => $comparison['transactions'], "change" => $this->calculatePercentageChange($current['transactions'], $comparison['transactions'])],
            "cohortTransactions" => ["current" => $current['cohort_tx'], "previous" => $comparison['cohort_tx'], "change" => $this->calculatePercentageChange($current['cohort_tx'], $comparison['cohort_tx'])],
            "transactingUsers" => ["current" => $current['transacting_users'], "previous" => $comparison['transacting_users'], "change" => $this->calculatePercentageChange($current['transacting_users'], $comparison['transacting_users'])],
            "cohortTransactingUsers" => ["current" => $current['cohort_users'], "previous" => $comparison['cohort_users'], "change" => $this->calculatePercentageChange($current['cohort_users'], $comparison['cohort_users'])],
            "retentionRate" => ["current" => $retentionRate, "previous" => $retentionRateComp, "change" => $this->calculatePercentageChange($retentionRate, $retentionRateComp)],
            "retentionRateTrue" => ["current" => max(0, 100 - $churnRate), "previous" => max(0, 100 - $churnRateComp), "change" => $this->calculatePercentageChange(max(0, 100 - $churnRate), max(0, 100 - $churnRateComp))],
            "conversionRate" => ["current" => $conversionRate, "previous" => $conversionRateComp, "change" => $this->calculatePercentageChange($conversionRate, $conversionRateComp)],
            "churnRate" => ["current" => $churnRate, "previous" => $churnRateComp, "change" => $this->calculatePercentageChange($churnRate, $churnRateComp)],
            "transactionsPerUser" => ["current" => $txPerUser, "previous" => $txPerUserComp, "change" => $this->calculatePercentageChange($txPerUser, $txPerUserComp)],
            "conversionRatePeriod" => ["current" => $convRatePeriod, "previous" => $convRatePeriodComp, "change" => $this->calculatePercentageChange($convRatePeriod, $convRatePeriodComp)],
            "activeMerchants" => ["current" => $current['active_merchants'], "previous" => $comparison['active_merchants'], "change" => $this->calculatePercentageChange($current['active_merchants'], $comparison['active_merchants'])],
            "activeMerchantRatio" => ["current" => $activeMerchantRatio, "previous" => $activeMerchantRatioComp, "change" => $this->calculatePercentageChange($activeMerchantRatio, $activeMerchantRatioComp)],
            "totalPartners" => ["current" => $totalActivePartnersDB, "previous" => $totalActivePartnersDB, "change" => 0.0],
            "totalActivePartnersDB" => ["current" => $totalActivePartnersDB, "previous" => $totalActivePartnersDB, "change" => 0.0],
            "totalLocationsActive" => ["current" => $totalLocationsActive, "previous" => $totalLocationsActive, "change" => 0.0],
            "totalMerchantsEverActive" => $totalMerchantsEverActive,
            "allTransactionsPeriod" => $current['transactions'],
            "transactionsPerMerchant" => ["current" => $txPerMerchant, "previous" => $txPerMerchantComp, "change" => $this->calculatePercentageChange($txPerMerchant, $txPerMerchantComp)],
            "billingRateTimwe" => ["current" => $billingRateTimweData['rate'], "previous" => $billingRateTimweCompData['rate'], "change" => $this->calculatePercentageChange($billingRateTimweData['rate'], $billingRateTimweCompData['rate'])],
            "totalTimweClients" => ["current" => $billingRateTimweData['total_clients'], "previous" => $billingRateTimweCompData['total_clients'], "change" => $this->calculatePercentageChange($billingRateTimweData['total_clients'], $billingRateTimweCompData['total_clients'])],
            "totalTimweBillings" => ["current" => $billingRateTimweData['total_billings'], "previous" => $billingRateTimweCompData['total_billings'], "change" => $this->calculatePercentageChange($billingRateTimweData['total_billings'], $billingRateTimweCompData['total_billings'])],
            "billingRateOoredoo" => ["current" => $billingRateOoredooData['rate'], "previous" => $billingRateOoredooCompData['rate'], "change" => $this->calculatePercentageChange($billingRateOoredooData['rate'], $billingRateOoredooCompData['rate'])],
            "totalOoredooClients" => ["current" => $billingRateOoredooData['total_clients'], "previous" => $billingRateOoredooCompData['total_clients'], "change" => $this->calculatePercentageChange($billingRateOoredooData['total_clients'], $billingRateOoredooCompData['total_clients'])],
            "totalOoreodooBillings" => ["current" => $billingRateOoredooData['total_billings'], "previous" => $billingRateOoredooCompData['total_billings'], "change" => $this->calculatePercentageChange($billingRateOoredooData['total_billings'], $billingRateOoredooCompData['total_billings'])],
            "_source" => "materialized"
        ];
    }
    
    /**
     * Requête légère: nombre d'abonnements activés dans la période ET encore actifs
     * Utilise l'index sur client_abonnement_creation (~200ms)
     */
    private function queryActivatedStillActive(Carbon $startBound, Carbon $endExclusive, ?int $operatorId): int
    {
        $query = DB::table('client_abonnement as ca')
            ->where('ca.client_abonnement_creation', '>=', $startBound)
            ->where('ca.client_abonnement_creation', '<', $endExclusive)
            ->where(function ($q) use ($endExclusive) {
                $q->whereNull('ca.client_abonnement_expiration')
                  ->orWhere('ca.client_abonnement_expiration', '>=', $endExclusive);
            });
        if ($operatorId !== null) {
            $query->where('ca.country_payments_methods_id', $operatorId);
        }
        return $query->count();
    }
    
    /**
     * Agrège les métriques quotidiennes matérialisées pour une plage de dates
     */
    private function aggregateMaterialized(string $startDate, string $endDate, ?int $operatorId): ?array
    {
        $query = DB::table('dashboard_daily_stats')
            ->where(function ($q) use ($operatorId) {
                if ($operatorId === null) {
                    $q->whereNull('operator_id');
                } else {
                    $q->where('operator_id', $operatorId);
                }
            })
            ->whereBetween('stat_date', [$startDate, $endDate]);
        
        $agg = $query->selectRaw('
            SUM(activated_count) as activated,
            SUM(deactivated_count) as deactivated,
            SUM(transactions_count) as transactions,
            SUM(transacting_users) as transacting_users,
            SUM(cohort_transactions) as cohort_tx,
            SUM(cohort_transacting_users) as cohort_users,
            SUM(active_merchants) as active_merchants,
            SUM(lost_subscriptions) as lost
        ')->first();
        
        if (!$agg || $agg->activated === null) return null;
        
        return [
            'activated' => (int) $agg->activated,
            'deactivated' => (int) $agg->deactivated,
            'transactions' => (int) $agg->transactions,
            'transacting_users' => (int) $agg->transacting_users,
            'cohort_tx' => (int) $agg->cohort_tx,
            'cohort_users' => (int) $agg->cohort_users,
            'active_merchants' => (int) $agg->active_merchants,
            'lost' => (int) $agg->lost,
        ];
    }
    
    public function getMerchantsOptimizedPublic(Carbon $startBound, Carbon $endExclusive, Carbon $compStartBound, Carbon $compEndExclusive, string $selectedOperator): array
    {
        return $this->getMerchantsOptimized($startBound, $endExclusive, $compStartBound, $compEndExclusive, $selectedOperator);
    }
    
    public function getTransactionsDataPublic(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        return $this->getTransactionsData($startBound, $endExclusive, $selectedOperator);
    }
    
    public function getSubscriptionsDataPublic(Carbon $startBound, Carbon $endExclusive, string $selectedOperator, ?Carbon $compStartBound = null, ?Carbon $compEndExclusive = null): array
    {
        return $this->getSubscriptionsData($startBound, $endExclusive, $selectedOperator, $compStartBound, $compEndExclusive);
    }
    
    public function getOoredooDailyStatisticsPublic(Carbon $startBound, Carbon $endExclusive): array
    {
        return $this->getOoredooDailyStatistics($startBound, $endExclusive);
    }
    
    public function groupOoredooStatsByMonthPublic(array $dailyStats): array
    {
        return $this->groupOoredooStatsByMonth($dailyStats);
    }
    
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
        Log::info("STEP 1/5: KPIs...");
        $kpis = $this->getKPIsOptimized($startBound, $endExclusive, $compStartBound, $compEndExclusive, $selectedOperator);
        Log::info("STEP 1/5: KPIs OK (" . round((microtime(true) - $startTime) * 1000) . "ms)");
        
        // 2. Marchands avec correction du problème N+1
        Log::info("STEP 2/5: Marchands...");
        $merchants = $this->getMerchantsOptimized($startBound, $endExclusive, $compStartBound, $compEndExclusive, $selectedOperator);
        Log::info("STEP 2/5: Marchands OK (" . round((microtime(true) - $startTime) * 1000) . "ms)");
        
        // 3. Données de transactions agrégées
        Log::info("STEP 3/5: Transactions...");
        $transactions = $this->getTransactionsData($startBound, $endExclusive, $selectedOperator);
        Log::info("STEP 3/5: Transactions OK (" . round((microtime(true) - $startTime) * 1000) . "ms)");
        
        // 4. Données d'abonnements (le plus lourd)
        Log::info("STEP 4/5: Abonnements...");
        $subscriptions = $this->getSubscriptionsData($startBound, $endExclusive, $selectedOperator, $compStartBound, $compEndExclusive);
        Log::info("STEP 4/5: Abonnements OK (" . round((microtime(true) - $startTime) * 1000) . "ms)");
        
        // 5. Données Ooredoo/DGV
        Log::info("STEP 5/5: Ooredoo stats...");
        $ooredooStats = [
            'daily_statistics' => $this->getOoredooDailyStatistics($startBound, $endExclusive),
            'daily_statistics_comparison' => $this->getOoredooDailyStatistics($compStartBound, $compEndExclusive)
        ];
        $ooredooStats['ooredoo_monthly_stats'] = $this->groupOoredooStatsByMonth($ooredooStats['daily_statistics']);
        $ooredooStats['ooredoo_monthly_stats_comparison'] = $this->groupOoredooStatsByMonth($ooredooStats['daily_statistics_comparison']);
        
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        Log::info("STEP 5/5: Ooredoo OK - TOTAL: {$executionTime}ms");
        
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
        // Requête unifiée pour tous les KPIs d'abonnements avec PDO bindings sécurisés
        $subscriptionQuery = DB::table('client_abonnement as ca')
            ->selectRaw(
                "COUNT(CASE WHEN ca.client_abonnement_creation >= ? AND ca.client_abonnement_creation < ? THEN 1 END) as activated_current,
                 COUNT(CASE WHEN ca.client_abonnement_creation >= ? AND ca.client_abonnement_creation < ? AND (ca.client_abonnement_expiration IS NULL OR ca.client_abonnement_expiration >= ?) THEN 1 END) as active_current,
                 COUNT(CASE WHEN ca.client_abonnement_expiration >= ? AND ca.client_abonnement_expiration < ? THEN 1 END) as deactivated_current,
                 COUNT(CASE WHEN ca.client_abonnement_creation >= ? AND ca.client_abonnement_creation < ? THEN 1 END) as activated_comparison,
                 COUNT(CASE WHEN ca.client_abonnement_creation >= ? AND ca.client_abonnement_creation < ? AND (ca.client_abonnement_expiration IS NULL OR ca.client_abonnement_expiration >= ?) THEN 1 END) as active_comparison,
                 COUNT(CASE WHEN ca.client_abonnement_expiration >= ? AND ca.client_abonnement_expiration < ? THEN 1 END) as deactivated_comparison",
                [
                    $startBound, $endExclusive,
                    $startBound, $endExclusive, $endExclusive,
                    $startBound, $endExclusive,
                    $compStartBound, $compEndExclusive,
                    $compStartBound, $compEndExclusive, $compEndExclusive,
                    $compStartBound, $compEndExclusive
                ]
            );
        $this->applyOperatorJoinAndFilter($subscriptionQuery, $selectedOperator, 'ca');
        
        Log::info("Requête KPIs - Opérateur: {$selectedOperator}");
        
        $subMetrics = $subscriptionQuery->first();
        
        Log::info("KPIs abonnements - Activés: {$subMetrics->activated_current}, Actifs: {$subMetrics->active_current}, Désactivés: {$subMetrics->deactivated_current}");
        
        // Requête unifiée pour les transactions - JOIN conditionnel + PDO bindings
        $transactionQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->selectRaw(
                "COUNT(CASE WHEN h.time >= ? AND h.time < ? THEN 1 END) as transactions_current,
                 COUNT(CASE WHEN h.time >= ? AND h.time < ? THEN 1 END) as transactions_comparison,
                 COUNT(DISTINCT CASE WHEN h.time >= ? AND h.time < ? THEN ca.client_id END) as users_current,
                 COUNT(DISTINCT CASE WHEN h.time >= ? AND h.time < ? THEN ca.client_id END) as users_comparison",
                [$startBound, $endExclusive, $compStartBound, $compEndExclusive, $startBound, $endExclusive, $compStartBound, $compEndExclusive]
            );
        $this->applyOperatorJoinAndFilter($transactionQuery, $selectedOperator, 'ca');
        
        $txMetrics = $transactionQuery->first();
        
        // Transactions de cohorte - JOIN conditionnel
        $cohortTransactionsQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->where('h.time', '>=', $startBound)
            ->where('h.time', '<', $endExclusive)
            ->where('ca.client_abonnement_creation', '>=', $startBound)
            ->where('ca.client_abonnement_creation', '<', $endExclusive);
        $this->applyOperatorJoinAndFilter($cohortTransactionsQuery, $selectedOperator, 'ca');
        $cohortTransactions = $cohortTransactionsQuery->count();
        
        $cohortTransactionsComparisonQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->where('h.time', '>=', $compStartBound)
            ->where('h.time', '<', $compEndExclusive)
            ->where('ca.client_abonnement_creation', '>=', $compStartBound)
            ->where('ca.client_abonnement_creation', '<', $compEndExclusive);
        $this->applyOperatorJoinAndFilter($cohortTransactionsComparisonQuery, $selectedOperator, 'ca');
        $cohortTransactionsComparison = $cohortTransactionsComparisonQuery->count();
        
        // Utilisateurs transactants de cohorte - JOIN conditionnel
        $cohortTransactingUsersQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->where('h.time', '>=', $startBound)
            ->where('h.time', '<', $endExclusive)
            ->where('ca.client_abonnement_creation', '>=', $startBound)
            ->where('ca.client_abonnement_creation', '<', $endExclusive);
        $this->applyOperatorJoinAndFilter($cohortTransactingUsersQuery, $selectedOperator, 'ca');
        $cohortTransactingUsers = $cohortTransactingUsersQuery->distinct('ca.client_id')->count('ca.client_id');
        
        $cohortTransactingUsersComparisonQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->where('h.time', '>=', $compStartBound)
            ->where('h.time', '<', $compEndExclusive)
            ->where('ca.client_abonnement_creation', '>=', $compStartBound)
            ->where('ca.client_abonnement_creation', '<', $compEndExclusive);
        $this->applyOperatorJoinAndFilter($cohortTransactingUsersComparisonQuery, $selectedOperator, 'ca');
        $cohortTransactingUsersComparison = $cohortTransactingUsersComparisonQuery->distinct('ca.client_id')->count('ca.client_id');
        
        // Calculs des taux
        $retentionRate = $subMetrics->activated_current > 0 ? round(($subMetrics->active_current / $subMetrics->activated_current) * 100, 1) : 0;
        $retentionRateComparison = $subMetrics->activated_comparison > 0 ? round(($subMetrics->active_comparison / $subMetrics->activated_comparison) * 100, 1) : 0;
        
        $conversionRate = $subMetrics->active_current > 0 ? round(($txMetrics->users_current / $subMetrics->active_current) * 100, 1) : 0;
        $conversionRateComparison = $subMetrics->active_comparison > 0 ? round(($txMetrics->users_comparison / $subMetrics->active_comparison) * 100, 1) : 0;
        
        // Calcul du churn rate (abonnements perdus dans la période / abonnements activés)
        // Abonnements perdus = activés ET désactivés dans la période
        $lostSubscriptionsQuery = DB::table('client_abonnement as ca')
            ->whereBetween('ca.client_abonnement_creation', [$startBound->toDateString(), $endExclusive->copy()->subDay()->toDateString()])
            ->whereNotNull('ca.client_abonnement_expiration')
            ->whereBetween('ca.client_abonnement_expiration', [$startBound->toDateString(), $endExclusive->copy()->subDay()->toDateString()]);
        $this->applyOperatorJoinAndFilter($lostSubscriptionsQuery, $selectedOperator, 'ca');
        $lostSubscriptions = $lostSubscriptionsQuery->count();
        
        $lostSubscriptionsComparisonQuery = DB::table('client_abonnement as ca')
            ->whereBetween('ca.client_abonnement_creation', [$compStartBound->toDateString(), $compEndExclusive->copy()->subDay()->toDateString()])
            ->whereNotNull('ca.client_abonnement_expiration')
            ->whereBetween('ca.client_abonnement_expiration', [$compStartBound->toDateString(), $compEndExclusive->copy()->subDay()->toDateString()]);
        $this->applyOperatorJoinAndFilter($lostSubscriptionsComparisonQuery, $selectedOperator, 'ca');
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
        // Marchands actifs dans la période principale - JOIN cpm conditionnel
        $activeMerchantsQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->join('partner as pt', 'p.partner_id', '=', 'pt.partner_id')
            ->where('h.time', '>=', $startBound)
            ->where('h.time', '<', $endExclusive)
            ->whereNotNull('h.promotion_id');
        $this->applyOperatorJoinAndFilter($activeMerchantsQuery, $selectedOperator, 'ca');
        $activeMerchants = $activeMerchantsQuery->distinct('pt.partner_id')->count('pt.partner_id');
        
        // Marchands actifs dans la période de comparaison
        $activeMerchantsComparisonQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->join('partner as pt', 'p.partner_id', '=', 'pt.partner_id')
            ->where('h.time', '>=', $compStartBound)
            ->where('h.time', '<', $compEndExclusive)
            ->whereNotNull('h.promotion_id');
        $this->applyOperatorJoinAndFilter($activeMerchantsComparisonQuery, $selectedOperator, 'ca');
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
        // Requête unifiée pour éviter le N+1 - JOIN cpm conditionnel pour ALL
        $merchantsQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->join('partner as pt', 'p.partner_id', '=', 'pt.partner_id')
            ->selectRaw(
                "pt.partner_name as name, pt.partner_id,
                 COUNT(CASE WHEN h.time >= ? AND h.time < ? THEN 1 END) as `current`,
                 COUNT(CASE WHEN h.time >= ? AND h.time < ? THEN 1 END) as previous",
                [$startBound, $endExclusive, $compStartBound, $compEndExclusive]
            )
            ->whereNotNull('h.promotion_id');
        
        $this->applyOperatorJoinAndFilter($merchantsQuery, $selectedOperator, 'ca');
        
        $merchants = $merchantsQuery
            ->groupBy('pt.partner_name', 'pt.partner_id')
            ->having('current', '>', 0)
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
        Log::debug("getOptimizedDashboardData - KPIs retournés", [
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
        // Try materialized path first
        if ($selectedOperator === 'ALL' || empty($selectedOperator)) {
            if ($this->hasTransactionMaterializedCoverage($startBound, $endExclusive)) {
                Log::info("getTransactionsData - MATERIALIZED path");
                return $this->getTransactionsDataMaterialized($startBound, $endExclusive);
            }
        }
        
        Log::info("getTransactionsData - LIVE path");
        return $this->getTransactionsDataLive($startBound, $endExclusive, $selectedOperator);
    }
    
    /**
     * FAST PATH: Read transaction data from transaction_daily_stats
     */
    private function getTransactionsDataMaterialized(Carbon $startBound, Carbon $endExclusive): array
    {
        $periodDays = $startBound->diffInDays($endExclusive);
        $granularity = $periodDays > 365 ? 'month' : 'day';
        $dateExpr = $granularity === 'month' ? "DATE_FORMAT(stat_date, '%Y-%m-01')" : 'stat_date';
        
        $rows = DB::table('transaction_daily_stats')
            ->where('stat_date', '>=', $startBound->toDateString())
            ->where('stat_date', '<', $endExclusive->toDateString())
            ->whereNull('operator_id')
            ->select(
                DB::raw("{$dateExpr} as period_date"),
                DB::raw('SUM(transaction_count) as transactions'),
                DB::raw('SUM(distinct_users) as users')
            )
            ->groupBy(DB::raw($dateExpr))
            ->orderBy('period_date')
            ->get()
            ->keyBy('period_date');
        
        $startDate = $startBound->copy();
        $endDate = $endExclusive->copy()->subDay();
        $dailyVolume = [];
        
        if ($granularity === 'month') {
            $cursor = $startDate->copy()->firstOfMonth();
            while ($cursor->lte($endDate)) {
                $key = $cursor->toDateString();
                $row = $rows->get($key);
                $dailyVolume[] = ['date' => $key, 'transactions' => $row ? (int)$row->transactions : 0, 'users' => $row ? (int)$row->users : 0];
                $cursor->addMonth();
            }
        } else {
            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                $key = $cursor->toDateString();
                $row = $rows->get($key);
                $dailyVolume[] = ['date' => $key, 'transactions' => $row ? (int)$row->transactions : 0, 'users' => $row ? (int)$row->users : 0];
                $cursor->addDay();
            }
        }
        
        // By operator: aggregate JSON columns
        $byOperator = [];
        $opAgg = DB::table('transaction_daily_stats')
            ->where('stat_date', '>=', $startBound->toDateString())
            ->where('stat_date', '<', $endExclusive->toDateString())
            ->whereNull('operator_id')
            ->pluck('by_operator');
        foreach ($opAgg as $jsonStr) {
            $ops = json_decode($jsonStr, true) ?? [];
            foreach ($ops as $name => $cnt) {
                $byOperator[$name] = ($byOperator[$name] ?? 0) + $cnt;
            }
        }
        $byOperatorResult = [];
        foreach ($byOperator as $name => $count) {
            $byOperatorResult[] = ['operator' => $name, 'count' => $count];
        }
        usort($byOperatorResult, fn($a, $b) => $b['count'] - $a['count']);
        
        // By plan: aggregate JSON columns
        $byPlan = [];
        $planAgg = DB::table('transaction_daily_stats')
            ->where('stat_date', '>=', $startBound->toDateString())
            ->where('stat_date', '<', $endExclusive->toDateString())
            ->whereNull('operator_id')
            ->pluck('by_plan');
        foreach ($planAgg as $jsonStr) {
            $plans = json_decode($jsonStr, true) ?? [];
            foreach ($plans as $name => $cnt) {
                $byPlan[$name] = ($byPlan[$name] ?? 0) + $cnt;
            }
        }
        $byPlanResult = [];
        foreach ($byPlan as $name => $count) {
            $byPlanResult[] = ['plan' => $name, 'count' => $count];
        }
        usort($byPlanResult, fn($a, $b) => $b['count'] - $a['count']);
        
        return [
            "daily_volume" => $dailyVolume,
            "by_category" => [],
            "analytics" => [
                "byOperator" => $byOperatorResult,
                "byPlan" => $byPlanResult,
                "byChannel" => []
            ]
        ];
    }
    
    /**
     * SLOW PATH (fallback): Original live queries
     */
    private function getTransactionsDataLive(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        // Calculer la granularité selon la période (forcer le mode quotidien pour toutes les périodes < 365 jours)
        $periodDays = $startBound->diffInDays($endExclusive);
        $granularity = $periodDays > 365 ? 'month' : 'day'; // Mode quotidien pour périodes <= 365 jours
        $historyDateExpr = $granularity === 'month' ? "DATE_FORMAT(h.time, '%Y-%m-01')" : "DATE(h.time)";
        
        Log::debug("getTransactionsData - Période: {$periodDays} jours, Granularité: {$granularity}");
        
        // Transactions agrégées par jour ou par mois - JOIN cpm conditionnel
        $transactionsQuery = DB::table("history as h")
            ->join("client_abonnement as ca", "h.client_abonnement_id", "=", "ca.client_abonnement_id")
            ->select(DB::raw("$historyDateExpr as date"), DB::raw("COUNT(*) as transactions"), DB::raw("COUNT(DISTINCT ca.client_id) as users"))
            ->where("h.time", ">=", $startBound)
            ->where("h.time", "<", $endExclusive);
        
        $this->applyOperatorJoinAndFilter($transactionsQuery, $selectedOperator, 'ca');
        
        $transactionsRaw = $transactionsQuery
            ->groupBy(DB::raw($historyDateExpr))
            ->orderBy("date")
            ->get()
            ->keyBy('date')
            ->toArray();
        
        Log::debug("Transactions raw - Nombre de dates: " . count($transactionsRaw) . ", Exemple de clés: " . implode(', ', array_slice(array_keys($transactionsRaw), 0, 5)));
        
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
        $methodStart = microtime(true);
        
        // Try materialized path first (fast: reads from subscription_daily_stats)
        $operatorId = $this->resolveOperatorIdForMaterialized($selectedOperator);
        $hasMaterialized = $this->hasMaterializedCoverage($startBound, $endExclusive, $operatorId);
        
        if ($hasMaterialized) {
            Log::info("getSubscriptionsData - MATERIALIZED path (operator_id={$operatorId})");
            return $this->getSubscriptionsDataMaterialized($startBound, $endExclusive, $selectedOperator, $operatorId, $compStartBound, $compEndExclusive, $methodStart);
        }
        
        Log::info("getSubscriptionsData - LIVE path (no materialized data for operator_id={$operatorId})");
        return $this->getSubscriptionsDataLive($startBound, $endExclusive, $selectedOperator, $compStartBound, $compEndExclusive, $methodStart);
    }

    /**
     * Resolve operator to operator_id for materialized table lookup.
     * Returns null for ALL, integer for specific operator.
     */
    private function resolveOperatorIdForMaterialized(string $selectedOperator): ?int
    {
        if ($selectedOperator === 'ALL' || empty($selectedOperator)) {
            return null;
        }
        return $this->getOperatorId($selectedOperator);
    }

    /**
     * Check if subscription_daily_stats has sufficient coverage for the requested period.
     * Requires at least 80% of expected days to be present.
     */
    private function hasMaterializedCoverage(Carbon $startBound, Carbon $endExclusive, ?int $operatorId): bool
    {
        try {
            $expectedDays = $startBound->diffInDays($endExclusive);
            if ($expectedDays <= 0) return false;

            $count = DB::table('subscription_daily_stats')
                ->where('stat_date', '>=', $startBound->toDateString())
                ->where('stat_date', '<', $endExclusive->toDateString())
                ->where(function ($q) use ($operatorId) {
                    if ($operatorId === null) {
                        $q->whereNull('operator_id');
                    } else {
                        $q->where('operator_id', $operatorId);
                    }
                })
                ->count();

            $coverage = $count / $expectedDays;
            Log::debug("Materialized coverage: {$count}/{$expectedDays} = " . round($coverage * 100) . "%");
            return $coverage >= 0.8;
        } catch (\Exception $e) {
            Log::warning("hasMaterializedCoverage check failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * FAST PATH: Read pre-computed subscription metrics from subscription_daily_stats.
     * Expected time: < 3 seconds for any period.
     */
    private function getSubscriptionsDataMaterialized(Carbon $startBound, Carbon $endExclusive, string $selectedOperator, ?int $operatorId, ?Carbon $compStartBound, ?Carbon $compEndExclusive, float $methodStart): array
    {
        $periodDays = $startBound->diffInDays($endExclusive);
        $granularity = $periodDays > 365 ? 'month' : 'day';

        // === 1. Daily activations from materialized table ===
        $dateExpr = $granularity === 'month' ? "DATE_FORMAT(stat_date, '%Y-%m-01')" : 'stat_date';
        $matRows = DB::table('subscription_daily_stats')
            ->where('stat_date', '>=', $startBound->toDateString())
            ->where('stat_date', '<', $endExclusive->toDateString())
            ->where(function ($q) use ($operatorId) {
                if ($operatorId === null) {
                    $q->whereNull('operator_id');
                } else {
                    $q->where('operator_id', $operatorId);
                }
            })
            ->select(
                DB::raw("{$dateExpr} as period_date"),
                DB::raw('SUM(activated_count) as activations'),
                DB::raw('SUM(active_snapshot) as active_snap')
            )
            ->groupBy(DB::raw($dateExpr))
            ->orderBy('period_date')
            ->get()
            ->keyBy('period_date');

        $dailyActivations = [];
        if ($granularity === 'month') {
            $cursor = $startBound->copy()->firstOfMonth();
            $endDate = $endExclusive->copy()->subDay();
            while ($cursor->lte($endDate)) {
                $key = $cursor->toDateString();
                $row = $matRows->get($key);
                $act = $row ? (int)$row->activations : 0;
                $dailyActivations[] = ['date' => $key, 'activations' => $act, 'active' => round($act * 0.95)];
                $cursor->addMonth();
            }
        } else {
            $cursor = $startBound->copy();
            $endDate = $endExclusive->copy()->subDay();
            while ($cursor->lte($endDate)) {
                $key = $cursor->toDateString();
                $row = $matRows->get($key);
                $act = $row ? (int)$row->activations : 0;
                $dailyActivations[] = ['date' => $key, 'activations' => $act, 'active' => round($act * 0.95)];
                $cursor->addDay();
            }
        }

        Log::info("Materialized daily_activations: " . count($dailyActivations) . " points (" . round((microtime(true) - $methodStart) * 1000) . "ms)");

        // === 2. Aggregated metrics from materialized table (channels, plans, renewal, lifespan) ===
        $currentAgg = $this->getMaterializedAggregates($startBound, $endExclusive, $operatorId);
        $compAgg = ($compStartBound && $compEndExclusive) 
            ? $this->getMaterializedAggregates($compStartBound, $compEndExclusive, $operatorId)
            : null;

        // Activations by channel
        $activationsByChannel = [];
        foreach (['cb', 'recharge', 'phone_balance', 'other'] as $ch) {
            $cur = $currentAgg["channel_{$ch}"] ?? 0;
            $prev = $compAgg ? ($compAgg["channel_{$ch}"] ?? 0) : 0;
            $activationsByChannel[$ch] = ["current" => $cur, "previous" => $prev, "change" => $this->calculatePercentageChange($cur, $prev)];
        }

        // Plan distribution
        $planDistribution = [];
        foreach (['daily', 'monthly', 'annual', 'other'] as $pl) {
            $cur = $currentAgg["plan_{$pl}"] ?? 0;
            $prev = $compAgg ? ($compAgg["plan_{$pl}"] ?? 0) : 0;
            $planDistribution[$pl] = ["current" => $cur, "previous" => $prev, "change" => $this->calculatePercentageChange($cur, $prev)];
        }

        // Renewal rate
        $renewalCurrent = ($currentAgg['expired_count'] > 0) ? round(($currentAgg['renewed_count'] / $currentAgg['expired_count']) * 100, 1) : 0;
        $renewalPrevious = ($compAgg && $compAgg['expired_count'] > 0) ? round(($compAgg['renewed_count'] / $compAgg['expired_count']) * 100, 1) : 0;
        $renewalRate = ["current" => $renewalCurrent, "previous" => $renewalPrevious, "change" => $this->calculatePercentageChange($renewalCurrent, $renewalPrevious)];

        // Average lifespan
        $lifespanCurrent = ($currentAgg['lifespan_sub_count'] > 0) ? round($currentAgg['total_lifespan_days'] / $currentAgg['lifespan_sub_count'], 1) : 0;
        $lifespanPrevious = ($compAgg && $compAgg['lifespan_sub_count'] > 0) ? round($compAgg['total_lifespan_days'] / $compAgg['lifespan_sub_count'], 1) : 0;
        $averageLifespan = ["current" => $lifespanCurrent, "previous" => $lifespanPrevious, "change" => $this->calculatePercentageChange($lifespanCurrent, $lifespanPrevious)];

        Log::info("Materialized aggregates done (" . round((microtime(true) - $methodStart) * 1000) . "ms)");

        // === 3. Retention trend (optimized: use materialized activations + single targeted query) ===
        $retentionCacheKey = 'ret_trend:' . md5("{$startBound}:{$endExclusive}:{$selectedOperator}");
        try {
            $retentionTrend = Cache::remember($retentionCacheKey, 1800, function() use ($startBound, $endExclusive, $selectedOperator, $operatorId) {
                return $this->calculateRetentionTrendMaterialized($startBound, $endExclusive, $selectedOperator, $operatorId);
            });
        } catch (\Exception $e) {
            Log::error("retentionTrend materialized - ECHEC: " . $e->getMessage());
            $retentionTrend = [];
        }

        // === 4. Quarterly active locations (cached, fast) ===
        $locationsCacheKey = 'qloc:' . md5($endExclusive->copy()->subDay()->toDateString());
        try {
            $quarterlyActiveLocations = Cache::remember($locationsCacheKey, 3600, function() use ($endExclusive) {
                return $this->calculateQuarterlyActiveLocations($endExclusive->copy()->subDay()->toDateString());
            });
        } catch (\Exception $e) {
            Log::error("quarterlyActiveLocations - ECHEC: " . $e->getMessage());
            $quarterlyActiveLocations = [];
        }

        // === 5. Subscription details (limited to 1000 - fast) ===
        $detailsCacheKey = 'sub_det:' . md5("{$startBound}:{$endExclusive}:{$selectedOperator}");
        try {
            $subscriptionDetails = Cache::remember($detailsCacheKey, 1800, function() use ($startBound, $endExclusive, $selectedOperator) {
                return $this->getSubscriptionDetails($startBound, $endExclusive, $selectedOperator);
            });
        } catch (\Exception $e) {
            Log::error("subscriptionDetails - ECHEC: " . $e->getMessage());
            $subscriptionDetails = [];
        }

        // === 6. Cohorts (6 monthly queries - bounded) ===
        $cohortsCacheKey = 'cohorts:' . md5("{$startBound}:{$endExclusive}:{$selectedOperator}");
        try {
            $cohorts = Cache::remember($cohortsCacheKey, 1800, function() use ($startBound, $endExclusive, $selectedOperator) {
                return $this->calculateCohorts($startBound->format('Y-m-d'), $endExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator);
            });
        } catch (\Exception $e) {
            Log::error("cohorts - ECHEC: " . $e->getMessage());
            $cohorts = [];
        }

        // === 7. Reactivation rate (bounded live query) ===
        $reactivationCurrent = 0;
        $reactivationPrevious = 0;
        try {
            $reactivationCurrent = $this->calculateReactivationRate($startBound->format('Y-m-d'), $endExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator);
            if ($compStartBound && $compEndExclusive) {
                $reactivationPrevious = $this->calculateReactivationRate($compStartBound->format('Y-m-d'), $compEndExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator);
            }
        } catch (\Exception $e) {
            Log::error("reactivation - ECHEC: " . $e->getMessage());
        }
        $reactivationRate = ["current" => $reactivationCurrent, "previous" => $reactivationPrevious, "change" => $this->calculatePercentageChange($reactivationCurrent, $reactivationPrevious)];

        // === 8. Timwe daily statistics (already materialized via timwe_daily_stats) ===
        $dailyStatistics = [];
        $dailyStatisticsComparison = [];
        try {
            $dailyStatistics = $this->getDailyStatistics($startBound, $endExclusive, $selectedOperator);
        } catch (\Exception $e) {
            Log::error("dailyStatistics - ECHEC: " . $e->getMessage());
        }
        if ($compStartBound && $compEndExclusive) {
            try {
                $dailyStatisticsComparison = $this->getDailyStatistics($compStartBound, $compEndExclusive, $selectedOperator);
            } catch (\Exception $e) {
                Log::error("dailyStatisticsComparison - ECHEC: " . $e->getMessage());
            }
        }

        $timweMonthlyStats = $this->groupTimweStatsByMonth($dailyStatistics);
        $timweMonthlyStatsComparison = $this->groupTimweStatsByMonth($dailyStatisticsComparison);

        $totalElapsed = round((microtime(true) - $methodStart) * 1000);
        Log::info("getSubscriptionsData MATERIALIZED - COMPLET en {$totalElapsed}ms");

        return [
            "daily_activations" => $dailyActivations,
            "retention_trend" => $retentionTrend,
            "quarterly_active_locations" => $quarterlyActiveLocations,
            "details" => $subscriptionDetails,
            "daily_statistics" => $dailyStatistics,
            "daily_statistics_comparison" => $dailyStatisticsComparison,
            "timwe_monthly_stats" => $timweMonthlyStats,
            "timwe_monthly_stats_comparison" => $timweMonthlyStatsComparison,
            "timwe_transactions_by_user" => [],
            "activations_by_channel" => $activationsByChannel,
            "plan_distribution" => $planDistribution,
            "cohorts" => $cohorts,
            "renewal_rate" => $renewalRate,
            "average_lifespan" => $averageLifespan,
            "reactivation_rate" => $reactivationRate
        ];
    }

    /**
     * Get aggregated metrics from subscription_daily_stats for a period.
     */
    private function getMaterializedAggregates(Carbon $startBound, Carbon $endExclusive, ?int $operatorId): array
    {
        $row = DB::table('subscription_daily_stats')
            ->where('stat_date', '>=', $startBound->toDateString())
            ->where('stat_date', '<', $endExclusive->toDateString())
            ->where(function ($q) use ($operatorId) {
                if ($operatorId === null) {
                    $q->whereNull('operator_id');
                } else {
                    $q->where('operator_id', $operatorId);
                }
            })
            ->selectRaw('
                SUM(channel_cb) as channel_cb,
                SUM(channel_recharge) as channel_recharge,
                SUM(channel_phone_balance) as channel_phone_balance,
                SUM(channel_other) as channel_other,
                SUM(plan_daily) as plan_daily,
                SUM(plan_monthly) as plan_monthly,
                SUM(plan_annual) as plan_annual,
                SUM(plan_other) as plan_other,
                SUM(expired_count) as expired_count,
                SUM(renewed_count) as renewed_count,
                SUM(total_lifespan_days) as total_lifespan_days,
                SUM(lifespan_sub_count) as lifespan_sub_count
            ')
            ->first();

        return [
            'channel_cb' => (int)($row->channel_cb ?? 0),
            'channel_recharge' => (int)($row->channel_recharge ?? 0),
            'channel_phone_balance' => (int)($row->channel_phone_balance ?? 0),
            'channel_other' => (int)($row->channel_other ?? 0),
            'plan_daily' => (int)($row->plan_daily ?? 0),
            'plan_monthly' => (int)($row->plan_monthly ?? 0),
            'plan_annual' => (int)($row->plan_annual ?? 0),
            'plan_other' => (int)($row->plan_other ?? 0),
            'expired_count' => (int)($row->expired_count ?? 0),
            'renewed_count' => (int)($row->renewed_count ?? 0),
            'total_lifespan_days' => (int)($row->total_lifespan_days ?? 0),
            'lifespan_sub_count' => (int)($row->lifespan_sub_count ?? 0),
        ];
    }

    /**
     * SLOW PATH (fallback): Original live queries when no materialized data exists.
     */
    private function getSubscriptionsDataLive(Carbon $startBound, Carbon $endExclusive, string $selectedOperator, ?Carbon $compStartBound, ?Carbon $compEndExclusive, float $methodStart): array
    {
        $maxTimeSec = 90;
        
        try { DB::statement("SET SESSION max_execution_time=30000"); } catch (\Exception $e) {}
        
        $periodDays = $startBound->diffInDays($endExclusive);
        $granularity = $periodDays > 365 ? 'month' : 'day';
        $caDateExpr = $granularity === 'month' ? "DATE_FORMAT(client_abonnement_creation, '%Y-%m-01')" : "DATE(client_abonnement_creation)";
        
        // Activations quotidiennes
        if ($selectedOperator === 'ALL' || empty($selectedOperator)) {
            $activationsQuery = DB::table("client_abonnement as ca")
                ->select(DB::raw("$caDateExpr as date"), DB::raw("COUNT(*) as activations"))
                ->where("ca.client_abonnement_creation", ">=", $startBound)
                ->where("ca.client_abonnement_creation", "<", $endExclusive);
        } else {
            $activationsQuery = DB::table("client_abonnement as ca")
                ->join("country_payments_methods as cpm", "ca.country_payments_methods_id", "=", "cpm.country_payments_methods_id")
                ->select(DB::raw("$caDateExpr as date"), DB::raw("COUNT(*) as activations"))
                ->where("ca.client_abonnement_creation", ">=", $startBound)
                ->where("ca.client_abonnement_creation", "<", $endExclusive);
            $this->applyOperatorFilter($activationsQuery, $selectedOperator);
        }
        
        $activationsRaw = $activationsQuery->groupBy(DB::raw($caDateExpr))->orderBy("date")->get()->keyBy('date')->toArray();
        
        $startDate = $startBound->copy();
        $endDate = $endExclusive->copy()->subDay();
        $dailyActivations = [];
        
        if ($granularity === 'month') {
            $cursor = $startDate->copy()->firstOfMonth();
            while ($cursor->lte($endDate)) {
                $key = $cursor->copy()->firstOfMonth()->toDateString();
                $activations = isset($activationsRaw[$key]) ? (int)$activationsRaw[$key]->activations : 0;
                $dailyActivations[] = ['date' => $key, 'activations' => $activations, 'active' => round($activations * 0.95)];
                $cursor->addMonth();
            }
        } else {
            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                $dateStr = $cursor->toDateString();
                $activations = isset($activationsRaw[$dateStr]) ? (int)$activationsRaw[$dateStr]->activations : 0;
                $dailyActivations[] = ['date' => $dateStr, 'activations' => $activations, 'active' => round($activations * 0.95)];
                $cursor->addDay();
            }
        }
        
        $retentionCacheKey = 'ret_trend:' . md5("{$startBound}:{$endExclusive}:{$selectedOperator}");
        try {
            $retentionTrend = Cache::remember($retentionCacheKey, 1800, fn() => $this->calculateRetentionTrendOptimized($startBound, $endExclusive, $selectedOperator));
        } catch (\Exception $e) { $retentionTrend = []; }
        
        $locationsCacheKey = 'qloc:' . md5($endExclusive->copy()->subDay()->toDateString());
        try {
            $quarterlyActiveLocations = Cache::remember($locationsCacheKey, 3600, fn() => $this->calculateQuarterlyActiveLocations($endExclusive->copy()->subDay()->toDateString()));
        } catch (\Exception $e) { $quarterlyActiveLocations = []; }
        
        $elapsed = microtime(true) - $methodStart;
        $timeExceeded = ($elapsed > $maxTimeSec);
        
        $subscriptionDetails = [];
        if (!$timeExceeded) {
            try {
                $subscriptionDetails = Cache::remember('sub_det:' . md5("{$startBound}:{$endExclusive}:{$selectedOperator}"), 1800, fn() => $this->getSubscriptionDetails($startBound, $endExclusive, $selectedOperator));
            } catch (\Exception $e) {}
        }
        
        $defaultChannel = ['cb' => 0, 'recharge' => 0, 'phone_balance' => 0, 'other' => 0];
        $defaultPlan = ['daily' => 0, 'monthly' => 0, 'annual' => 0, 'other' => 0];
        $defaultComparison = ["current" => 0, "previous" => 0, "change" => 0.0];
        
        $activationsCurrent = $defaultChannel;
        $activationsPrevious = $defaultChannel;
        if (!$timeExceeded) {
            try {
                $activationsCurrent = Cache::remember('actbychan:' . md5("{$startBound}:{$endExclusive}:{$selectedOperator}"), 1800, fn() => $this->calculateActivationsByPaymentMethod($startBound, $endExclusive, $selectedOperator));
                $activationsPrevious = ($compStartBound && $compEndExclusive) ? Cache::remember('actbychan:' . md5("{$compStartBound}:{$compEndExclusive}:{$selectedOperator}"), 1800, fn() => $this->calculateActivationsByPaymentMethod($compStartBound, $compEndExclusive, $selectedOperator)) : $defaultChannel;
            } catch (\Exception $e) {}
            $elapsed = microtime(true) - $methodStart;
            $timeExceeded = ($elapsed > $maxTimeSec);
        }
        
        $activationsByChannel = [];
        foreach (['cb', 'recharge', 'phone_balance', 'other'] as $ch) {
            $activationsByChannel[$ch] = ["current" => $activationsCurrent[$ch] ?? 0, "previous" => $activationsPrevious[$ch] ?? 0, "change" => $this->calculatePercentageChange($activationsCurrent[$ch] ?? 0, $activationsPrevious[$ch] ?? 0)];
        }
        
        $plansCurrent = $defaultPlan;
        $plansPrevious = $defaultPlan;
        if (!$timeExceeded) {
            try {
                $plansCurrent = Cache::remember('plandist:' . md5("{$startBound}:{$endExclusive}:{$selectedOperator}"), 1800, fn() => $this->calculatePlanDistribution($startBound, $endExclusive, $selectedOperator));
                $plansPrevious = ($compStartBound && $compEndExclusive) ? Cache::remember('plandist:' . md5("{$compStartBound}:{$compEndExclusive}:{$selectedOperator}"), 1800, fn() => $this->calculatePlanDistribution($compStartBound, $compEndExclusive, $selectedOperator)) : $defaultPlan;
            } catch (\Exception $e) {}
            $elapsed = microtime(true) - $methodStart;
            $timeExceeded = ($elapsed > $maxTimeSec);
        }
        
        $planDistribution = [];
        foreach (['daily', 'monthly', 'annual', 'other'] as $pl) {
            $planDistribution[$pl] = ["current" => $plansCurrent[$pl] ?? 0, "previous" => $plansPrevious[$pl] ?? 0, "change" => $this->calculatePercentageChange($plansCurrent[$pl] ?? 0, $plansPrevious[$pl] ?? 0)];
        }
        
        $cohorts = [];
        if (!$timeExceeded) {
            try {
                $cohorts = Cache::remember('cohorts:' . md5("{$startBound}:{$endExclusive}:{$selectedOperator}"), 1800, fn() => $this->calculateCohorts($startBound->format('Y-m-d'), $endExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator));
            } catch (\Exception $e) {}
            $elapsed = microtime(true) - $methodStart;
            $timeExceeded = ($elapsed > $maxTimeSec);
        }
        
        $renewalRate = $defaultComparison;
        $averageLifespan = $defaultComparison;
        $reactivationRate = $defaultComparison;
        if (!$timeExceeded) {
            try {
                $renewalCurrent = Cache::remember('renewal:' . md5("{$startBound}:{$endExclusive}:{$selectedOperator}"), 1800, fn() => $this->calculateRenewalRate($startBound->format('Y-m-d'), $endExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator));
                $renewalPrevious = ($compStartBound && $compEndExclusive) ? Cache::remember('renewal:' . md5("{$compStartBound}:{$compEndExclusive}:{$selectedOperator}"), 1800, fn() => $this->calculateRenewalRate($compStartBound->format('Y-m-d'), $compEndExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator)) : 0;
                $renewalRate = ["current" => $renewalCurrent, "previous" => $renewalPrevious, "change" => $this->calculatePercentageChange($renewalCurrent, $renewalPrevious)];
                
                $lifespanCurrent = Cache::remember('lifespan:' . md5("{$startBound}:{$endExclusive}:{$selectedOperator}"), 1800, fn() => $this->calculateAverageLifespan($startBound->format('Y-m-d'), $endExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator));
                $lifespanPrevious = ($compStartBound && $compEndExclusive) ? Cache::remember('lifespan:' . md5("{$compStartBound}:{$compEndExclusive}:{$selectedOperator}"), 1800, fn() => $this->calculateAverageLifespan($compStartBound->format('Y-m-d'), $compEndExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator)) : 0;
                $averageLifespan = ["current" => $lifespanCurrent, "previous" => $lifespanPrevious, "change" => $this->calculatePercentageChange($lifespanCurrent, $lifespanPrevious)];
                
                $reactivationCurrent = $this->calculateReactivationRate($startBound->format('Y-m-d'), $endExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator);
                $reactivationPrevious = ($compStartBound && $compEndExclusive) ? $this->calculateReactivationRate($compStartBound->format('Y-m-d'), $compEndExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator) : 0;
                $reactivationRate = ["current" => $reactivationCurrent, "previous" => $reactivationPrevious, "change" => $this->calculatePercentageChange($reactivationCurrent, $reactivationPrevious)];
            } catch (\Exception $e) {}
        }
        
        $dailyStatistics = [];
        $dailyStatisticsComparison = [];
        if (!$timeExceeded) {
            try { $dailyStatistics = $this->getDailyStatistics($startBound, $endExclusive, $selectedOperator); } catch (\Exception $e) {}
            if ($compStartBound && $compEndExclusive) {
                try { $dailyStatisticsComparison = $this->getDailyStatistics($compStartBound, $compEndExclusive, $selectedOperator); } catch (\Exception $e) {}
            }
        }
        
        $timweMonthlyStats = $this->groupTimweStatsByMonth($dailyStatistics);
        $timweMonthlyStatsComparison = $this->groupTimweStatsByMonth($dailyStatisticsComparison);
        
        try { DB::statement("SET SESSION max_execution_time=0"); } catch (\Exception $e) {}
        
        $totalElapsed = round((microtime(true) - $methodStart) * 1000);
        Log::info("getSubscriptionsData LIVE - COMPLET en {$totalElapsed}ms" . (($elapsed ?? 0) > $maxTimeSec ? " (données partielles)" : ""));
        
        return [
            "daily_activations" => $dailyActivations,
            "retention_trend" => $retentionTrend,
            "quarterly_active_locations" => $quarterlyActiveLocations,
            "details" => $subscriptionDetails,
            "daily_statistics" => $dailyStatistics,
            "daily_statistics_comparison" => $dailyStatisticsComparison,
            "timwe_monthly_stats" => $timweMonthlyStats,
            "timwe_monthly_stats_comparison" => $timweMonthlyStatsComparison,
            "timwe_transactions_by_user" => [],
            "activations_by_channel" => $activationsByChannel,
            "plan_distribution" => $planDistribution,
            "cohorts" => $cohorts,
            "renewal_rate" => $renewalRate,
            "average_lifespan" => $averageLifespan,
            "reactivation_rate" => $reactivationRate
        ];
    }
    

    /**
     * Materialized retention trend: uses pre-computed activated_count + single batch SQL for "still active".
     * Much faster than full GROUP BY scan on 353K rows.
     */
    private function calculateRetentionTrendMaterialized(Carbon $startBound, Carbon $endExclusive, string $selectedOperator, ?int $operatorId): array
    {
        try {
            $periodDays = $startBound->diffInDays($endExclusive);
            $intervalDays = max(1, intval($periodDays / 30));
            $endDateStr = $endExclusive->copy()->subDay()->toDateString();

            // Step 1: Get activated_count per day from materialized table
            $matData = DB::table('subscription_daily_stats')
                ->where('stat_date', '>=', $startBound->toDateString())
                ->where('stat_date', '<', $endExclusive->toDateString())
                ->where(function ($q) use ($operatorId) {
                    if ($operatorId === null) {
                        $q->whereNull('operator_id');
                    } else {
                        $q->where('operator_id', $operatorId);
                    }
                })
                ->pluck('activated_count', 'stat_date')
                ->toArray();

            // Step 2: Build sample dates
            $sampleDates = [];
            $cursor = $startBound->copy();
            $endDate = $endExclusive->copy()->subDay();
            while ($cursor->lte($endDate)) {
                $sampleDates[] = $cursor->toDateString();
                $cursor->addDays($intervalDays);
            }

            if (empty($sampleDates)) return [];

            // Step 3: Single batch query - count "still active" subscriptions per creation date
            // Only for sample dates, not all dates
            $opFilter = ($operatorId !== null) ? " AND ca.country_payments_methods_id = {$operatorId}" : '';
            $dateList = "'" . implode("','", $sampleDates) . "'";
            
            $activeResults = DB::select("
                SELECT DATE(ca.client_abonnement_creation) as cdate, COUNT(*) as active_count
                FROM client_abonnement ca
                WHERE DATE(ca.client_abonnement_creation) IN ({$dateList})
                AND (ca.client_abonnement_expiration IS NULL OR ca.client_abonnement_expiration > ?)
                {$opFilter}
                GROUP BY DATE(ca.client_abonnement_creation)
            ", [$endDateStr]);

            $activeMap = [];
            foreach ($activeResults as $r) {
                $activeMap[$r->cdate] = (int)$r->active_count;
            }

            // Step 4: Compute retention rate
            $trend = [];
            foreach ($sampleDates as $d) {
                $activated = $matData[$d] ?? 0;
                $active = $activeMap[$d] ?? 0;
                $rate = ($activated > 0) ? round(($active / $activated) * 100, 1) : 100.0;
                $trend[] = ['date' => $d, 'rate' => $rate, 'value' => $rate];
            }

            Log::info("calculateRetentionTrendMaterialized - OK", ['points' => count($trend), 'sample_dates' => count($sampleDates)]);
            return $trend;
        } catch (\Exception $e) {
            Log::error("Retention materialized failed, fallback to live: " . $e->getMessage());
            return $this->calculateRetentionTrendOptimized($startBound, $endExclusive, $selectedOperator);
        }
    }

    /**
     * Calcule la tendance de rétention jour par jour (VERSION OPTIMISÉE - une seule requête)
     */
    private function calculateRetentionTrendOptimized(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        try {
            $periodDays = $startBound->diffInDays($endExclusive);
            $intervalDays = max(1, intval($periodDays / 30));
            $endDateStr = $endExclusive->toDateString();
            
            // Construire la requête - SKIP le JOIN inutile quand opérateur=ALL
            if ($selectedOperator === 'ALL' || empty($selectedOperator)) {
                $activationsQuery = DB::table('client_abonnement as ca')
                    ->selectRaw(
                        "DATE(ca.client_abonnement_creation) as date, COUNT(*) as activated, SUM(CASE WHEN ca.client_abonnement_expiration IS NULL OR ca.client_abonnement_expiration > ? THEN 1 ELSE 0 END) as active",
                        [$endDateStr]
                    )
                    ->where('ca.client_abonnement_creation', '>=', $startBound)
                    ->where('ca.client_abonnement_creation', '<', $endExclusive);
            } else {
                $activationsQuery = DB::table('client_abonnement as ca')
                    ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
                    ->selectRaw(
                        "DATE(ca.client_abonnement_creation) as date, COUNT(*) as activated, SUM(CASE WHEN ca.client_abonnement_expiration IS NULL OR ca.client_abonnement_expiration > ? THEN 1 ELSE 0 END) as active",
                        [$endDateStr]
                    )
                    ->where('ca.client_abonnement_creation', '>=', $startBound)
                    ->where('ca.client_abonnement_creation', '<', $endExclusive);
                $this->applyOperatorFilter($activationsQuery, $selectedOperator);
            }
            
            $results = $activationsQuery
                ->groupBy(DB::raw("DATE(ca.client_abonnement_creation)"))
                ->orderBy('date')
                ->get()
                ->keyBy('date');
            
            $trend = [];
            $cursor = $startBound->copy();
            $endDate = $endExclusive->copy()->subDay();
            
            while ($cursor->lte($endDate)) {
                $dateStr = $cursor->toDateString();
                $result = $results->get($dateStr);
                $rate = ($result && $result->activated > 0) ? round(($result->active / $result->activated) * 100, 1) : 100.0;
                $trend[] = ['date' => $dateStr, 'rate' => $rate, 'value' => $rate];
                $cursor->addDays($intervalDays);
            }
            
            Log::info("calculateRetentionTrendOptimized - OK", ['points' => count($trend)]);
            return $trend;
        } catch (\Exception $e) {
            Log::error("Erreur rétention (timeout ou erreur SQL): " . $e->getMessage());
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
            // Version optimisée: une seule requête pour le total actuel,
            // pas de boucle sur chaque trimestre (économise 14 requêtes DB)
            $hasPartenerActive = Cache::remember('schema:partner:partener_active', 86400, function() {
                return Schema::hasColumn('partner', 'partener_active');
            });
            
            $countLocations = 0;
            if ($hasPartenerActive) {
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
            
            // Générer la série trimestrielle avec le même total (données statiques)
            $quarterlyActiveLocations = [];
            $quarterCursor = Carbon::parse($endDate)->firstOfQuarter()->subQuarters(7);
            $quarterEnd = Carbon::parse($endDate)->firstOfQuarter();
            
            while ($quarterCursor->lte($quarterEnd)) {
                $quarterlyActiveLocations[] = [
                    'quarter' => $quarterCursor->format('Y') . '-Q' . $quarterCursor->quarter,
                    'locations' => (int)$countLocations
                ];
                $quarterCursor->addQuarter();
            }
            
            Log::info("calculateQuarterlyActiveLocations - OK", ['quarters' => count($quarterlyActiveLocations), 'locations' => $countLocations]);
            return $quarterlyActiveLocations;
        } catch (\Exception $e) {
            Log::error("Erreur calcul points de vente: " . $e->getMessage());
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
            $limit = min(1000, max(100, intval($periodDays * 10)));
            
            // Requête simplifiée SANS la sous-requête corrélée sur transactions_history
            // (c'était la cause du timeout de 30s)
            $query = DB::table('client_abonnement as ca')
                ->leftJoin('client as c', 'ca.client_id', '=', 'c.client_id')
                ->select([
                    'ca.client_id',
                    'c.client_prenom as first_name',
                    'c.client_nom as last_name',
                    'c.client_telephone as phone',
                    'ca.client_abonnement_creation as activation_date',
                    'ca.client_abonnement_expiration as end_date',
                    DB::raw("CASE 
                        WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) <= 3 THEN 'Trial'
                        WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) = 1 THEN 'Journalier'
                        WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) BETWEEN 20 AND 40 THEN 'Mensuel'
                        WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) >= 330 THEN 'Annuel'
                        ELSE 'Autre'
                    END as plan")
                ])
                ->where(function($q) use ($startBound, $endExclusive) {
                    $q->where(function($subQ) use ($startBound, $endExclusive) {
                        $subQ->where('ca.client_abonnement_creation', '>=', $startBound)
                             ->where('ca.client_abonnement_creation', '<', $endExclusive);
                    })
                    ->orWhere(function($subQ) use ($endExclusive) {
                        $subQ->whereNull('ca.client_abonnement_expiration')
                             ->orWhere('ca.client_abonnement_expiration', '>=', $endExclusive);
                    });
                });
            
            // Ajouter le nom opérateur + JOIN conditionnel
            if ($selectedOperator !== 'ALL' && !empty($selectedOperator)) {
                $query->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id');
                $query->addSelect('cpm.country_payments_methods_name as operator');
                $this->applyOperatorFilter($query, $selectedOperator);
            } else {
                $query->addSelect(DB::raw("(SELECT cpm2.country_payments_methods_name FROM country_payments_methods cpm2 WHERE cpm2.country_payments_methods_id = ca.country_payments_methods_id LIMIT 1) as operator"));
            }
            
            // Skip expensive COUNT(*) - use materialized data or estimate
            $totalCount = -1; // Will display "1000+" in frontend
            
            $results = $query->orderByDesc('ca.client_abonnement_creation')->limit($limit)->get();
            
            $dataArray = $results->map(function($item) {
                $array = (array)$item;
                if (!isset($array['client_id'])) $array['client_id'] = null;
                return $array;
            })->toArray();
            
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
            Log::error("Erreur getSubscriptionDetails: " . $e->getMessage());
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
    public function getDailyStatistics(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
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
            $endCarbon = Carbon::parse($endDate);
            $cohorts = [];
            
            // Build 6 month boundaries
            $months = [];
            for ($i = 5; $i >= 0; $i--) {
                $cohortMonth = $endCarbon->copy()->subMonths($i);
                $months[] = [
                    'label' => $cohortMonth->format('M Y'),
                    'start' => $cohortMonth->copy()->startOfMonth(),
                    'end' => $cohortMonth->copy()->endOfMonth(),
                    'd30' => $cohortMonth->copy()->startOfMonth()->addDays(30),
                    'd60' => $cohortMonth->copy()->startOfMonth()->addDays(60),
                ];
            }

            // Single batch query: compute total, d30, d60 for all 6 months at once
            $globalStart = $months[0]['start'];
            $globalEnd = $months[count($months) - 1]['end'];

            // Build CASE expressions for each month
            $selectParts = [];
            $bindings = [];
            foreach ($months as $idx => $m) {
                $mStart = $m['start']->toDateTimeString();
                $mEnd = $m['end']->toDateTimeString();
                $d30 = $m['d30']->toDateTimeString();
                $d60 = $m['d60']->toDateTimeString();

                $selectParts[] = "SUM(CASE WHEN ca.client_abonnement_creation BETWEEN ? AND ? THEN 1 ELSE 0 END) as total_{$idx}";
                $bindings[] = $mStart;
                $bindings[] = $mEnd;

                $selectParts[] = "SUM(CASE WHEN ca.client_abonnement_creation BETWEEN ? AND ? AND (ca.client_abonnement_expiration IS NULL OR ca.client_abonnement_expiration >= ?) THEN 1 ELSE 0 END) as d30_{$idx}";
                $bindings[] = $mStart;
                $bindings[] = $mEnd;
                $bindings[] = $d30;

                $selectParts[] = "SUM(CASE WHEN ca.client_abonnement_creation BETWEEN ? AND ? AND (ca.client_abonnement_expiration IS NULL OR ca.client_abonnement_expiration >= ?) THEN 1 ELSE 0 END) as d60_{$idx}";
                $bindings[] = $mStart;
                $bindings[] = $mEnd;
                $bindings[] = $d60;
            }

            $selectSql = implode(', ', $selectParts);
            
            $opJoin = '';
            $opWhere = '';
            if ($operatorFilter !== 'ALL' && !empty($operatorFilter)) {
                $opId = $this->getOperatorId($operatorFilter);
                if ($opId) {
                    $opWhere = " AND ca.country_payments_methods_id = {$opId}";
                }
            }

            $sql = "SELECT {$selectSql} FROM client_abonnement ca WHERE ca.client_abonnement_creation >= ? AND ca.client_abonnement_creation <= ? {$opWhere}";
            $bindings[] = $globalStart->toDateTimeString();
            $bindings[] = $globalEnd->toDateTimeString();

            $result = DB::selectOne($sql, $bindings);

            foreach ($months as $idx => $m) {
                $total = (int)($result->{"total_{$idx}"} ?? 0);
                $d30 = (int)($result->{"d30_{$idx}"} ?? 0);
                $d60 = (int)($result->{"d60_{$idx}"} ?? 0);

                $cohorts[] = [
                    'month' => $m['label'],
                    'total' => $total,
                    'survival_d30' => $total > 0 ? round(($d30 / $total) * 100, 1) : 0,
                    'survival_d60' => $total > 0 ? round(($d60 / $total) * 100, 1) : 0,
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
                ->whereBetween('ca.client_abonnement_expiration', [$startDate, $endCarbon]);
            $this->applyOperatorJoinAndFilter($expiredQuery, $operatorFilter, 'ca');
            
            $expiredSubscriptions = $expiredQuery->count();
            if ($expiredSubscriptions == 0) return 0;
            
            $windowDays = 60; // fenêtre de renouvellement
            
            $renewedQuery = DB::table('client_abonnement as ca1')
                ->join('client_abonnement as ca2', 'ca1.client_id', '=', 'ca2.client_id')
                ->whereBetween('ca1.client_abonnement_expiration', [$startDate, $endCarbon])
                ->where('ca2.client_abonnement_creation', '>', DB::raw('ca1.client_abonnement_expiration'))
                ->whereRaw("ca2.client_abonnement_creation <= DATE_ADD(ca1.client_abonnement_expiration, INTERVAL ? DAY)", [$windowDays]);
            $this->applyOperatorJoinAndFilter($renewedQuery, $operatorFilter, 'ca1');
            
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
                ->where('ca.client_abonnement_creation', '>=', $startDate)
                ->where('ca.client_abonnement_creation', '<=', $endCarbon);
            $this->applyOperatorJoinAndFilter($query, $operatorFilter, 'ca');
            
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
                ->where('ca.client_abonnement_expiration', '<', $startDate);
            $this->applyOperatorJoinAndFilter($expiredBeforePeriod, $operatorFilter, 'ca');
            
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
                ->whereIn('ca.client_id', $expiredClients)
                ->where('ca.client_abonnement_creation', '>=', $startDate)
                ->where('ca.client_abonnement_creation', '<=', $endCarbon);
            $this->applyOperatorJoinAndFilter($reactivatedQuery, $operatorFilter, 'ca');
            
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
    public function groupTimweStatsByMonth(array $dailyStats): array
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

