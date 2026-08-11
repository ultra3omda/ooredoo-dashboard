<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Traits\OperatorHelper;
use App\Traits\TransactionHelper;
use App\Traits\MaterializedCoverage;

class SubscriptionService
{
    use OperatorHelper, TransactionHelper, MaterializedCoverage;

    protected StatisticsService $statisticsService;

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    public function getSubscriptions(Carbon $startBound, Carbon $endExclusive, string $selectedOperator, ?Carbon $compStartBound = null, ?Carbon $compEndExclusive = null): array
    {
        $methodStart = microtime(true);
        $operatorId = $this->resolveOperatorIdForMaterialized($selectedOperator);
        $hasMaterialized = $this->hasMaterializedCoverage($startBound, $endExclusive, $operatorId);
        
        if ($hasMaterialized) {
            Log::info("getSubscriptionsData - MATERIALIZED path (operator_id={$operatorId})");
            return $this->getSubscriptionsDataMaterialized($startBound, $endExclusive, $selectedOperator, $operatorId, $compStartBound, $compEndExclusive, $methodStart);
        }
        
        Log::info("getSubscriptionsData - LIVE path");
        return $this->getSubscriptionsDataLive($startBound, $endExclusive, $selectedOperator, $compStartBound, $compEndExclusive, $methodStart);
    }

    public function getUserSubscriptions(int $clientId): array
    {
        try {
            $billingPpid = env('TIMWE_BILLING_PPID', '63980');
            $trial3DaysPpid = env('TIMWE_FREE_TRIAL_PPID_3_DAYS', '63981');
            $trial30DaysPpid = env('TIMWE_FREE_TRIAL_PPID_30_DAYS', '63982');
            
            $transactionsSubquery = DB::table('transactions_history')
                ->select(['result', 'created_at'])
                ->where('client_id', $clientId)
                ->where(function($q) { $q->where('status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')->orWhere('status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%'); })
                ->orderBy('created_at', 'asc');
            
            $subscriptions = DB::table('client_abonnement as ca')
                ->leftJoin('client as c', 'ca.client_id', '=', 'c.client_id')
                ->leftJoin('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
                ->leftJoin('abonnement_tarifs as at', 'ca.tarif_id', '=', 'at.abonnement_tarifs_id')
                ->leftJoin('abonnement as a', 'at.abonnement_id', '=', 'a.abonnement_id')
                ->select([
                    'ca.client_abonnement_id', 'ca.client_id', 'c.client_prenom as first_name', 'c.client_nom as last_name',
                    'c.client_telephone as phone', 'cpm.country_payments_methods_name as operator',
                    'ca.client_abonnement_creation as activation_date', 'ca.client_abonnement_expiration as end_date',
                    'ca.subscription_type', 'a.abonnement_nom as subscription_name', 'at.abonnement_tarifs_prix as price',
                    DB::raw("CASE 
                        WHEN LOWER(TRIM(cpm.country_payments_methods_name)) LIKE '%timwe%' THEN
                            CASE WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) = 3 THEN 'Trial'
                                 WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) BETWEEN 20 AND 40 THEN 'Mensuel'
                                 ELSE 'Mensuel' END
                        WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) = 1 THEN 'Journalier'
                        WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) BETWEEN 20 AND 40 THEN 'Mensuel'
                        WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) >= 330 THEN 'Annuel'
                        ELSE 'Autre' END as plan"),
                    DB::raw("CASE WHEN ca.client_abonnement_expiration IS NULL OR ca.client_abonnement_expiration >= NOW() THEN 'Actif' ELSE 'Expiré' END as status")
                ])
                ->where('ca.client_id', $clientId)
                ->orderByDesc('ca.client_abonnement_creation')
                ->get();
            
            $transactions = $transactionsSubquery->get();
            
            $subscriptionsArray = $subscriptions->map(function($subscription) use ($transactions, $billingPpid, $trial3DaysPpid, $trial30DaysPpid) {
                $subArray = (array)$subscription;
                $operator = $subArray['operator'] ?? '';
                $activationDate = $subArray['activation_date'] ?? null;
                $endDate = $subArray['end_date'] ?? null;
                
                if (stripos($operator, 'timwe') !== false && $transactions->isNotEmpty() && $activationDate && $endDate) {
                    $duration = Carbon::parse($activationDate)->diffInDays(Carbon::parse($endDate));
                    $relevantTransaction = $transactions->sortBy(fn($t) => abs(Carbon::parse($t->created_at)->diffInSeconds(Carbon::parse($activationDate))))->first();
                    
                    if ($relevantTransaction && $relevantTransaction->result) {
                        $ppid = $this->extractPricepointId($relevantTransaction->result);
                        
                        // Check ppid FIRST (trial detection has priority over duration)
                        if ($ppid === $trial3DaysPpid || $ppid === $trial30DaysPpid) { $subArray['plan'] = 'Trial'; $subArray['price'] = 0; }
                        elseif ($duration === 3) { $subArray['plan'] = 'Trial'; $subArray['price'] = 0; }
                        elseif ($ppid === $billingPpid) { $subArray['plan'] = 'Mensuel'; }
                        elseif ($duration >= 20 && $duration <= 40) { $subArray['plan'] = 'Mensuel'; }
                        else { $subArray['plan'] = 'Trial'; $subArray['price'] = 0; }
                    } else {
                        // No matching transaction - check if it's a first subscription (trial)
                        if ($duration === 3) { $subArray['plan'] = 'Trial'; $subArray['price'] = 0; }
                        elseif ($duration >= 28 && $duration <= 31) {
                            // 30-day sub without billing transaction = likely free trial
                            $hasOlderSub = $subscriptions->contains(fn($s) => 
                                $s->client_abonnement_id !== $subscription->client_abonnement_id 
                                && $s->activation_date < $activationDate
                            );
                            if (!$hasOlderSub) { $subArray['plan'] = 'Trial'; $subArray['price'] = 0; }
                            else { $subArray['plan'] = 'Mensuel'; }
                        }
                    }
                }
                
                if (isset($subArray['plan']) && $subArray['plan'] === 'Trial') { $subArray['price'] = 0; }
                return $subArray;
            })->toArray();
            
            return ['success' => true, 'client_id' => $clientId, 'total_subscriptions' => count($subscriptionsArray), 'subscriptions' => $subscriptionsArray];
        } catch (\Exception $e) {
            Log::error("Erreur getUserSubscriptions({$clientId}): " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'client_id' => $clientId, 'subscriptions' => []];
        }
    }

    private function resolveOperatorIdForMaterialized(string $selectedOperator): ?int
    {
        if ($selectedOperator === 'ALL' || empty($selectedOperator)) return null;
        return $this->getOperatorId($selectedOperator);
    }

    private function hasMaterializedCoverage(Carbon $startBound, Carbon $endExclusive, ?int $operatorId): bool
    {
        $scoped = DB::table('subscription_daily_stats')
            ->where('stat_date', '>=', $startBound->toDateString())
            ->where('stat_date', '<', $endExclusive->toDateString())
            ->where(function ($q) use ($operatorId) {
                if ($operatorId === null) $q->whereNull('operator_id');
                else $q->where('operator_id', $operatorId);
            });

        return $this->hasFreshMaterializedCoverage($scoped, $startBound, $endExclusive);
    }

    private function getSubscriptionsDataMaterialized(Carbon $startBound, Carbon $endExclusive, string $selectedOperator, ?int $operatorId, ?Carbon $compStartBound, ?Carbon $compEndExclusive, float $methodStart): array
    {
        $periodDays = $startBound->diffInDays($endExclusive);
        $granularity = $periodDays > 365 ? 'month' : 'day';
        $dateExpr = $granularity === 'month' ? "DATE_FORMAT(stat_date, '%Y-%m-01')" : 'stat_date';
        
        $matRows = DB::table('subscription_daily_stats')
            ->where('stat_date', '>=', $startBound->toDateString())
            ->where('stat_date', '<', $endExclusive->toDateString())
            ->where(function ($q) use ($operatorId) {
                if ($operatorId === null) $q->whereNull('operator_id');
                else $q->where('operator_id', $operatorId);
            })
            ->select(DB::raw("{$dateExpr} as period_date"), DB::raw('SUM(activated_count) as activations'), DB::raw('SUM(active_snapshot) as active_snap'))
            ->groupBy(DB::raw($dateExpr))
            ->orderBy('period_date')
            ->get()
            ->keyBy('period_date');

        $dailyActivations = [];
        $endDate = $endExclusive->copy()->subDay();
        if ($granularity === 'month') {
            $cursor = $startBound->copy()->firstOfMonth();
            while ($cursor->lte($endDate)) {
                $key = $cursor->toDateString();
                $row = $matRows->get($key);
                $act = $row ? (int)$row->activations : 0;
                $dailyActivations[] = ['date' => $key, 'activations' => $act, 'active' => round($act * 0.95)];
                $cursor->addMonth();
            }
        } else {
            $cursor = $startBound->copy();
            while ($cursor->lte($endDate)) {
                $key = $cursor->toDateString();
                $row = $matRows->get($key);
                $act = $row ? (int)$row->activations : 0;
                $dailyActivations[] = ['date' => $key, 'activations' => $act, 'active' => round($act * 0.95)];
                $cursor->addDay();
            }
        }

        $currentAgg = $this->getMaterializedAggregates($startBound, $endExclusive, $operatorId);
        $compAgg = ($compStartBound && $compEndExclusive) ? $this->getMaterializedAggregates($compStartBound, $compEndExclusive, $operatorId) : null;

        $activationsByChannel = [];
        foreach (['cb', 'recharge', 'phone_balance', 'other'] as $ch) {
            $cur = $currentAgg["channel_{$ch}"] ?? 0;
            $prev = $compAgg ? ($compAgg["channel_{$ch}"] ?? 0) : 0;
            $activationsByChannel[$ch] = ["current" => $cur, "previous" => $prev, "change" => $this->calculatePercentageChange($cur, $prev)];
        }

        $planDistribution = [];
        foreach (['daily', 'monthly', 'annual', 'other'] as $pl) {
            $cur = $currentAgg["plan_{$pl}"] ?? 0;
            $prev = $compAgg ? ($compAgg["plan_{$pl}"] ?? 0) : 0;
            $planDistribution[$pl] = ["current" => $cur, "previous" => $prev, "change" => $this->calculatePercentageChange($cur, $prev)];
        }

        $renewalCurrent = ($currentAgg['expired_count'] > 0) ? round(($currentAgg['renewed_count'] / $currentAgg['expired_count']) * 100, 1) : 0;
        $renewalPrevious = ($compAgg && $compAgg['expired_count'] > 0) ? round(($compAgg['renewed_count'] / $compAgg['expired_count']) * 100, 1) : 0;
        $renewalRate = ["current" => $renewalCurrent, "previous" => $renewalPrevious, "change" => $this->calculatePercentageChange($renewalCurrent, $renewalPrevious)];

        $lifespanCurrent = ($currentAgg['lifespan_sub_count'] > 0) ? round($currentAgg['total_lifespan_days'] / $currentAgg['lifespan_sub_count'], 1) : 0;
        $lifespanPrevious = ($compAgg && $compAgg['lifespan_sub_count'] > 0) ? round($compAgg['total_lifespan_days'] / $compAgg['lifespan_sub_count'], 1) : 0;
        $averageLifespan = ["current" => $lifespanCurrent, "previous" => $lifespanPrevious, "change" => $this->calculatePercentageChange($lifespanCurrent, $lifespanPrevious)];

        $retentionTrend = [];
        try {
            $retentionTrend = Cache::remember('ret_trend:' . md5("{$startBound}:{$endExclusive}:{$selectedOperator}"), 1800, fn() => $this->calculateRetentionTrendMaterialized($startBound, $endExclusive, $selectedOperator, $operatorId));
        } catch (\Exception $e) {}

        $quarterlyActiveLocations = [];
        try {
            $quarterlyActiveLocations = Cache::remember('qloc:' . md5($endExclusive->copy()->subDay()->toDateString()), 3600, fn() => $this->calculateQuarterlyActiveLocations($endExclusive->copy()->subDay()->toDateString()));
        } catch (\Exception $e) {}

        $subscriptionDetails = [];
        try {
            $subscriptionDetails = Cache::remember('sub_det:' . md5("{$startBound}:{$endExclusive}:{$selectedOperator}"), 1800, fn() => $this->getSubscriptionDetails($startBound, $endExclusive, $selectedOperator));
        } catch (\Exception $e) {}

        $cohorts = [];
        try {
            $cohorts = Cache::remember('cohorts:' . md5("{$startBound}:{$endExclusive}:{$selectedOperator}"), 1800, fn() => $this->calculateCohorts($startBound->format('Y-m-d'), $endExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator));
        } catch (\Exception $e) {}

        $reactivationCurrent = 0; $reactivationPrevious = 0;
        try {
            $reactivationCurrent = $this->calculateReactivationRate($startBound->format('Y-m-d'), $endExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator);
            if ($compStartBound && $compEndExclusive) {
                $reactivationPrevious = $this->calculateReactivationRate($compStartBound->format('Y-m-d'), $compEndExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator);
            }
        } catch (\Exception $e) {}
        $reactivationRate = ["current" => $reactivationCurrent, "previous" => $reactivationPrevious, "change" => $this->calculatePercentageChange($reactivationCurrent, $reactivationPrevious)];

        $dailyStatistics = []; $dailyStatisticsComparison = [];
        try { $dailyStatistics = $this->statisticsService->getDailyStatistics($startBound, $endExclusive, $selectedOperator); } catch (\Exception $e) {}
        if ($compStartBound && $compEndExclusive) {
            try { $dailyStatisticsComparison = $this->statisticsService->getDailyStatistics($compStartBound, $compEndExclusive, $selectedOperator); } catch (\Exception $e) {}
        }

        return [
            "daily_activations" => $dailyActivations,
            "retention_trend" => $retentionTrend,
            "quarterly_active_locations" => $quarterlyActiveLocations,
            "details" => $subscriptionDetails,
            "daily_statistics" => $dailyStatistics,
            "daily_statistics_comparison" => $dailyStatisticsComparison,
            "timwe_monthly_stats" => $this->statisticsService->groupTimweStatsByMonth($dailyStatistics),
            "timwe_monthly_stats_comparison" => $this->statisticsService->groupTimweStatsByMonth($dailyStatisticsComparison),
            "timwe_transactions_by_user" => [],
            "activations_by_channel" => $activationsByChannel,
            "plan_distribution" => $planDistribution,
            "cohorts" => $cohorts,
            "renewal_rate" => $renewalRate,
            "average_lifespan" => $averageLifespan,
            "reactivation_rate" => $reactivationRate
        ];
    }

    private function getMaterializedAggregates(Carbon $startBound, Carbon $endExclusive, ?int $operatorId): array
    {
        $row = DB::table('subscription_daily_stats')
            ->where('stat_date', '>=', $startBound->toDateString())
            ->where('stat_date', '<', $endExclusive->toDateString())
            ->where(function ($q) use ($operatorId) {
                if ($operatorId === null) $q->whereNull('operator_id');
                else $q->where('operator_id', $operatorId);
            })
            ->selectRaw('SUM(channel_cb) as channel_cb, SUM(channel_recharge) as channel_recharge, SUM(channel_phone_balance) as channel_phone_balance, SUM(channel_other) as channel_other, SUM(plan_daily) as plan_daily, SUM(plan_monthly) as plan_monthly, SUM(plan_annual) as plan_annual, SUM(plan_other) as plan_other, SUM(expired_count) as expired_count, SUM(renewed_count) as renewed_count, SUM(total_lifespan_days) as total_lifespan_days, SUM(lifespan_sub_count) as lifespan_sub_count')
            ->first();

        return [
            'channel_cb' => (int)($row->channel_cb ?? 0), 'channel_recharge' => (int)($row->channel_recharge ?? 0),
            'channel_phone_balance' => (int)($row->channel_phone_balance ?? 0), 'channel_other' => (int)($row->channel_other ?? 0),
            'plan_daily' => (int)($row->plan_daily ?? 0), 'plan_monthly' => (int)($row->plan_monthly ?? 0),
            'plan_annual' => (int)($row->plan_annual ?? 0), 'plan_other' => (int)($row->plan_other ?? 0),
            'expired_count' => (int)($row->expired_count ?? 0), 'renewed_count' => (int)($row->renewed_count ?? 0),
            'total_lifespan_days' => (int)($row->total_lifespan_days ?? 0), 'lifespan_sub_count' => (int)($row->lifespan_sub_count ?? 0),
        ];
    }

    private function getSubscriptionsDataLive(Carbon $startBound, Carbon $endExclusive, string $selectedOperator, ?Carbon $compStartBound, ?Carbon $compEndExclusive, float $methodStart): array
    {
        $maxTimeSec = 90;
        try { DB::statement("SET SESSION max_execution_time=30000"); } catch (\Exception $e) {}
        
        $periodDays = $startBound->diffInDays($endExclusive);
        $granularity = $periodDays > 365 ? 'month' : 'day';
        $caDateExpr = $granularity === 'month' ? "DATE_FORMAT(client_abonnement_creation, '%Y-%m-01')" : "DATE(client_abonnement_creation)";
        
        if ($selectedOperator === 'ALL' || empty($selectedOperator)) {
            $activationsQuery = DB::table("client_abonnement as ca")->select(DB::raw("$caDateExpr as date"), DB::raw("COUNT(*) as activations"))->where("ca.client_abonnement_creation", ">=", $startBound)->where("ca.client_abonnement_creation", "<", $endExclusive);
        } else {
            $activationsQuery = DB::table("client_abonnement as ca")->join("country_payments_methods as cpm", "ca.country_payments_methods_id", "=", "cpm.country_payments_methods_id")->select(DB::raw("$caDateExpr as date"), DB::raw("COUNT(*) as activations"))->where("ca.client_abonnement_creation", ">=", $startBound)->where("ca.client_abonnement_creation", "<", $endExclusive);
            $this->applyOperatorFilter($activationsQuery, $selectedOperator);
        }
        
        $activationsRaw = $activationsQuery->groupBy(DB::raw($caDateExpr))->orderBy("date")->get()->keyBy('date')->toArray();
        
        $endDate = $endExclusive->copy()->subDay();
        $dailyActivations = [];
        
        if ($granularity === 'month') {
            $cursor = $startBound->copy()->firstOfMonth();
            while ($cursor->lte($endDate)) {
                $key = $cursor->copy()->firstOfMonth()->toDateString();
                $activations = isset($activationsRaw[$key]) ? (int)$activationsRaw[$key]->activations : 0;
                $dailyActivations[] = ['date' => $key, 'activations' => $activations, 'active' => round($activations * 0.95)];
                $cursor->addMonth();
            }
        } else {
            $cursor = $startBound->copy();
            while ($cursor->lte($endDate)) {
                $dateStr = $cursor->toDateString();
                $activations = isset($activationsRaw[$dateStr]) ? (int)$activationsRaw[$dateStr]->activations : 0;
                $dailyActivations[] = ['date' => $dateStr, 'activations' => $activations, 'active' => round($activations * 0.95)];
                $cursor->addDay();
            }
        }
        
        $retentionTrend = [];
        try { $retentionTrend = Cache::remember('ret_trend:' . md5("{$startBound}:{$endExclusive}:{$selectedOperator}"), 1800, fn() => $this->calculateRetentionTrendOptimized($startBound, $endExclusive, $selectedOperator)); } catch (\Exception $e) {}
        
        $quarterlyActiveLocations = [];
        try { $quarterlyActiveLocations = Cache::remember('qloc:' . md5($endExclusive->copy()->subDay()->toDateString()), 3600, fn() => $this->calculateQuarterlyActiveLocations($endExclusive->copy()->subDay()->toDateString())); } catch (\Exception $e) {}
        
        $elapsed = microtime(true) - $methodStart;
        $timeExceeded = ($elapsed > $maxTimeSec);
        
        $subscriptionDetails = [];
        if (!$timeExceeded) { try { $subscriptionDetails = Cache::remember('sub_det:' . md5("{$startBound}:{$endExclusive}:{$selectedOperator}"), 1800, fn() => $this->getSubscriptionDetails($startBound, $endExclusive, $selectedOperator)); } catch (\Exception $e) {} }
        
        $defaultChannel = ['cb' => 0, 'recharge' => 0, 'phone_balance' => 0, 'other' => 0];
        $defaultPlan = ['daily' => 0, 'monthly' => 0, 'annual' => 0, 'other' => 0];
        $defaultComparison = ["current" => 0, "previous" => 0, "change" => 0.0];
        
        $activationsCurrent = $defaultChannel; $activationsPrevious = $defaultChannel;
        if (!$timeExceeded) {
            try {
                $activationsCurrent = Cache::remember('actbychan:' . md5("{$startBound}:{$endExclusive}:{$selectedOperator}"), 1800, fn() => $this->calculateActivationsByPaymentMethod($startBound, $endExclusive, $selectedOperator));
                $activationsPrevious = ($compStartBound && $compEndExclusive) ? Cache::remember('actbychan:' . md5("{$compStartBound}:{$compEndExclusive}:{$selectedOperator}"), 1800, fn() => $this->calculateActivationsByPaymentMethod($compStartBound, $compEndExclusive, $selectedOperator)) : $defaultChannel;
            } catch (\Exception $e) {}
            $elapsed = microtime(true) - $methodStart; $timeExceeded = ($elapsed > $maxTimeSec);
        }
        
        $activationsByChannel = [];
        foreach (['cb', 'recharge', 'phone_balance', 'other'] as $ch) {
            $activationsByChannel[$ch] = ["current" => $activationsCurrent[$ch] ?? 0, "previous" => $activationsPrevious[$ch] ?? 0, "change" => $this->calculatePercentageChange($activationsCurrent[$ch] ?? 0, $activationsPrevious[$ch] ?? 0)];
        }
        
        $plansCurrent = $defaultPlan; $plansPrevious = $defaultPlan;
        if (!$timeExceeded) {
            try {
                $plansCurrent = Cache::remember('plandist:' . md5("{$startBound}:{$endExclusive}:{$selectedOperator}"), 1800, fn() => $this->calculatePlanDistribution($startBound, $endExclusive, $selectedOperator));
                $plansPrevious = ($compStartBound && $compEndExclusive) ? Cache::remember('plandist:' . md5("{$compStartBound}:{$compEndExclusive}:{$selectedOperator}"), 1800, fn() => $this->calculatePlanDistribution($compStartBound, $compEndExclusive, $selectedOperator)) : $defaultPlan;
            } catch (\Exception $e) {}
            $elapsed = microtime(true) - $methodStart; $timeExceeded = ($elapsed > $maxTimeSec);
        }
        
        $planDistribution = [];
        foreach (['daily', 'monthly', 'annual', 'other'] as $pl) {
            $planDistribution[$pl] = ["current" => $plansCurrent[$pl] ?? 0, "previous" => $plansPrevious[$pl] ?? 0, "change" => $this->calculatePercentageChange($plansCurrent[$pl] ?? 0, $plansPrevious[$pl] ?? 0)];
        }
        
        $cohorts = [];
        if (!$timeExceeded) { try { $cohorts = Cache::remember('cohorts:' . md5("{$startBound}:{$endExclusive}:{$selectedOperator}"), 1800, fn() => $this->calculateCohorts($startBound->format('Y-m-d'), $endExclusive->copy()->subDay()->format('Y-m-d'), $selectedOperator)); } catch (\Exception $e) {} $elapsed = microtime(true) - $methodStart; $timeExceeded = ($elapsed > $maxTimeSec); }
        
        $renewalRate = $defaultComparison; $averageLifespan = $defaultComparison; $reactivationRate = $defaultComparison;
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
        
        $dailyStatistics = []; $dailyStatisticsComparison = [];
        if (!$timeExceeded) {
            try { $dailyStatistics = $this->statisticsService->getDailyStatistics($startBound, $endExclusive, $selectedOperator); } catch (\Exception $e) {}
            if ($compStartBound && $compEndExclusive) {
                try { $dailyStatisticsComparison = $this->statisticsService->getDailyStatistics($compStartBound, $compEndExclusive, $selectedOperator); } catch (\Exception $e) {}
            }
        }
        
        try { DB::statement("SET SESSION max_execution_time=0"); } catch (\Exception $e) {}
        
        return [
            "daily_activations" => $dailyActivations, "retention_trend" => $retentionTrend,
            "quarterly_active_locations" => $quarterlyActiveLocations, "details" => $subscriptionDetails,
            "daily_statistics" => $dailyStatistics, "daily_statistics_comparison" => $dailyStatisticsComparison,
            "timwe_monthly_stats" => $this->statisticsService->groupTimweStatsByMonth($dailyStatistics),
            "timwe_monthly_stats_comparison" => $this->statisticsService->groupTimweStatsByMonth($dailyStatisticsComparison),
            "timwe_transactions_by_user" => [],
            "activations_by_channel" => $activationsByChannel, "plan_distribution" => $planDistribution,
            "cohorts" => $cohorts, "renewal_rate" => $renewalRate,
            "average_lifespan" => $averageLifespan, "reactivation_rate" => $reactivationRate
        ];
    }

    private function calculateRetentionTrendMaterialized(Carbon $startBound, Carbon $endExclusive, string $selectedOperator, ?int $operatorId): array
    {
        try {
            $periodDays = $startBound->diffInDays($endExclusive);
            $intervalDays = max(1, intval($periodDays / 30));
            $endDateStr = $endExclusive->copy()->subDay()->toDateString();
            
            $matData = DB::table('subscription_daily_stats')->where('stat_date', '>=', $startBound->toDateString())->where('stat_date', '<', $endExclusive->toDateString())->where(function ($q) use ($operatorId) { if ($operatorId === null) $q->whereNull('operator_id'); else $q->where('operator_id', $operatorId); })->pluck('activated_count', 'stat_date')->toArray();
            
            $sampleDates = [];
            $cursor = $startBound->copy();
            $endDate = $endExclusive->copy()->subDay();
            while ($cursor->lte($endDate)) { $sampleDates[] = $cursor->toDateString(); $cursor->addDays($intervalDays); }
            if (empty($sampleDates)) return [];
            
            $opFilter = ($operatorId !== null) ? " AND ca.country_payments_methods_id = {$operatorId}" : '';
            $dateList = "'" . implode("','", $sampleDates) . "'";
            $activeResults = DB::select("SELECT DATE(ca.client_abonnement_creation) as cdate, COUNT(*) as active_count FROM client_abonnement ca WHERE DATE(ca.client_abonnement_creation) IN ({$dateList}) AND (ca.client_abonnement_expiration IS NULL OR ca.client_abonnement_expiration > ?) {$opFilter} GROUP BY DATE(ca.client_abonnement_creation)", [$endDateStr]);
            $activeMap = [];
            foreach ($activeResults as $r) $activeMap[$r->cdate] = (int)$r->active_count;
            
            $trend = [];
            foreach ($sampleDates as $d) {
                $activated = $matData[$d] ?? 0;
                $active = $activeMap[$d] ?? 0;
                $rate = ($activated > 0) ? round(($active / $activated) * 100, 1) : 100.0;
                $trend[] = ['date' => $d, 'rate' => $rate, 'value' => $rate];
            }
            return $trend;
        } catch (\Exception $e) {
            return $this->calculateRetentionTrendOptimized($startBound, $endExclusive, $selectedOperator);
        }
    }

    private function calculateRetentionTrendOptimized(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        try {
            $periodDays = $startBound->diffInDays($endExclusive);
            $intervalDays = max(1, intval($periodDays / 30));
            $endDateStr = $endExclusive->toDateString();
            
            if ($selectedOperator === 'ALL' || empty($selectedOperator)) {
                $activationsQuery = DB::table('client_abonnement as ca')->selectRaw("DATE(ca.client_abonnement_creation) as date, COUNT(*) as activated, SUM(CASE WHEN ca.client_abonnement_expiration IS NULL OR ca.client_abonnement_expiration > ? THEN 1 ELSE 0 END) as active", [$endDateStr])->where('ca.client_abonnement_creation', '>=', $startBound)->where('ca.client_abonnement_creation', '<', $endExclusive);
            } else {
                $activationsQuery = DB::table('client_abonnement as ca')->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')->selectRaw("DATE(ca.client_abonnement_creation) as date, COUNT(*) as activated, SUM(CASE WHEN ca.client_abonnement_expiration IS NULL OR ca.client_abonnement_expiration > ? THEN 1 ELSE 0 END) as active", [$endDateStr])->where('ca.client_abonnement_creation', '>=', $startBound)->where('ca.client_abonnement_creation', '<', $endExclusive);
                $this->applyOperatorFilter($activationsQuery, $selectedOperator);
            }
            
            $results = $activationsQuery->groupBy(DB::raw("DATE(ca.client_abonnement_creation)"))->orderBy('date')->get()->keyBy('date');
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
            return $trend;
        } catch (\Exception $e) { return []; }
    }

    private function calculateQuarterlyActiveLocations(string $endDate): array
    {
        try {
            $quarterlyData = DB::table('partner_location')
                ->selectRaw('YEAR(partner_location.created_at) as yr, QUARTER(partner_location.created_at) as q, COUNT(DISTINCT partner_location.partner_location_id) as new_count')
                ->join('partner', 'partner_location.partner_id', '=', 'partner.partner_id')
                ->whereNotNull('partner_location.created_at')
                ->groupByRaw('YEAR(partner_location.created_at), QUARTER(partner_location.created_at)')
                ->orderByRaw('YEAR(partner_location.created_at), QUARTER(partner_location.created_at)')
                ->get();
            
            $result = []; $cumulative = 0;
            foreach ($quarterlyData as $row) {
                $cumulative += $row->new_count;
                $result[] = ['quarter' => $row->yr . '-Q' . $row->q, 'locations' => $cumulative, 'new' => (int)$row->new_count];
            }
            return $result;
        } catch (\Exception $e) { return []; }
    }

    /**
     * Construit la requête des détails d'abonnements (sans LIMIT ni pagination).
     * Partagée par l'affichage du tableau (plafonné) et par l'export CSV (intégral).
     */
    private function buildSubscriptionDetailsQuery(Carbon $startBound, Carbon $endExclusive, string $selectedOperator)
    {
        $query = DB::table('client_abonnement as ca')->leftJoin('client as c', 'ca.client_id', '=', 'c.client_id')
                ->select(['ca.client_id', 'c.client_prenom as first_name', 'c.client_nom as last_name', 'c.client_telephone as phone', 'ca.client_abonnement_creation as activation_date', 'ca.client_abonnement_expiration as end_date',
                    DB::raw("CASE WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) <= 3 THEN 'Trial' WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) = 1 THEN 'Journalier' WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) BETWEEN 20 AND 40 THEN 'Mensuel' WHEN DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) >= 330 THEN 'Annuel' ELSE 'Autre' END as plan")])
                ->where(function($q) use ($startBound, $endExclusive) {
                    $q->where(function($subQ) use ($startBound, $endExclusive) { $subQ->where('ca.client_abonnement_creation', '>=', $startBound)->where('ca.client_abonnement_creation', '<', $endExclusive); })
                      ->orWhere(function($subQ) use ($endExclusive) { $subQ->whereNull('ca.client_abonnement_expiration')->orWhere('ca.client_abonnement_expiration', '>=', $endExclusive); });
                });

        if ($selectedOperator !== 'ALL' && !empty($selectedOperator)) {
            $query->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id');
            $query->addSelect('cpm.country_payments_methods_name as operator');
            $this->applyOperatorFilter($query, $selectedOperator);
        } else {
            $query->addSelect(DB::raw("(SELECT cpm2.country_payments_methods_name FROM country_payments_methods cpm2 WHERE cpm2.country_payments_methods_id = ca.country_payments_methods_id LIMIT 1) as operator"));
        }

        return $query->orderByDesc('ca.client_abonnement_creation');
    }

    private function getSubscriptionDetails(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        try {
            $periodDays = $startBound->diffInDays($endExclusive);
            $limit = min(1000, max(100, intval($periodDays * 10)));

            $results = $this->buildSubscriptionDetailsQuery($startBound, $endExclusive, $selectedOperator)->limit($limit)->get();
            return ['data' => $results->map(fn($item) => (array)$item)->toArray(), 'meta' => ['total_count' => -1, 'displayed_count' => $results->count(), 'limit' => $limit, 'period' => $startBound->toDateString() . ' - ' . $endExclusive->copy()->subDay()->toDateString()]];
        } catch (\Exception $e) { return ['data' => [], 'meta' => ['total_count' => 0, 'error' => $e->getMessage()]]; }
    }

    /**
     * Parcourt l'INTÉGRALITÉ des abonnements de la période, sans plafond.
     * Utilise un curseur : les lignes sont consommées une à une, la mémoire PHP
     * ne croît pas avec la taille du résultat, ce qui permet d'exporter des
     * centaines de milliers de lignes.
     *
     * @return \Generator<object>
     */
    public function streamSubscriptionDetails(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): \Generator
    {
        yield from $this->buildSubscriptionDetailsQuery($startBound, $endExclusive, $selectedOperator)->cursor();
    }

    private function calculateActivationsByPaymentMethod(Carbon $startBound, Carbon $endExclusive, string $operatorFilter): array
    {
        try {
            $query = DB::table('client_abonnement as ca')->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')->where('ca.client_abonnement_creation', '>=', $startBound)->where('ca.client_abonnement_creation', '<', $endExclusive);
            $this->applyOperatorFilter($query, $operatorFilter);
            $rows = $query->select('cpm.country_payments_methods_name as cpm_name', DB::raw('COUNT(*) as cnt'))->groupBy('cpm.country_payments_methods_name')->get();
            
            $totals = ['cb' => 0, 'recharge' => 0, 'phone_balance' => 0, 'other' => 0];
            foreach ($rows as $row) {
                $name = mb_strtolower($row->cpm_name);
                if (str_contains($name, 'banc') || str_contains($name, 'cb')) $totals['cb'] += (int)$row->cnt;
                elseif (str_contains($name, 'cadeau') || str_contains($name, 'voucher') || str_contains($name, 'recharg')) $totals['recharge'] += (int)$row->cnt;
                elseif (str_contains($name, 'solde') || str_contains($name, 'téléphon') || str_contains($name, 'teleph') || str_contains($name, 'orange') || str_contains($name, " tt") || str_contains($name, 'timwe')) $totals['phone_balance'] += (int)$row->cnt;
                else $totals['other'] += (int)$row->cnt;
            }
            return $totals;
        } catch (\Exception $e) { return ['cb' => 0, 'recharge' => 0, 'phone_balance' => 0, 'other' => 0]; }
    }

    private function calculatePlanDistribution(Carbon $startBound, Carbon $endExclusive, string $operatorFilter): array
    {
        try {
            $query = DB::table('client_abonnement as ca')->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')->where('ca.client_abonnement_creation', '>=', $startBound)->where('ca.client_abonnement_creation', '<', $endExclusive);
            $this->applyOperatorFilter($query, $operatorFilter);
            $subs = $query->select('ca.client_abonnement_creation', 'ca.client_abonnement_expiration', 'cpm.country_payments_methods_name as cpm_name')->get();
            
            $totals = ['daily' => 0, 'monthly' => 0, 'annual' => 0, 'other' => 0];
            foreach ($subs as $s) {
                $name = mb_strtolower($s->cpm_name ?? '');
                if (str_contains($name, 'timwe')) { $totals['monthly']++; continue; }
                if (str_contains($name, 'solde') || str_contains($name, 'téléphon') || str_contains($name, 'teleph') || str_contains($name, 'orange') || str_contains($name, ' tt')) { $totals['daily']++; continue; }
                if (str_contains($name, 'carte') || str_contains($name, 'cadeau') || str_contains($name, 'recharge')) {
                    if (empty($s->client_abonnement_expiration)) { $totals['other']++; continue; }
                    $days = Carbon::parse($s->client_abonnement_creation)->diffInDays(Carbon::parse($s->client_abonnement_expiration));
                    if ($days == 1) $totals['daily']++; elseif ($days == 30) $totals['monthly']++; elseif ($days == 365) $totals['annual']++; else $totals['other']++;
                    continue;
                }
                if (empty($s->client_abonnement_expiration)) { $totals['other']++; } else {
                    $days = Carbon::parse($s->client_abonnement_creation)->diffInDays(Carbon::parse($s->client_abonnement_expiration));
                    if ($days <= 2) $totals['daily']++; elseif ($days >= 20 && $days <= 40) $totals['monthly']++; elseif ($days >= 330) $totals['annual']++; else $totals['other']++;
                }
            }
            return $totals;
        } catch (\Exception $e) { return ['daily' => 0, 'monthly' => 0, 'annual' => 0, 'other' => 0]; }
    }

    private function calculateCohorts(string $startDate, string $endDate, string $operatorFilter): array
    {
        try {
            $endCarbon = Carbon::parse($endDate);
            $months = [];
            for ($i = 5; $i >= 0; $i--) {
                $cohortMonth = $endCarbon->copy()->subMonths($i);
                $months[] = ['label' => $cohortMonth->format('M Y'), 'start' => $cohortMonth->copy()->startOfMonth(), 'end' => $cohortMonth->copy()->endOfMonth(), 'd30' => $cohortMonth->copy()->startOfMonth()->addDays(30), 'd60' => $cohortMonth->copy()->startOfMonth()->addDays(60)];
            }
            $selectParts = []; $bindings = [];
            foreach ($months as $idx => $m) {
                $mStart = $m['start']->toDateTimeString(); $mEnd = $m['end']->toDateTimeString(); $d30 = $m['d30']->toDateTimeString(); $d60 = $m['d60']->toDateTimeString();
                $selectParts[] = "SUM(CASE WHEN ca.client_abonnement_creation BETWEEN ? AND ? THEN 1 ELSE 0 END) as total_{$idx}"; $bindings[] = $mStart; $bindings[] = $mEnd;
                $selectParts[] = "SUM(CASE WHEN ca.client_abonnement_creation BETWEEN ? AND ? AND (ca.client_abonnement_expiration IS NULL OR ca.client_abonnement_expiration >= ?) THEN 1 ELSE 0 END) as d30_{$idx}"; $bindings[] = $mStart; $bindings[] = $mEnd; $bindings[] = $d30;
                $selectParts[] = "SUM(CASE WHEN ca.client_abonnement_creation BETWEEN ? AND ? AND (ca.client_abonnement_expiration IS NULL OR ca.client_abonnement_expiration >= ?) THEN 1 ELSE 0 END) as d60_{$idx}"; $bindings[] = $mStart; $bindings[] = $mEnd; $bindings[] = $d60;
            }
            $opWhere = '';
            if ($operatorFilter !== 'ALL' && !empty($operatorFilter)) { $opId = $this->getOperatorId($operatorFilter); if ($opId) $opWhere = " AND ca.country_payments_methods_id = {$opId}"; }
            $globalStart = $months[0]['start']->toDateTimeString(); $globalEnd = $months[count($months) - 1]['end']->toDateTimeString();
            $bindings[] = $globalStart; $bindings[] = $globalEnd;
            $result = DB::selectOne("SELECT " . implode(', ', $selectParts) . " FROM client_abonnement ca WHERE ca.client_abonnement_creation >= ? AND ca.client_abonnement_creation <= ? {$opWhere}", $bindings);
            $cohorts = [];
            foreach ($months as $idx => $m) {
                $total = (int)($result->{"total_{$idx}"} ?? 0); $d30 = (int)($result->{"d30_{$idx}"} ?? 0); $d60 = (int)($result->{"d60_{$idx}"} ?? 0);
                $cohorts[] = ['month' => $m['label'], 'total' => $total, 'survival_d30' => $total > 0 ? round(($d30 / $total) * 100, 1) : 0, 'survival_d60' => $total > 0 ? round(($d60 / $total) * 100, 1) : 0];
            }
            return $cohorts;
        } catch (\Exception $e) { return []; }
    }

    private function calculateRenewalRate(string $startDate, string $endDate, string $operatorFilter): float
    {
        try {
            $endCarbon = Carbon::parse($endDate)->endOfDay();
            $expiredQuery = DB::table('client_abonnement as ca')->whereBetween('ca.client_abonnement_expiration', [$startDate, $endCarbon]);
            $this->applyOperatorJoinAndFilter($expiredQuery, $operatorFilter, 'ca');
            $expiredSubscriptions = $expiredQuery->count();
            if ($expiredSubscriptions == 0) return 0;
            $renewedQuery = DB::table('client_abonnement as ca1')->join('client_abonnement as ca2', 'ca1.client_id', '=', 'ca2.client_id')->whereBetween('ca1.client_abonnement_expiration', [$startDate, $endCarbon])->where('ca2.client_abonnement_creation', '>', DB::raw('ca1.client_abonnement_expiration'))->whereRaw("ca2.client_abonnement_creation <= DATE_ADD(ca1.client_abonnement_expiration, INTERVAL 60 DAY)");
            $this->applyOperatorJoinAndFilter($renewedQuery, $operatorFilter, 'ca1');
            $renewedSubscriptions = $renewedQuery->distinct('ca1.client_abonnement_id')->count();
            return round(($renewedSubscriptions / $expiredSubscriptions) * 100, 1);
        } catch (\Exception $e) { return 0; }
    }

    private function calculateAverageLifespan(string $startDate, string $endDate, string $operatorFilter): float
    {
        try {
            $endCarbon = Carbon::parse($endDate)->endOfDay();
            $query = DB::table('client_abonnement as ca')->where('ca.client_abonnement_creation', '>=', $startDate)->where('ca.client_abonnement_creation', '<=', $endCarbon);
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
        } catch (\Exception $e) { return 0; }
    }

    private function calculateReactivationRate(string $startDate, string $endDate, string $operatorFilter): float
    {
        try {
            $endCarbon = Carbon::parse($endDate)->endOfDay();
            $expiredBeforePeriod = DB::table('client_abonnement as ca')->where('ca.client_abonnement_expiration', '<', $startDate);
            $this->applyOperatorJoinAndFilter($expiredBeforePeriod, $operatorFilter, 'ca');
            $expiredClients = $expiredBeforePeriod->distinct('ca.client_id')->pluck('ca.client_id');
            $expiredCount = $expiredClients->count();
            if ($expiredCount == 0 || $expiredCount > 15000) return 0;
            $reactivatedQuery = DB::table('client_abonnement as ca')->whereIn('ca.client_id', $expiredClients)->where('ca.client_abonnement_creation', '>=', $startDate)->where('ca.client_abonnement_creation', '<=', $endCarbon);
            $this->applyOperatorJoinAndFilter($reactivatedQuery, $operatorFilter, 'ca');
            $reactivatedClients = $reactivatedQuery->distinct('ca.client_id')->count();
            return round(($reactivatedClients / $expiredClients->count()) * 100, 1);
        } catch (\Exception $e) { return 0; }
    }
}
