<?php

namespace App\Console\Commands;

use App\Services\TimweLifetimeAggregateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TimweLifetimeBackfillCommand extends Command
{
    protected $signature = 'timwe:lifetime-backfill
                            {--chunk=500 : Nombre de numéros par lot}
                            {--dry-run : Lister les numéros sans recalculer}';

    protected $description = 'Remplit la table timwe_phone_lifetime_stats à partir de transactions_history (tous les numéros ayant au moins une transaction Timwe).';

    public function handle(): int
    {
        if (!Schema::hasTable('timwe_phone_lifetime_stats')) {
            $this->error('La table timwe_phone_lifetime_stats n\'existe pas. Exécutez les migrations.');
            return self::FAILURE;
        }

        $chunkSize = (int) $this->option('chunk');
        $dryRun = $this->option('dry-run');

        $query = DB::table('transactions_history as th')
            ->join('client as c', 'th.client_id', '=', 'c.client_id')
            ->where(function ($q) {
                $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                    ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
            })
            ->whereNotNull('th.result')
            ->select('c.client_telephone')
            ->distinct();

        $phones = $query->pluck('client_telephone')->unique()->filter()->values()->all();
        $total = count($phones);
        $this->info("Numéros distincts avec au moins une transaction Timwe : {$total}");
        if ($total === 0) {
            return self::SUCCESS;
        }
        if ($dryRun) {
            $this->info('Mode dry-run : aucun recalcul.');
            return self::SUCCESS;
        }

        $service = new TimweLifetimeAggregateService();
        $bar = $this->output->createProgressBar($total);
        $bar->start();
        $done = 0;
        foreach (array_chunk($phones, $chunkSize) as $chunk) {
            foreach ($chunk as $phone) {
                try {
                    $service->recalculateForPhone($phone);
                } catch (\Throwable $e) {
                    $this->newLine();
                    $this->warn("Erreur pour {$phone}: " . $e->getMessage());
                }
                $done++;
                $bar->advance();
            }
        }
        $bar->finish();
        $this->newLine();
        $this->info("Backfill lifetime terminé : {$done} numéros traités.");
        return self::SUCCESS;
    }
}
