<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MaterializeTransactionStats extends Command
{
    protected $signature = 'dashboard:materialize-transactions
        {--days=90 : Number of days to compute}
        {--from= : Start date (Y-m-d)}
        {--to= : End date (Y-m-d)}
        {--force : Recompute even if already exists}';

    protected $description = 'Batch pre-compute daily transaction metrics into transaction_daily_stats';

    public function handle(): int
    {
        $startTime = microtime(true);
        ini_set('memory_limit', '1G');
        DB::statement('SET SESSION max_execution_time=300000');

        if ($this->option('from') && $this->option('to')) {
            $startDate = Carbon::parse($this->option('from'));
            $endDate = Carbon::parse($this->option('to'));
        } else {
            $days = (int) $this->option('days');
            $endDate = Carbon::yesterday();
            $startDate = $endDate->copy()->subDays($days - 1);
        }

        $this->info("Materializing transaction stats: {$startDate->toDateString()} to {$endDate->toDateString()}");

        $startStr = $startDate->toDateString();
        $endStr = $endDate->copy()->addDay()->toDateString();
        $force = $this->option('force');

        // Skip existing dates if not --force
        $existingDates = [];
        if (!$force) {
            $existingDates = DB::table('transaction_daily_stats')
                ->where('stat_date', '>=', $startStr)
                ->where('stat_date', '<=', $endDate->toDateString())
                ->whereNull('operator_id')
                ->pluck('stat_date')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->toArray();
            if (count($existingDates) > 0) {
                $this->info("Skipping " . count($existingDates) . " already computed dates");
            }
        }

        // === STEP 1: Transaction counts per day (ALL operators) ===
        $this->info("[1/5] Transaction counts...");
        $txRows = DB::select("
            SELECT DATE(h.time) as stat_date, COUNT(*) as cnt, COUNT(DISTINCT ca.client_id) as users
            FROM history h
            JOIN client_abonnement ca ON h.client_abonnement_id = ca.client_abonnement_id
            WHERE h.time >= ? AND h.time < ?
            GROUP BY DATE(h.time)
        ", [$startStr, $endStr]);
        $txMap = [];
        foreach ($txRows as $r) {
            $txMap[$r->stat_date] = ['count' => (int)$r->cnt, 'users' => (int)$r->users];
        }
        $this->info("  Found " . count($txMap) . " days with transactions");

        // === STEP 2: Cohort transactions (transactions from subs created on same day) ===
        $this->info("[2/5] Cohort transactions...");
        $cohortRows = DB::select("
            SELECT DATE(h.time) as stat_date, COUNT(*) as cnt, COUNT(DISTINCT ca.client_id) as users
            FROM history h
            JOIN client_abonnement ca ON h.client_abonnement_id = ca.client_abonnement_id
            WHERE h.time >= ? AND h.time < ?
            AND DATE(ca.client_abonnement_creation) = DATE(h.time)
            GROUP BY DATE(h.time)
        ", [$startStr, $endStr]);
        $cohortMap = [];
        foreach ($cohortRows as $r) {
            $cohortMap[$r->stat_date] = ['count' => (int)$r->cnt, 'users' => (int)$r->users];
        }

        // === STEP 3: Active merchants per day ===
        $this->info("[3/5] Active merchants...");
        $merchantRows = DB::select("
            SELECT DATE(h.time) as stat_date, COUNT(DISTINCT p.partner_id) as cnt
            FROM history h
            JOIN promotion p ON h.promotion_id = p.promotion_id
            WHERE h.time >= ? AND h.time < ?
            AND h.promotion_id IS NOT NULL
            GROUP BY DATE(h.time)
        ", [$startStr, $endStr]);
        $merchantMap = [];
        foreach ($merchantRows as $r) {
            $merchantMap[$r->stat_date] = (int)$r->cnt;
        }

        // === STEP 4: By operator breakdown ===
        $this->info("[4/5] By operator breakdown...");
        $byOpRows = DB::select("
            SELECT DATE(h.time) as stat_date,
                   cpm.country_payments_methods_name as op_name,
                   COUNT(*) as cnt
            FROM history h
            JOIN client_abonnement ca ON h.client_abonnement_id = ca.client_abonnement_id
            JOIN country_payments_methods cpm ON ca.country_payments_methods_id = cpm.country_payments_methods_id
            WHERE h.time >= ? AND h.time < ?
            GROUP BY DATE(h.time), cpm.country_payments_methods_name
        ", [$startStr, $endStr]);
        $byOpMap = [];
        foreach ($byOpRows as $r) {
            $byOpMap[$r->stat_date][$r->op_name] = (int)$r->cnt;
        }

        // === STEP 5: By plan breakdown ===
        $this->info("[5/5] By plan breakdown...");
        $byPlanRows = DB::select("
            SELECT DATE(h.time) as stat_date,
                   CASE
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
                   END as plan_type,
                   COUNT(*) as cnt
            FROM history h
            JOIN client_abonnement ca ON h.client_abonnement_id = ca.client_abonnement_id
            JOIN country_payments_methods cpm ON ca.country_payments_methods_id = cpm.country_payments_methods_id
            LEFT JOIN abonnement_tarifs at ON ca.tarif_id = at.abonnement_tarifs_id
            WHERE h.time >= ? AND h.time < ?
            GROUP BY DATE(h.time), plan_type
        ", [$startStr, $endStr]);
        $byPlanMap = [];
        foreach ($byPlanRows as $r) {
            $byPlanMap[$r->stat_date][$r->plan_type] = (int)$r->cnt;
        }

        // === INSERT ===
        $this->info("Inserting rows...");
        $inserted = 0;
        $cursor = $startDate->copy();

        while ($cursor->lte($endDate)) {
            $d = $cursor->toDateString();
            $cursor->addDay();

            if (!$force && in_array($d, $existingDates)) {
                continue;
            }

            $tx = $txMap[$d] ?? ['count' => 0, 'users' => 0];
            $cohort = $cohortMap[$d] ?? ['count' => 0, 'users' => 0];

            // Remplacement explicite plutôt qu'updateOrInsert : l'index UNIQUE
            // (stat_date, operator_id) ne contraint pas les NULL sous MySQL, donc
            // des doublons ont pu s'accumuler pour operator_id NULL — et
            // updateOrInsert() ne corrige qu'UNE ligne (->limit(1)->update()),
            // laissant les autres avec des valeurs périmées que la lecture
            // additionne ensuite. Le delete+insert garantit une ligne unique et
            // nettoie au passage les doublons déjà présents.
            DB::transaction(function () use ($d, $tx, $cohort, $merchantMap, $byOpMap, $byPlanMap) {
                DB::table('transaction_daily_stats')
                    ->where('stat_date', $d)
                    ->whereNull('operator_id')
                    ->delete();

                DB::table('transaction_daily_stats')->insert([
                    'stat_date' => $d,
                    'operator_id' => null,
                    'transaction_count' => $tx['count'],
                    'distinct_users' => $tx['users'],
                    'cohort_transaction_count' => $cohort['count'],
                    'cohort_distinct_users' => $cohort['users'],
                    'active_merchants' => $merchantMap[$d] ?? 0,
                    'by_operator' => json_encode($byOpMap[$d] ?? []),
                    'by_plan' => json_encode($byPlanMap[$d] ?? []),
                    'computed_at' => now(),
                ]);
            });
            $inserted++;
        }

        $elapsed = round(microtime(true) - $startTime, 1);
        $total = DB::table('transaction_daily_stats')->count();
        $this->info("Done in {$elapsed}s: {$inserted} inserted, {$total} total rows");
        Log::info("MaterializeTransactionStats: {$inserted} inserted in {$elapsed}s, {$total} total");

        return 0;
    }
}
