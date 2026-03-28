<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Traits\OperatorHelper;
use App\Traits\TransactionHelper;
use App\Models\TimweDailyStat;

class KPIService
{
    use OperatorHelper, TransactionHelper;

    protected MerchantService $merchantService;

    public function __construct(MerchantService $merchantService)
    {
        $this->merchantService = $merchantService;
    }

    public function getKPIs(Carbon $startBound, Carbon $endExclusive, Carbon $compStartBound, Carbon $compEndExclusive, string $selectedOperator): array
    {
        $materialized = $this->getKPIsFromMaterialized($startBound, $endExclusive, $compStartBound, $compEndExclusive, $selectedOperator);
        if ($materialized !== null) {
            Log::info("KPIs servis depuis les données matérialisées");
            return $materialized;
        }
        return $this->getKPIsOptimized($startBound, $endExclusive, $compStartBound, $compEndExclusive, $selectedOperator);
    }

    private function getKPIsFromMaterialized(Carbon $startBound, Carbon $endExclusive, Carbon $compStartBound, Carbon $compEndExclusive, string $selectedOperator): ?array
    {
        try {
            $operatorId = ($selectedOperator === 'ALL') ? null : ($this->getOperatorId($selectedOperator));
            
            $startDate = $startBound->toDateString();
            $endDate = $endExclusive->copy()->subDay()->toDateString();
            $compStartDate = $compStartBound->toDateString();
            $compEndDate = $compEndExclusive->copy()->subDay()->toDateString();
            
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
                    if ($tolerance > 0 && $actualDays < $expectedDays) {
                        $endDate = Carbon::yesterday()->toDateString();
                    }
                    return $this->buildKPIsFromDashboardDailyStats($startBound, $endExclusive, $compStartBound, $compEndExclusive, $startDate, $endDate, $compStartDate, $compEndDate, $operatorId, $selectedOperator);
                }
            }
            
            $subCoverage = $this->hasMaterializedCoverage($startBound, $endExclusive, $operatorId);
            $txCoverage = $this->hasTransactionMaterializedCoverage($startBound, $endExclusive);
            
            if ($subCoverage && $txCoverage) {
                return $this->buildKPIsFromCombinedMaterialized($startBound, $endExclusive, $compStartBound, $compEndExclusive, $operatorId, $selectedOperator);
            }
            
            return null;
        } catch (\Exception $e) {
            Log::warning("KPIs materialized read failed: " . $e->getMessage());
            return null;
        }
    }

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

    private function hasMaterializedCoverage(Carbon $startBound, Carbon $endExclusive, ?int $operatorId): bool
    {
        try {
            $expectedDays = $startBound->diffInDays($endExclusive);
            if ($expectedDays <= 0) return false;
            $count = DB::table('subscription_daily_stats')
                ->where('stat_date', '>=', $startBound->toDateString())
                ->where('stat_date', '<', $endExclusive->toDateString())
                ->where(function ($q) use ($operatorId) {
                    if ($operatorId === null) $q->whereNull('operator_id');
                    else $q->where('operator_id', $operatorId);
                })
                ->count();
            return ($count / $expectedDays) >= 0.8;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function buildKPIsFromDashboardDailyStats(Carbon $startBound, Carbon $endExclusive, Carbon $compStartBound, Carbon $compEndExclusive, string $startDate, string $endDate, string $compStartDate, string $compEndDate, ?int $operatorId, string $selectedOperator): ?array
    {
        $current = $this->aggregateMaterialized($startDate, $endDate, $operatorId);
        $comparison = $this->aggregateMaterialized($compStartDate, $compEndDate, $operatorId);
        if (!$current) return null;
        
        return $this->assembleKPIResult($current, $comparison, $startBound, $endExclusive, $compStartBound, $compEndExclusive, $operatorId, $selectedOperator, $endDate, $compEndDate);
    }

    private function buildKPIsFromCombinedMaterialized(Carbon $startBound, Carbon $endExclusive, Carbon $compStartBound, Carbon $compEndExclusive, ?int $operatorId, string $selectedOperator): ?array
    {
        $current = $this->aggregateCombinedMaterialized($startBound, $endExclusive, $operatorId);
        $comparison = $this->aggregateCombinedMaterialized($compStartBound, $compEndExclusive, $operatorId);
        if (!$current) return null;
        
        $endDate = $endExclusive->copy()->subDay()->toDateString();
        $compEndDate = $compEndExclusive->copy()->subDay()->toDateString();
        
        return $this->assembleKPIResult($current, $comparison, $startBound, $endExclusive, $compStartBound, $compEndExclusive, $operatorId, $selectedOperator, $endDate, $compEndDate);
    }

    private function aggregateCombinedMaterialized(Carbon $startBound, Carbon $endExclusive, ?int $operatorId): ?array
    {
        $startStr = $startBound->toDateString();
        $endStr = $endExclusive->copy()->subDay()->toDateString();
        
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
        
        $txAgg = DB::table('transaction_daily_stats')
            ->where('stat_date', '>=', $startStr)
            ->where('stat_date', '<=', $endStr)
            ->whereNull('operator_id')
            ->selectRaw('SUM(transaction_count) as transactions, SUM(distinct_users) as transacting_users, SUM(cohort_transaction_count) as cohort_tx, SUM(cohort_distinct_users) as cohort_users, SUM(active_merchants) as active_merchants')
            ->first();
        
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
        
        $totalActivePartnersDB = Cache::remember('total_partners_with_promo_v3', 3600, function() {
            // Partenaires avec au moins 1 promotion active ET au moins 1 point de vente
            // Aligné avec la logique de clubprivileges.app
            return DB::table('promotion as pr')
                ->where('pr.promotion_active', 1)
                ->whereIn('pr.partner_id', DB::table('partner_location')->distinct()->pluck('partner_id'))
                ->distinct('pr.partner_id')
                ->count('pr.partner_id');
        });
        $totalMerchantsEverActive = Cache::remember('total_merchants_ever', 3600, fn() => DB::table('history as h')->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')->distinct('p.partner_id')->count('p.partner_id'));
        $totalLocationsActive = Cache::remember('total_locations_promo_v3', 3600, function() {
            try {
                // Points de vente des partenaires ayant au moins 1 promotion active
                return DB::table('partner_location as pl')
                    ->whereIn('pl.partner_id', DB::table('promotion')->where('promotion_active', 1)->distinct()->pluck('partner_id'))
                    ->count();
            }
            catch (\Exception $e) { return 0; }
        });
        
        // Active Merchants: recalculer le vrai compte unique depuis history (pas le SUM des daily stats)
        $realActiveMerchants = 0;
        $realActiveMerchantsComp = 0;
        try {
            $promoActivePartners = DB::table('promotion')->where('promotion_active', 1)->distinct()->pluck('partner_id');
            $locationPartners = DB::table('partner_location')->distinct()->pluck('partner_id');
            
            $amQuery = DB::table('history as h')
                ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
                ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
                ->where('h.time', '>=', $startBound)->where('h.time', '<', $endExclusive)
                ->whereNotNull('h.promotion_id')
                ->whereIn('p.partner_id', $promoActivePartners)
                ->whereIn('p.partner_id', $locationPartners);
            $this->applyOperatorJoinAndFilter($amQuery, $selectedOperator, 'ca');
            $realActiveMerchants = $amQuery->distinct('p.partner_id')->count('p.partner_id');

            $amCompQuery = DB::table('history as h')
                ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
                ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
                ->where('h.time', '>=', $compStartBound)->where('h.time', '<', $compEndExclusive)
                ->whereNotNull('h.promotion_id')
                ->whereIn('p.partner_id', $promoActivePartners)
                ->whereIn('p.partner_id', $locationPartners);
            $this->applyOperatorJoinAndFilter($amCompQuery, $selectedOperator, 'ca');
            $realActiveMerchantsComp = $amCompQuery->distinct('p.partner_id')->count('p.partner_id');
        } catch (\Exception $e) {
            Log::warning('Real active merchants calculation failed: ' . $e->getMessage());
            $realActiveMerchants = $current['active_merchants'];
            $realActiveMerchantsComp = $comparison['active_merchants'];
        }

        $activeMerchantRatio = $totalActivePartnersDB > 0 ? round(($realActiveMerchants / $totalActivePartnersDB) * 100, 1) : 0;
        $activeMerchantRatioComp = $totalActivePartnersDB > 0 ? round(($realActiveMerchantsComp / $totalActivePartnersDB) * 100, 1) : 0;
        $txPerMerchant = $realActiveMerchants > 0 ? round($current['transactions'] / $realActiveMerchants, 1) : 0;
        $txPerMerchantComp = $realActiveMerchantsComp > 0 ? round($comparison['transactions'] / $realActiveMerchantsComp, 1) : 0;
        
        $billingRateTimweData = $this->calculateTimweBillingRate($startBound, $endExclusive, $selectedOperator);
        $billingRateTimweCompData = $this->calculateTimweBillingRate($compStartBound, $compEndExclusive, $selectedOperator);
        $billingRateOoredooData = $this->calculateOoredooBillingRate($startBound, $endExclusive, $selectedOperator);
        $billingRateOoredooCompData = $this->calculateOoredooBillingRate($compStartBound, $compEndExclusive, $selectedOperator);

        // Calcul durée moyenne entre 2 transactions
        $avgInterTxDays = 0;
        $avgInterTxDaysComparison = 0;
        try {
            $avgInterTxDays = $this->calculateAvgInterTransactionDays($startBound, $endExclusive, $selectedOperator);
            $avgInterTxDaysComparison = $this->calculateAvgInterTransactionDays($compStartBound, $compEndExclusive, $selectedOperator);
        } catch (\Exception $e) {
            Log::warning('assembleKPIResult avgInterTransactionDays failed: ' . $e->getMessage());
        }

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
            "activeMerchants" => ["current" => $realActiveMerchants, "previous" => $realActiveMerchantsComp, "change" => $this->calculatePercentageChange($realActiveMerchants, $realActiveMerchantsComp)],
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
            "avgInterTransactionDays" => ["current" => $avgInterTxDays, "previous" => $avgInterTxDaysComparison, "change" => $this->calculatePercentageChange($avgInterTxDays, $avgInterTxDaysComparison)],
            "lostSubscriptions" => ["current" => $current['lost'], "previous" => $comparison['lost'], "change" => $this->calculatePercentageChange($current['lost'], $comparison['lost'])],
            "_source" => "materialized"
        ];
    }

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

    private function aggregateMaterialized(string $startDate, string $endDate, ?int $operatorId): ?array
    {
        $query = DB::table('dashboard_daily_stats')
            ->where(function ($q) use ($operatorId) {
                if ($operatorId === null) $q->whereNull('operator_id');
                else $q->where('operator_id', $operatorId);
            })
            ->whereBetween('stat_date', [$startDate, $endDate]);
        
        $agg = $query->selectRaw('
            SUM(activated_count) as activated, SUM(deactivated_count) as deactivated,
            SUM(transactions_count) as transactions, SUM(transacting_users) as transacting_users,
            SUM(cohort_transactions) as cohort_tx, SUM(cohort_transacting_users) as cohort_users,
            SUM(active_merchants) as active_merchants, SUM(lost_subscriptions) as lost
        ')->first();
        
        if (!$agg || $agg->activated === null) return null;
        
        return [
            'activated' => (int)$agg->activated, 'deactivated' => (int)$agg->deactivated,
            'transactions' => (int)$agg->transactions, 'transacting_users' => (int)$agg->transacting_users,
            'cohort_tx' => (int)$agg->cohort_tx, 'cohort_users' => (int)$agg->cohort_users,
            'active_merchants' => (int)$agg->active_merchants, 'lost' => (int)$agg->lost,
        ];
    }

    public function getKPIsOptimized(Carbon $startBound, Carbon $endExclusive, Carbon $compStartBound, Carbon $compEndExclusive, string $selectedOperator): array
    {
        $subscriptionQuery = DB::table('client_abonnement as ca')
            ->selectRaw(
                "COUNT(CASE WHEN ca.client_abonnement_creation >= ? AND ca.client_abonnement_creation < ? THEN 1 END) as activated_current,
                 COUNT(CASE WHEN ca.client_abonnement_creation >= ? AND ca.client_abonnement_creation < ? AND (ca.client_abonnement_expiration IS NULL OR ca.client_abonnement_expiration >= ?) THEN 1 END) as active_current,
                 COUNT(CASE WHEN ca.client_abonnement_expiration >= ? AND ca.client_abonnement_expiration < ? THEN 1 END) as deactivated_current,
                 COUNT(CASE WHEN ca.client_abonnement_creation >= ? AND ca.client_abonnement_creation < ? THEN 1 END) as activated_comparison,
                 COUNT(CASE WHEN ca.client_abonnement_creation >= ? AND ca.client_abonnement_creation < ? AND (ca.client_abonnement_expiration IS NULL OR ca.client_abonnement_expiration >= ?) THEN 1 END) as active_comparison,
                 COUNT(CASE WHEN ca.client_abonnement_expiration >= ? AND ca.client_abonnement_expiration < ? THEN 1 END) as deactivated_comparison",
                [$startBound, $endExclusive, $startBound, $endExclusive, $endExclusive, $startBound, $endExclusive, $compStartBound, $compEndExclusive, $compStartBound, $compEndExclusive, $compEndExclusive, $compStartBound, $compEndExclusive]
            );
        $this->applyOperatorJoinAndFilter($subscriptionQuery, $selectedOperator, 'ca');
        $subMetrics = $subscriptionQuery->first();
        
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
        
        // Cohort transactions
        $cohortTxQuery = DB::table('history as h')->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')->where('h.time', '>=', $startBound)->where('h.time', '<', $endExclusive)->where('ca.client_abonnement_creation', '>=', $startBound)->where('ca.client_abonnement_creation', '<', $endExclusive);
        $this->applyOperatorJoinAndFilter($cohortTxQuery, $selectedOperator, 'ca');
        $cohortTransactions = $cohortTxQuery->count();
        
        $cohortTxCompQuery = DB::table('history as h')->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')->where('h.time', '>=', $compStartBound)->where('h.time', '<', $compEndExclusive)->where('ca.client_abonnement_creation', '>=', $compStartBound)->where('ca.client_abonnement_creation', '<', $compEndExclusive);
        $this->applyOperatorJoinAndFilter($cohortTxCompQuery, $selectedOperator, 'ca');
        $cohortTransactionsComparison = $cohortTxCompQuery->count();
        
        // Cohort users
        $cohortUsersQuery = DB::table('history as h')->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')->where('h.time', '>=', $startBound)->where('h.time', '<', $endExclusive)->where('ca.client_abonnement_creation', '>=', $startBound)->where('ca.client_abonnement_creation', '<', $endExclusive);
        $this->applyOperatorJoinAndFilter($cohortUsersQuery, $selectedOperator, 'ca');
        $cohortTransactingUsers = $cohortUsersQuery->distinct('ca.client_id')->count('ca.client_id');
        
        $cohortUsersCompQuery = DB::table('history as h')->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')->where('h.time', '>=', $compStartBound)->where('h.time', '<', $compEndExclusive)->where('ca.client_abonnement_creation', '>=', $compStartBound)->where('ca.client_abonnement_creation', '<', $compEndExclusive);
        $this->applyOperatorJoinAndFilter($cohortUsersCompQuery, $selectedOperator, 'ca');
        $cohortTransactingUsersComparison = $cohortUsersCompQuery->distinct('ca.client_id')->count('ca.client_id');
        
        // Rates
        $retentionRate = $subMetrics->activated_current > 0 ? round(($subMetrics->active_current / $subMetrics->activated_current) * 100, 1) : 0;
        $retentionRateComparison = $subMetrics->activated_comparison > 0 ? round(($subMetrics->active_comparison / $subMetrics->activated_comparison) * 100, 1) : 0;
        $conversionRate = $subMetrics->active_current > 0 ? round(($txMetrics->users_current / $subMetrics->active_current) * 100, 1) : 0;
        $conversionRateComparison = $subMetrics->active_comparison > 0 ? round(($txMetrics->users_comparison / $subMetrics->active_comparison) * 100, 1) : 0;
        
        // Lost subscriptions
        $lostQuery = DB::table('client_abonnement as ca')->whereBetween('ca.client_abonnement_creation', [$startBound->toDateString(), $endExclusive->copy()->subDay()->toDateString()])->whereNotNull('ca.client_abonnement_expiration')->whereBetween('ca.client_abonnement_expiration', [$startBound->toDateString(), $endExclusive->copy()->subDay()->toDateString()]);
        $this->applyOperatorJoinAndFilter($lostQuery, $selectedOperator, 'ca');
        $lostSubscriptions = $lostQuery->count();
        
        $lostCompQuery = DB::table('client_abonnement as ca')->whereBetween('ca.client_abonnement_creation', [$compStartBound->toDateString(), $compEndExclusive->copy()->subDay()->toDateString()])->whereNotNull('ca.client_abonnement_expiration')->whereBetween('ca.client_abonnement_expiration', [$compStartBound->toDateString(), $compEndExclusive->copy()->subDay()->toDateString()]);
        $this->applyOperatorJoinAndFilter($lostCompQuery, $selectedOperator, 'ca');
        $lostSubscriptionsComparison = $lostCompQuery->count();
        
        $churnRate = $subMetrics->activated_current > 0 ? round(($lostSubscriptions / $subMetrics->activated_current) * 100, 1) : 0;
        $churnRateComparison = $subMetrics->activated_comparison > 0 ? round(($lostSubscriptionsComparison / $subMetrics->activated_comparison) * 100, 1) : 0;
        
        $merchantKPIs = $this->merchantService->calculateMerchantKPIs($startBound, $endExclusive, $compStartBound, $compEndExclusive, $selectedOperator, $txMetrics->transactions_current, $txMetrics->transactions_comparison);
        
        $transactionsPerUser = $txMetrics->users_current > 0 ? round($txMetrics->transactions_current / $txMetrics->users_current, 1) : 0;
        $transactionsPerUserComparison = $txMetrics->users_comparison > 0 ? round($txMetrics->transactions_comparison / $txMetrics->users_comparison, 1) : 0;
        $conversionRatePeriod = $subMetrics->active_current > 0 ? round(($txMetrics->users_current / $subMetrics->active_current) * 100, 2) : 0;
        $conversionRatePeriodComparison = $subMetrics->active_comparison > 0 ? round(($txMetrics->users_comparison / $subMetrics->active_comparison) * 100, 2) : 0;
        
        $billingRateTimweData = $this->calculateTimweBillingRate($startBound, $endExclusive, $selectedOperator);
        $billingRateTimweComparisonData = $this->calculateTimweBillingRate($compStartBound, $compEndExclusive, $selectedOperator);
        $billingRateOoredooData = $this->calculateOoredooBillingRate($startBound, $endExclusive, $selectedOperator);
        $billingRateOoredooComparisonData = $this->calculateOoredooBillingRate($compStartBound, $compEndExclusive, $selectedOperator);
        
        // Calcul durée moyenne entre 2 transactions (en jours)
        $avgInterTxDays = 0;
        $avgInterTxDaysComparison = 0;
        try {
            $avgInterTxDays = $this->calculateAvgInterTransactionDays($startBound, $endExclusive, $selectedOperator);
            $avgInterTxDaysComparison = $this->calculateAvgInterTransactionDays($compStartBound, $compEndExclusive, $selectedOperator);
        } catch (\Exception $e) {
            Log::warning('avgInterTransactionDays calculation failed: ' . $e->getMessage());
        }
        
        return [
            "activatedSubscriptions" => ["current" => $subMetrics->activated_current, "previous" => $subMetrics->activated_comparison, "change" => $this->calculatePercentageChange($subMetrics->activated_current, $subMetrics->activated_comparison)],
            "activeSubscriptions" => ["current" => $subMetrics->active_current, "previous" => $subMetrics->active_comparison, "change" => $this->calculatePercentageChange($subMetrics->active_current, $subMetrics->active_comparison)],
            "deactivatedSubscriptions" => ["current" => $subMetrics->deactivated_current, "previous" => $subMetrics->deactivated_comparison, "change" => $this->calculatePercentageChange($subMetrics->deactivated_current, $subMetrics->deactivated_comparison)],
            "periodDeactivated" => ["current" => $subMetrics->deactivated_current, "previous" => $subMetrics->deactivated_comparison, "change" => $this->calculatePercentageChange($subMetrics->deactivated_current, $subMetrics->deactivated_comparison)],
            "cohortDeactivated" => ["current" => $lostSubscriptions, "previous" => $lostSubscriptionsComparison, "change" => $this->calculatePercentageChange($lostSubscriptions, $lostSubscriptionsComparison)],
            "totalTransactions" => ["current" => $txMetrics->transactions_current, "previous" => $txMetrics->transactions_comparison, "change" => $this->calculatePercentageChange($txMetrics->transactions_current, $txMetrics->transactions_comparison)],
            "cohortTransactions" => ["current" => $cohortTransactions, "previous" => $cohortTransactionsComparison, "change" => $this->calculatePercentageChange($cohortTransactions, $cohortTransactionsComparison)],
            "transactingUsers" => ["current" => $txMetrics->users_current, "previous" => $txMetrics->users_comparison, "change" => $this->calculatePercentageChange($txMetrics->users_current, $txMetrics->users_comparison)],
            "cohortTransactingUsers" => ["current" => $cohortTransactingUsers, "previous" => $cohortTransactingUsersComparison, "change" => $this->calculatePercentageChange($cohortTransactingUsers, $cohortTransactingUsersComparison)],
            "retentionRate" => ["current" => $retentionRate, "previous" => $retentionRateComparison, "change" => $this->calculatePercentageChange($retentionRate, $retentionRateComparison)],
            "retentionRateTrue" => ["current" => max(0, 100 - $churnRate), "previous" => max(0, 100 - $churnRateComparison), "change" => $this->calculatePercentageChange(max(0, 100 - $churnRate), max(0, 100 - $churnRateComparison))],
            "conversionRate" => ["current" => $conversionRate, "previous" => $conversionRateComparison, "change" => $this->calculatePercentageChange($conversionRate, $conversionRateComparison)],
            "churnRate" => ["current" => $churnRate, "previous" => $churnRateComparison, "change" => $this->calculatePercentageChange($churnRate, $churnRateComparison)],
            "transactionsPerUser" => ["current" => $transactionsPerUser, "previous" => $transactionsPerUserComparison, "change" => $this->calculatePercentageChange($transactionsPerUser, $transactionsPerUserComparison)],
            "conversionRatePeriod" => ["current" => $conversionRatePeriod, "previous" => $conversionRatePeriodComparison, "change" => $this->calculatePercentageChange($conversionRatePeriod, $conversionRatePeriodComparison)],
            "activeMerchants" => $merchantKPIs['activeMerchants'],
            "activeMerchantRatio" => $merchantKPIs['activeMerchantRatio'],
            "totalPartners" => $merchantKPIs['totalPartners'],
            "totalActivePartnersDB" => $merchantKPIs['totalActivePartnersDB'],
            "totalLocationsActive" => $merchantKPIs['totalLocationsActive'],
            "totalMerchantsEverActive" => $merchantKPIs['totalMerchantsEverActive'],
            "allTransactionsPeriod" => $merchantKPIs['allTransactionsPeriod'],
            "transactionsPerMerchant" => $merchantKPIs['transactionsPerMerchant'],
            "billingRateTimwe" => ["current" => $billingRateTimweData['rate'], "previous" => $billingRateTimweComparisonData['rate'], "change" => $this->calculatePercentageChange($billingRateTimweData['rate'], $billingRateTimweComparisonData['rate'])],
            "totalTimweClients" => ["current" => $billingRateTimweData['total_clients'], "previous" => $billingRateTimweComparisonData['total_clients'], "change" => $this->calculatePercentageChange($billingRateTimweData['total_clients'], $billingRateTimweComparisonData['total_clients'])],
            "totalTimweBillings" => ["current" => $billingRateTimweData['total_billings'], "previous" => $billingRateTimweComparisonData['total_billings'], "change" => $this->calculatePercentageChange($billingRateTimweData['total_billings'], $billingRateTimweComparisonData['total_billings'])],
            "billingRateOoredoo" => ["current" => $billingRateOoredooData['rate'], "previous" => $billingRateOoredooComparisonData['rate'], "change" => $this->calculatePercentageChange($billingRateOoredooData['rate'], $billingRateOoredooComparisonData['rate'])],
            "totalOoredooClients" => ["current" => $billingRateOoredooData['total_clients'], "previous" => $billingRateOoredooComparisonData['total_clients'], "change" => $this->calculatePercentageChange($billingRateOoredooData['total_clients'], $billingRateOoredooComparisonData['total_clients'])],
            "totalOoreodooBillings" => ["current" => $billingRateOoredooData['total_billings'], "previous" => $billingRateOoredooComparisonData['total_billings'], "change" => $this->calculatePercentageChange($billingRateOoredooData['total_billings'], $billingRateOoredooComparisonData['total_billings'])],
            "avgInterTransactionDays" => ["current" => $avgInterTxDays, "previous" => $avgInterTxDaysComparison, "change" => $this->calculatePercentageChange($avgInterTxDays, $avgInterTxDaysComparison)],
            "lostSubscriptions" => ["current" => $lostSubscriptions, "previous" => $lostSubscriptionsComparison, "change" => $this->calculatePercentageChange($lostSubscriptions, $lostSubscriptionsComparison)]
        ];
    }

    public function calculateAvgInterTransactionDays(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): float
    {
        // Calcul: pour les utilisateurs ayant 2+ transactions dans la période,
        // calculer la durée moyenne en jours entre transactions consécutives
        $query = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->where('h.time', '>=', $startBound)
            ->where('h.time', '<', $endExclusive)
            ->whereNotNull('h.promotion_id');
        $this->applyOperatorJoinAndFilter($query, $selectedOperator, 'ca');
        
        // Récupérer les transactions groupées par client, ordonnées par date
        $transactions = $query
            ->select('ca.client_id', 'h.time')
            ->orderBy('ca.client_id')
            ->orderBy('h.time')
            ->get();
        
        if ($transactions->isEmpty()) return 0;
        
        $totalGapDays = 0;
        $gapCount = 0;
        $prevClientId = null;
        $prevTime = null;
        
        foreach ($transactions as $tx) {
            if ($tx->client_id === $prevClientId && $prevTime) {
                $current = strtotime($tx->time);
                $prev = strtotime($prevTime);
                $diffDays = ($current - $prev) / 86400;
                if ($diffDays > 0) {
                    $totalGapDays += $diffDays;
                    $gapCount++;
                }
            }
            $prevClientId = $tx->client_id;
            $prevTime = $tx->time;
        }
        
        return $gapCount > 0 ? round($totalGapDays / $gapCount, 1) : 0;
    }

    public function calculateTimweBillingRate(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        try {
            $endDate = $endExclusive->copy()->subDay();
            $stats = TimweDailyStat::getStatsForPeriod($startBound, $endDate);

            if ($stats->isNotEmpty()) {
                $lastDayStat = $stats->last();
                return [
                    'rate' => $lastDayStat->billing_rate,
                    'total_clients' => $lastDayStat->total_clients,
                    'billed_clients' => 0,
                    'total_billings' => $stats->sum('total_billings')
                ];
            }

            return ['rate' => 0.0, 'total_clients' => 0, 'billed_clients' => 0, 'total_billings' => 0];
        } catch (\Exception $e) {
            Log::error("Erreur calcul taux de facturation Timwe: " . $e->getMessage());
            return ['rate' => 0.0, 'total_clients' => 0, 'billed_clients' => 0, 'total_billings' => 0];
        }
    }

    public function calculateOoredooBillingRate(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        try {
            $endDate = $endExclusive->copy()->subDay();
            $stats = \App\Models\OoredooDailyStat::getStatsForPeriod($startBound, $endDate);

            if ($stats->isNotEmpty()) {
                $lastDayStat = $stats->last();
                return [
                    'rate' => $lastDayStat->billing_rate,
                    'total_clients' => $lastDayStat->total_clients,
                    'billed_clients' => 0,
                    'total_billings' => $stats->sum('total_billings')
                ];
            }

            return ['rate' => 0.0, 'total_clients' => 0, 'billed_clients' => 0, 'total_billings' => 0];
        } catch (\Exception $e) {
            Log::error("calculateOoredooBillingRate - Erreur: " . $e->getMessage());
            return ['rate' => 0.0, 'total_clients' => 0, 'billed_clients' => 0, 'total_billings' => 0];
        }
    }
}
