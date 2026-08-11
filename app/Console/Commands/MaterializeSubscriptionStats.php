<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MaterializeSubscriptionStats extends Command
{
    protected $signature = 'dashboard:materialize-subscriptions 
        {--days=90 : Number of days to compute} 
        {--from= : Start date (Y-m-d)} 
        {--to= : End date (Y-m-d)} 
        {--force : Recompute even if already exists}
        {--operator= : Specific operator ID (omit for ALL only)}
        {--all-operators : Compute for all individual operators too}';
    
    protected $description = 'Batch pre-compute daily subscription metrics into subscription_daily_stats';

    private array $operatorNames = [];

    public function handle(): int
    {
        $startTime = microtime(true);
        ini_set('memory_limit', '1G');
        DB::statement('SET SESSION max_execution_time=300000');
        DB::statement('SET SESSION group_concat_max_len=1000000');

        // Determine date range
        if ($this->option('from') && $this->option('to')) {
            $startDate = Carbon::parse($this->option('from'));
            $endDate = Carbon::parse($this->option('to'));
        } else {
            $days = (int) $this->option('days');
            $endDate = Carbon::yesterday();
            $startDate = $endDate->copy()->subDays($days - 1);
        }

        $this->info("Batch materializing subscription stats: {$startDate->toDateString()} to {$endDate->toDateString()}");

        $this->loadOperatorNames();

        // Build operator list
        $operators = $this->getOperatorList();

        foreach ($operators as $opId) {
            $opName = ($opId !== null && isset($this->operatorNames[$opId])) ? $this->operatorNames[$opId] : '?';
            $label = $opId === null ? 'ALL' : "op={$opId} ({$opName})";
            $this->info("\n=== Processing {$label} ===");
            $opStart = microtime(true);

            $this->batchMaterialize($startDate, $endDate, $opId);

            $opElapsed = round(microtime(true) - $opStart, 1);
            $this->info("  {$label} done in {$opElapsed}s");
        }

        $totalElapsed = round(microtime(true) - $startTime, 1);
        $count = DB::table('subscription_daily_stats')->count();
        $this->info("\nTotal: {$totalElapsed}s, {$count} rows in subscription_daily_stats");
        Log::info("MaterializeSubscriptionStats completed in {$totalElapsed}s, {$count} total rows");

        return 0;
    }

    private function batchMaterialize(Carbon $startDate, Carbon $endDate, ?int $operatorId): void
    {
        $startStr = $startDate->toDateString();
        $endStr = $endDate->copy()->addDay()->toDateString(); // exclusive
        $isAll = ($operatorId === null);
        $opFilter = $isAll ? '' : " AND ca.country_payments_methods_id = {$operatorId}";

        // If not --force, skip existing dates
        $force = $this->option('force');
        $existingDates = [];
        if (!$force) {
            $existingDates = DB::table('subscription_daily_stats')
                ->where('stat_date', '>=', $startStr)
                ->where('stat_date', '<=', $endDate->toDateString())
                ->where(function($q) use ($operatorId) {
                    if ($operatorId === null) {
                        $q->whereNull('operator_id');
                    } else {
                        $q->where('operator_id', $operatorId);
                    }
                })
                ->pluck('stat_date')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->toArray();
            
            if (count($existingDates) > 0) {
                $this->info("  Skipping " . count($existingDates) . " already computed dates");
            }
        }

        // STEP 1: Activated count per day (batch)
        $this->info("  [1/6] Activations...");
        $activations = DB::select("
            SELECT DATE(ca.client_abonnement_creation) as stat_date, COUNT(*) as cnt
            FROM client_abonnement ca
            WHERE ca.client_abonnement_creation >= ? AND ca.client_abonnement_creation < ?
            {$opFilter}
            GROUP BY DATE(ca.client_abonnement_creation)
        ", [$startStr, $endStr]);
        $activationMap = [];
        foreach ($activations as $row) {
            $activationMap[$row->stat_date] = (int)$row->cnt;
        }
        $this->info("    Found " . count($activationMap) . " days with activations");

        // STEP 2: Deactivated count per day (batch)
        $this->info("  [2/6] Deactivations...");
        $deactivations = DB::select("
            SELECT DATE(ca.client_abonnement_expiration) as stat_date, COUNT(*) as cnt
            FROM client_abonnement ca
            WHERE ca.client_abonnement_expiration >= ? AND ca.client_abonnement_expiration < ?
            {$opFilter}
            GROUP BY DATE(ca.client_abonnement_expiration)
        ", [$startStr, $endStr]);
        $deactivationMap = [];
        foreach ($deactivations as $row) {
            $deactivationMap[$row->stat_date] = (int)$row->cnt;
        }

        // STEP 3: Channel distribution per day (batch)
        $this->info("  [3/6] Channels...");
        $channelRows = DB::select("
            SELECT DATE(ca.client_abonnement_creation) as stat_date,
                   cpm.country_payments_methods_name as cpm_name,
                   COUNT(*) as cnt
            FROM client_abonnement ca
            JOIN country_payments_methods cpm ON ca.country_payments_methods_id = cpm.country_payments_methods_id
            WHERE ca.client_abonnement_creation >= ? AND ca.client_abonnement_creation < ?
            {$opFilter}
            GROUP BY DATE(ca.client_abonnement_creation), cpm.country_payments_methods_name
        ", [$startStr, $endStr]);

        $channelMap = []; // date => [cb, recharge, phone_balance, other]
        foreach ($channelRows as $row) {
            $d = $row->stat_date;
            if (!isset($channelMap[$d])) {
                $channelMap[$d] = ['cb' => 0, 'recharge' => 0, 'phone_balance' => 0, 'other' => 0];
            }
            $name = mb_strtolower($row->cpm_name ?? '');
            $cnt = (int)$row->cnt;
            $channelMap[$d][$this->classifyChannel($name)] += $cnt;
        }

        // STEP 4: Plan distribution per day (batch via SQL classification)
        $this->info("  [4/6] Plan distribution...");
        $planRows = DB::select("
            SELECT DATE(ca.client_abonnement_creation) as stat_date,
                   cpm.country_payments_methods_name as cpm_name,
                   ca.client_abonnement_creation,
                   ca.client_abonnement_expiration,
                   DATEDIFF(ca.client_abonnement_expiration, ca.client_abonnement_creation) as dur
            FROM client_abonnement ca
            JOIN country_payments_methods cpm ON ca.country_payments_methods_id = cpm.country_payments_methods_id
            WHERE ca.client_abonnement_creation >= ? AND ca.client_abonnement_creation < ?
            {$opFilter}
        ", [$startStr, $endStr]);

        $planMap = []; // date => [daily, monthly, annual, other]
        foreach ($planRows as $row) {
            $d = $row->stat_date;
            if (!isset($planMap[$d])) {
                $planMap[$d] = ['daily' => 0, 'monthly' => 0, 'annual' => 0, 'other' => 0];
            }
            $planMap[$d][$this->classifyPlan($row)] += 1;
        }
        unset($planRows); // Free memory

        // STEP 5: Expired + Lifespan per day (batch)
        $this->info("  [5/6] Expired & lifespan...");
        $expiredRows = DB::select("
            SELECT DATE(ca.client_abonnement_expiration) as stat_date, COUNT(*) as cnt
            FROM client_abonnement ca
            WHERE ca.client_abonnement_expiration >= ? AND ca.client_abonnement_expiration < ?
            {$opFilter}
            GROUP BY DATE(ca.client_abonnement_expiration)
        ", [$startStr, $endStr]);
        $expiredMap = [];
        foreach ($expiredRows as $row) {
            $expiredMap[$row->stat_date] = (int)$row->cnt;
        }

        // Lifespan for subs created in period (with expiration)
        $lifespanRows = DB::select("
            SELECT DATE(ca.client_abonnement_creation) as stat_date,
                   SUM(DATEDIFF(COALESCE(ca.client_abonnement_expiration, NOW()), ca.client_abonnement_creation)) as total_days,
                   COUNT(*) as cnt
            FROM client_abonnement ca
            WHERE ca.client_abonnement_creation >= ? AND ca.client_abonnement_creation < ?
            {$opFilter}
            GROUP BY DATE(ca.client_abonnement_creation)
        ", [$startStr, $endStr]);
        $lifespanMap = [];
        foreach ($lifespanRows as $row) {
            $lifespanMap[$row->stat_date] = ['days' => (int)$row->total_days, 'cnt' => (int)$row->cnt];
        }

        // STEP 6: Renewal counts per day (batch - most complex)
        $this->info("  [6/6] Renewals...");
        $renewalRows = DB::select("
            SELECT DATE(ca1.client_abonnement_expiration) as stat_date,
                   COUNT(DISTINCT ca1.client_abonnement_id) as cnt
            FROM client_abonnement ca1
            INNER JOIN client_abonnement ca2 ON ca1.client_id = ca2.client_id
                AND ca2.client_abonnement_creation > ca1.client_abonnement_expiration
                AND ca2.client_abonnement_creation <= DATE_ADD(ca1.client_abonnement_expiration, INTERVAL 60 DAY)
            WHERE ca1.client_abonnement_expiration >= ? AND ca1.client_abonnement_expiration < ?
            {$opFilter}
            GROUP BY DATE(ca1.client_abonnement_expiration)
        ", [$startStr, $endStr]);
        $renewalMap = [];
        foreach ($renewalRows as $row) {
            $renewalMap[$row->stat_date] = (int)$row->cnt;
        }

        // === INSERT all days ===
        $this->info("  Inserting rows...");
        $inserted = 0;
        $cursor = $startDate->copy();
        $batch = [];

        while ($cursor->lte($endDate)) {
            $d = $cursor->toDateString();
            $cursor->addDay();

            if (!$force && in_array($d, $existingDates)) {
                continue;
            }

            $channels = $channelMap[$d] ?? ['cb' => 0, 'recharge' => 0, 'phone_balance' => 0, 'other' => 0];
            $plans = $planMap[$d] ?? ['daily' => 0, 'monthly' => 0, 'annual' => 0, 'other' => 0];
            $lifespan = $lifespanMap[$d] ?? ['days' => 0, 'cnt' => 0];

            $batch[] = [
                'stat_date' => $d,
                'operator_id' => $operatorId,
                'activated_count' => $activationMap[$d] ?? 0,
                'deactivated_count' => $deactivationMap[$d] ?? 0,
                'active_snapshot' => 0, // Will compute separately if needed
                'channel_cb' => $channels['cb'],
                'channel_recharge' => $channels['recharge'],
                'channel_phone_balance' => $channels['phone_balance'],
                'channel_other' => $channels['other'],
                'plan_daily' => $plans['daily'],
                'plan_monthly' => $plans['monthly'],
                'plan_annual' => $plans['annual'],
                'plan_other' => $plans['other'],
                'expired_count' => $expiredMap[$d] ?? 0,
                'renewed_count' => $renewalMap[$d] ?? 0,
                'total_lifespan_days' => $lifespan['days'],
                'lifespan_sub_count' => $lifespan['cnt'],
                'computed_at' => now(),
            ];

            if (count($batch) >= 100) {
                $this->upsertBatch($batch);
                $inserted += count($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->upsertBatch($batch);
            $inserted += count($batch);
        }

        $this->info("  Inserted/updated {$inserted} rows");
    }

    private function upsertBatch(array $batch): void
    {
        foreach ($batch as $row) {
            // Remplacement explicite plutôt qu'updateOrInsert : sous MySQL l'index
            // UNIQUE ne contraint pas les NULL, donc des doublons peuvent exister
            // pour operator_id NULL, et updateOrInsert() n'en corrige qu'une seule
            // (->limit(1)->update()). La lecture additionnant les lignes, les
            // doublons périmés faussent le résultat.
            DB::transaction(function () use ($row) {
                DB::table('subscription_daily_stats')
                    ->where('stat_date', $row['stat_date'])
                    ->where(function ($q) use ($row) {
                        if ($row['operator_id'] === null) {
                            $q->whereNull('operator_id');
                        } else {
                            $q->where('operator_id', $row['operator_id']);
                        }
                    })
                    ->delete();

                DB::table('subscription_daily_stats')->insert($row);
            });
        }
    }

    private function classifyChannel(string $name): string
    {
        if (str_contains($name, 'banc') || str_contains($name, 'cb')) {
            return 'cb';
        }
        if (str_contains($name, 'cadeau') || str_contains($name, 'voucher') || str_contains($name, 'recharg')) {
            return 'recharge';
        }
        if (
            str_contains($name, 'solde') || str_contains($name, 'téléphon') || str_contains($name, 'teleph') ||
            str_contains($name, 'orange') || str_contains($name, ' tt') || str_contains($name, 'timwe') ||
            str_contains($name, 'izi') || str_contains($name, 'taraji') || str_contains($name, 'lefri9i') ||
            str_contains($name, 'pluxee')
        ) {
            return 'phone_balance';
        }
        return 'other';
    }

    private function classifyPlan(object $row): string
    {
        $name = mb_strtolower($row->cpm_name ?? '');
        $dur = $row->dur;

        if (str_contains($name, 'timwe')) return 'monthly';
        
        if (str_contains($name, 'solde') || str_contains($name, 'téléphon') || str_contains($name, 'teleph') ||
            str_contains($name, 'orange') || str_contains($name, ' tt')) {
            return 'daily';
        }

        if (str_contains($name, 'carte') || str_contains($name, 'cadeau') || str_contains($name, 'recharge')) {
            if ($dur === null) return 'other';
            if ($dur == 1) return 'daily';
            if ($dur == 30) return 'monthly';
            if ($dur == 365) return 'annual';
            return 'other';
        }

        if ($dur === null) return 'other';
        if ($dur <= 2) return 'daily';
        if ($dur >= 20 && $dur <= 40) return 'monthly';
        if ($dur >= 330) return 'annual';
        return 'other';
    }

    private function loadOperatorNames(): void
    {
        $rows = DB::table('country_payments_methods')
            ->select('country_payments_methods_id', 'country_payments_methods_name')
            ->get();
        foreach ($rows as $row) {
            $this->operatorNames[(int)$row->country_payments_methods_id] = $row->country_payments_methods_name;
        }
    }

    private function getOperatorList(): array
    {
        if ($this->option('operator')) {
            return [(int)$this->option('operator')];
        }

        $list = [null]; // null = ALL

        if ($this->option('all-operators')) {
            $ids = DB::table('country_payments_methods')
                ->whereNotNull('country_payments_methods_name')
                ->where('country_payments_methods_name', '!=', '')
                ->pluck('country_payments_methods_id')
                ->map(fn($id) => (int)$id)
                ->toArray();
            $list = array_merge($list, $ids);
        }

        return $list;
    }
}
