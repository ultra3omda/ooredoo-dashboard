<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\OperatorHelper;

class TransactionService
{
    use OperatorHelper;

    public function getTransactions(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        if ($selectedOperator === 'ALL' || empty($selectedOperator)) {
            if ($this->hasTransactionMaterializedCoverage($startBound, $endExclusive)) {
                Log::info("getTransactionsData - MATERIALIZED path");
                return $this->getTransactionsDataMaterialized($startBound, $endExclusive);
            }
        }
        
        Log::info("getTransactionsData - LIVE path");
        return $this->getTransactionsDataLive($startBound, $endExclusive, $selectedOperator);
    }

    public function hasTransactionMaterializedCoverage(Carbon $startBound, Carbon $endExclusive): bool
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

    private function getTransactionsDataMaterialized(Carbon $startBound, Carbon $endExclusive): array
    {
        $periodDays = $startBound->diffInDays($endExclusive);
        $granularity = $periodDays > 365 ? 'month' : 'day';
        $dateExpr = $granularity === 'month' ? "DATE_FORMAT(stat_date, '%Y-%m-01')" : 'stat_date';
        
        $rows = DB::table('transaction_daily_stats')
            ->where('stat_date', '>=', $startBound->toDateString())
            ->where('stat_date', '<', $endExclusive->toDateString())
            ->whereNull('operator_id')
            ->select(DB::raw("{$dateExpr} as period_date"), DB::raw('SUM(transaction_count) as transactions'), DB::raw('SUM(distinct_users) as users'))
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
            "analytics" => ["byOperator" => $byOperatorResult, "byPlan" => $byPlanResult, "byChannel" => []]
        ];
    }

    private function getTransactionsDataLive(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        $periodDays = $startBound->diffInDays($endExclusive);
        $granularity = $periodDays > 365 ? 'month' : 'day';
        $historyDateExpr = $granularity === 'month' ? "DATE_FORMAT(h.time, '%Y-%m-01')" : "DATE(h.time)";
        
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
        
        $startDate = $startBound->copy();
        $endDate = $endExclusive->copy()->subDay();
        $dailyVolume = [];
        $intervalDays = max(1, intval($periodDays / 30));
        
        if ($granularity === 'month') {
            $cursor = $startDate->copy()->firstOfMonth();
            while ($cursor->lte($endDate)) {
                $key = $cursor->copy()->firstOfMonth()->toDateString();
                $row = $transactionsRaw[$key] ?? null;
                $dailyVolume[] = ['date' => $key, 'transactions' => $row ? (int)$row->transactions : 0, 'users' => $row ? (int)$row->users : 0];
                $cursor->addMonth();
            }
        } else {
            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                $dateStr = $cursor->toDateString();
                $dayTransactions = $transactionsRaw[$dateStr] ?? null;
                $dailyVolume[] = ['date' => $dateStr, 'transactions' => $dayTransactions ? (int)$dayTransactions->transactions : 0, 'users' => $dayTransactions ? (int)$dayTransactions->users : 0];
                $cursor->addDays($intervalDays);
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
            ->map(fn($item) => ['operator' => $item->operator, 'count' => (int)$item->count])
            ->toArray();
    }

    private function getTransactionsByPlan(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        $planCase = "CASE 
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
        END";

        $query = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->leftJoin('abonnement_tarifs as at', 'ca.tarif_id', '=', 'at.abonnement_tarifs_id')
            ->select(DB::raw("{$planCase} as plan"), DB::raw('COUNT(*) as count'))
            ->where('h.time', '>=', $startBound)
            ->where('h.time', '<', $endExclusive);
        
        $this->applyOperatorFilter($query, $selectedOperator);
        
        return $query->groupBy(DB::raw($planCase))
            ->get()
            ->map(fn($item) => ['plan' => $item->plan, 'count' => (int)$item->count])
            ->toArray();
    }
}
