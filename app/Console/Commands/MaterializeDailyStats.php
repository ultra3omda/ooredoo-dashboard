<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MaterializeDailyStats extends Command
{
    protected $signature = 'dashboard:materialize {--days=90 : Nombre de jours à recalculer} {--date= : Date spécifique (Y-m-d)} {--force : Recalculer même si déjà fait}';
    protected $description = 'Pré-calcule les KPIs quotidiens dans dashboard_daily_stats pour accélérer le dashboard';

    public function handle()
    {
        $startTime = microtime(true);
        $specificDate = $this->option('date');
        $days = (int) $this->option('days');
        $force = $this->option('force');

        if ($specificDate) {
            $dates = [Carbon::parse($specificDate)];
            $this->info("Matérialisation pour le {$specificDate}...");
        } else {
            $endDate = Carbon::yesterday();
            $startDate = $endDate->copy()->subDays($days - 1);
            $dates = [];
            $current = $startDate->copy();
            while ($current->lte($endDate)) {
                $dates[] = $current->copy();
                $current->addDay();
            }
            $this->info("Matérialisation de {$startDate->toDateString()} à {$endDate->toDateString()} ({$days} jours)...");
        }

        $operators = $this->getOperatorIds();
        $operatorList = array_merge([null], $operators); // null = ALL
        $totalInserted = 0;
        $totalSkipped = 0;

        $bar = $this->output->createProgressBar(count($dates));
        $bar->start();

        foreach ($dates as $date) {
            $dateStr = $date->toDateString();
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->addDay()->startOfDay();

            foreach ($operatorList as $opId) {
                if (!$force) {
                    $exists = DB::table('dashboard_daily_stats')
                        ->where('stat_date', $dateStr)
                        ->where(function ($q) use ($opId) {
                            if ($opId === null) {
                                $q->whereNull('operator_id');
                            } else {
                                $q->where('operator_id', $opId);
                            }
                        })
                        ->exists();
                    if ($exists) {
                        $totalSkipped++;
                        continue;
                    }
                }

                try {
                    $stats = $this->computeDayStats($dayStart, $dayEnd, $opId);

                    // Remplacement explicite plutôt qu'updateOrInsert : sous MySQL
                    // l'index UNIQUE ne contraint pas les NULL, donc des doublons
                    // peuvent exister pour operator_id NULL, et updateOrInsert()
                    // n'en corrige qu'une seule (->limit(1)->update()). La lecture
                    // additionnant les lignes, les doublons périmés faussent le
                    // résultat. Le delete+insert garantit une ligne unique.
                    DB::transaction(function () use ($dateStr, $opId, $stats) {
                        DB::table('dashboard_daily_stats')
                            ->where('stat_date', $dateStr)
                            ->where(function ($q) use ($opId) {
                                if ($opId === null) {
                                    $q->whereNull('operator_id');
                                } else {
                                    $q->where('operator_id', $opId);
                                }
                            })
                            ->delete();

                        DB::table('dashboard_daily_stats')->insert(array_merge(
                            $stats,
                            ['stat_date' => $dateStr, 'operator_id' => $opId, 'computed_at' => now()]
                        ));
                    });
                    $totalInserted++;
                } catch (\Exception $e) {
                    Log::warning("Materialize failed for {$dateStr} op={$opId}: " . $e->getMessage());
                    $this->warn("Erreur {$dateStr} op={$opId}: " . substr($e->getMessage(), 0, 80));
                }
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $elapsed = round(microtime(true) - $startTime, 1);
        $this->info("Terminé en {$elapsed}s: {$totalInserted} insérés, {$totalSkipped} ignorés.");
        Log::info("MaterializeDailyStats: {$totalInserted} inserted, {$totalSkipped} skipped in {$elapsed}s");

        return 0;
    }

    private function getOperatorIds(): array
    {
        return DB::table('country_payments_methods')
            ->whereNotNull('country_payments_methods_name')
            ->where('country_payments_methods_name', '!=', '')
            ->pluck('country_payments_methods_id')
            ->map(fn($id) => (int)$id)
            ->toArray();
    }

    private function computeDayStats(Carbon $dayStart, Carbon $dayEnd, ?int $operatorId): array
    {
        DB::statement('SET SESSION max_execution_time=30000');

        $isAll = ($operatorId === null);

        // Activated subscriptions
        $activatedQuery = DB::table('client_abonnement as ca')
            ->where('ca.client_abonnement_creation', '>=', $dayStart)
            ->where('ca.client_abonnement_creation', '<', $dayEnd);
        if (!$isAll) {
            $activatedQuery->where('ca.country_payments_methods_id', $operatorId);
        }
        $activated = $activatedQuery->count();

        // Deactivated subscriptions
        $deactivatedQuery = DB::table('client_abonnement as ca')
            ->where('ca.client_abonnement_expiration', '>=', $dayStart)
            ->where('ca.client_abonnement_expiration', '<', $dayEnd);
        if (!$isAll) {
            $deactivatedQuery->where('ca.country_payments_methods_id', $operatorId);
        }
        $deactivated = $deactivatedQuery->count();

        // Active snapshot at end of day
        $activeQuery = DB::table('client_abonnement as ca')
            ->where('ca.client_abonnement_creation', '<', $dayEnd)
            ->where(function ($q) use ($dayEnd) {
                $q->whereNull('ca.client_abonnement_expiration')
                  ->orWhere('ca.client_abonnement_expiration', '>=', $dayEnd);
            });
        if (!$isAll) {
            $activeQuery->where('ca.country_payments_methods_id', $operatorId);
        }
        $activeSnapshot = $activeQuery->count();

        // Transactions
        $txQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->where('h.time', '>=', $dayStart)
            ->where('h.time', '<', $dayEnd);
        if (!$isAll) {
            $txQuery->where('ca.country_payments_methods_id', $operatorId);
        }

        $txData = $txQuery->selectRaw('COUNT(*) as tx_count, COUNT(DISTINCT ca.client_id) as user_count')->first();

        // Cohort transactions (subscriptions created same day)
        $cohortTxQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->where('h.time', '>=', $dayStart)
            ->where('h.time', '<', $dayEnd)
            ->where('ca.client_abonnement_creation', '>=', $dayStart)
            ->where('ca.client_abonnement_creation', '<', $dayEnd);
        if (!$isAll) {
            $cohortTxQuery->where('ca.country_payments_methods_id', $operatorId);
        }

        $cohortData = $cohortTxQuery->selectRaw('COUNT(*) as tx_count, COUNT(DISTINCT ca.client_id) as user_count')->first();

        // Active merchants
        $merchantQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->join('partner as pt', 'p.partner_id', '=', 'pt.partner_id')
            ->where('h.time', '>=', $dayStart)
            ->where('h.time', '<', $dayEnd)
            ->whereNotNull('h.promotion_id');
        if (!$isAll) {
            $merchantQuery->where('ca.country_payments_methods_id', $operatorId);
        }
        $activeMerchants = $merchantQuery->distinct('pt.partner_id')->count('pt.partner_id');

        // Lost subscriptions (created AND expired same day)
        $lostQuery = DB::table('client_abonnement as ca')
            ->where('ca.client_abonnement_creation', '>=', $dayStart)
            ->where('ca.client_abonnement_creation', '<', $dayEnd)
            ->whereNotNull('ca.client_abonnement_expiration')
            ->where('ca.client_abonnement_expiration', '>=', $dayStart)
            ->where('ca.client_abonnement_expiration', '<', $dayEnd);
        if (!$isAll) {
            $lostQuery->where('ca.country_payments_methods_id', $operatorId);
        }
        $lostSubs = $lostQuery->count();

        return [
            'activated_count' => $activated,
            'deactivated_count' => $deactivated,
            'active_snapshot' => $activeSnapshot,
            'transactions_count' => $txData->tx_count ?? 0,
            'transacting_users' => $txData->user_count ?? 0,
            'cohort_transactions' => $cohortData->tx_count ?? 0,
            'cohort_transacting_users' => $cohortData->user_count ?? 0,
            'active_merchants' => $activeMerchants,
            'lost_subscriptions' => $lostSubs,
        ];
    }
}
