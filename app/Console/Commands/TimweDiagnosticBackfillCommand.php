<?php

namespace App\Console\Commands;

use App\Services\TimweDiagnosticAggregateService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TimweDiagnosticBackfillCommand extends Command
{
    protected $signature = 'timwe:diagnostic-backfill
                            {--start-date= : Date de début (Y-m-d)}
                            {--end-date= : Date de fin (Y-m-d)}
                            {--force : Recalculer même si des données existent}
                            {--analyze : Analyser les dates insérées (0 transaction vs >0) puis quitter}
                            {--only-empty : Recalculer uniquement les dates avec 0 transaction}
                            {--dry-run : Compter les transactions sans écrire (1 jour = start-date)}';

    protected $description = 'Remplit les tables d\'agrégation du diagnostic Timwe (timwe_diagnostic_daily_*) pour une période — comme timwe:calculate-daily pour les stats.';

    public function handle(): int
    {
        if ($this->option('analyze')) {
            return $this->runAnalyze();
        }
        if ($this->option('dry-run')) {
            return $this->runDryRun();
        }

        $startStr = $this->option('start-date') ?: Carbon::now()->subYear()->format('Y-m-d');
        $endStr = $this->option('end-date') ?: Carbon::now()->format('Y-m-d');
        $force = $this->option('force');
        $onlyEmpty = $this->option('only-empty');

        try {
            $start = Carbon::parse($startStr)->startOfDay();
            $end = Carbon::parse($endStr)->endOfDay();
        } catch (\Exception $e) {
            $this->error('Format de date invalide. Utilisez Y-m-d.');
            return self::FAILURE;
        }

        if ($start->gt($end)) {
            $this->error('La date de début doit être avant la date de fin.');
            return self::FAILURE;
        }

        $totalDays = $start->diffInDays($end) + 1;
        $this->info("Diagnostic Timwe - Backfill agrégation: {$startStr} → {$endStr} ({$totalDays} jours)" . ($force ? ' [--force: recalcul de toutes les dates]' : ''));
        if (!$force) {
            $this->line('Astuce: utilisez --force pour recalculer les dates déjà présentes (ex: 26/07 au 10/08).');
        }

        $service = new TimweDiagnosticAggregateService();
        $bar = $this->output->createProgressBar($totalDays);
        $bar->setFormat('verbose');
        $processed = 0;
        $skipped = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            $dateStr = $current->format('Y-m-d');
            $row = \DB::table('timwe_diagnostic_daily_summary')->where('stat_date', $dateStr)->first();
            $hasData = $row !== null;
            $isEmpty = $hasData && (int) ($row->total_transactions ?? 0) === 0;
            if ($hasData && !$force && !($onlyEmpty && $isEmpty)) {
                $skipped++;
                $bar->advance();
                $current->addDay();
                continue;
            }
            if ($onlyEmpty && $hasData && !$isEmpty) {
                $skipped++;
                $bar->advance();
                $current->addDay();
                continue;
            }
            try {
                $service->recalculateForDate($current);
                $processed++;
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("Erreur {$dateStr}: " . $e->getMessage());
            }
            $bar->advance();
            $current->addDay();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Backfill terminé. Jours recalculés: {$processed} / {$totalDays}." . ($skipped > 0 ? " ({$skipped} jour(s) déjà présents, ignorés sans --force)" : ''));
        $this->line('Le diagnostic pour 365 jours lira désormais depuis les tables agrégées (rapide).');
        return self::SUCCESS;
    }

    private function runDryRun(): int
    {
        $startStr = $this->option('start-date') ?: Carbon::yesterday()->format('Y-m-d');
        try {
            $date = Carbon::parse($startStr)->startOfDay();
        } catch (\Exception $e) {
            $this->error('Format de date invalide. Utilisez Y-m-d.');
            return self::FAILURE;
        }
        $service = new TimweDiagnosticAggregateService();
        $count = $service->countTransactionsForDate($date);
        $this->info("Dry-run {$startStr}: {$count} transaction(s) Timwe trouvée(s) (status TIMWE_RENEWED_NOTIF ou TIMWE_CHARGE_DELIVERED, result non null).");
        if ($count === 0) {
            $totalTimwe = \DB::table('transactions_history')
                ->where(function ($q) {
                    $q->where('status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')->orWhere('status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
                })
                ->count();
            $this->line("Sur toute la table transactions_history, il y a {$totalTimwe} ligne(s) avec ce statut Timwe.");
        }
        return self::SUCCESS;
    }

    private function runAnalyze(): int
    {
        if (!\Schema::hasTable('timwe_diagnostic_daily_summary')) {
            $this->warn('Table timwe_diagnostic_daily_summary absente.');
            return self::SUCCESS;
        }
        $rows = \DB::table('timwe_diagnostic_daily_summary')->orderBy('stat_date')->get();
        $total = $rows->count();
        $withData = $rows->filter(fn ($r) => (int) ($r->total_transactions ?? 0) > 0)->count();
        $empty = $total - $withData;
        $this->info('=== Analyse timwe_diagnostic_daily_summary ===');
        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Total dates insérées', $total],
                ['Dates avec transactions > 0', $withData],
                ['Dates avec 0 transaction (à recalculer)', $empty],
            ]
        );
        if ($total > 0) {
            $first = $rows->first()->stat_date;
            $last = $rows->last()->stat_date;
            $this->line("Période couverte: {$first} → {$last}");
        }
        if ($empty > 0) {
            $emptyDates = $rows->filter(fn ($r) => (int) ($r->total_transactions ?? 0) === 0)->pluck('stat_date')->values()->all();
            $ranges = $this->compactDateRanges($emptyDates);
            $this->newLine();
            $this->line('Dates à 0 transaction (exemples / plages):');
            foreach (array_slice($ranges, 0, 15) as $range) {
                $this->line('  ' . $range);
            }
            if (count($ranges) > 15) {
                $this->line('  ... et ' . (count($ranges) - 15) . ' autre(s) plage(s).');
            }
            $this->newLine();
            $this->line('Pour recalculer uniquement les dates à 0: php artisan timwe:diagnostic-backfill --only-empty --start-date=... --end-date=...');
            $this->line('Pour tout recalculer: php artisan timwe:diagnostic-backfill --force --start-date=... --end-date=...');
        }
        return self::SUCCESS;
    }

    private function compactDateRanges(array $dates): array
    {
        if (empty($dates)) {
            return [];
        }
        sort($dates);
        $ranges = [];
        $start = $dates[0];
        $prev = $dates[0];
        foreach ($dates as $d) {
            $prevCarbon = Carbon::parse($prev);
            $dCarbon = Carbon::parse($d);
            if ($dCarbon->diffInDays($prevCarbon) > 1) {
                $ranges[] = $start === $prev ? $start : "{$start} → {$prev}";
                $start = $d;
            }
            $prev = $d;
        }
        $ranges[] = $start === $prev ? $start : "{$start} → {$prev}";
        return $ranges;
    }
}
