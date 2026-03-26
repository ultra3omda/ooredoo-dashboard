<?php

namespace App\Console\Commands;

use App\Services\TimweStatsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecalculateTimweStatsFromAugust2025 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timwe:recalculate-from-august-2025
                            {--start-date=2025-08-01 : Date de début (Y-m-d)}
                            {--end-date= : Date de fin (Y-m-d), par défaut aujourd\'hui}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculer les statistiques Timwe depuis août 2025 jusqu\'à aujourd\'hui avec la nouvelle logique (totalCharged > 0)';

    protected TimweStatsService $timweStatsService;

    public function __construct(TimweStatsService $timweStatsService)
    {
        parent::__construct();
        $this->timweStatsService = $timweStatsService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $startDate = Carbon::parse($this->option('start-date'))->startOfDay();
        } catch (\Exception $e) {
            $this->error("Format de date de début invalide: {$this->option('start-date')}");
            return Command::FAILURE;
        }

        if ($this->option('end-date')) {
            try {
                $endDate = Carbon::parse($this->option('end-date'))->endOfDay();
            } catch (\Exception $e) {
                $this->error("Format de date de fin invalide: {$this->option('end-date')}");
                return Command::FAILURE;
            }
        } else {
            $endDate = Carbon::today()->endOfDay();
        }

        if ($startDate->gt($endDate)) {
            $this->error("La date de début doit être antérieure à la date de fin");
            return Command::FAILURE;
        }

        $this->info("🔄 Recalcul des statistiques Timwe...");
        $this->info("📋 Nouvelle logique: pricepointId=63980 AND mnoDeliveryCode=DELIVERED AND totalCharged > 0");
        $this->info("📅 Période: {$startDate->format('Y-m-d')} → {$endDate->format('Y-m-d')}");
        $this->newLine();

        $currentDate = $startDate->copy();
        $successCount = 0;
        $errorCount = 0;
        $results = [];
        $totalDays = $startDate->diffInDays($endDate) + 1;
        $progressBar = $this->output->createProgressBar($totalDays);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            
            if ($this->timweStatsService->calculateAndStoreStatsForDate($currentDate)) {
                $stat = \App\Models\TimweDailyStat::where('stat_date', $dateStr)->first();
                if ($stat) {
                    $results[] = [
                        'date' => $dateStr,
                        'billings' => $stat->total_billings,
                        'revenue_tnd' => $stat->revenue_tnd,
                        'status' => '✅'
                    ];
                    $successCount++;
                } else {
                    $results[] = [
                        'date' => $dateStr,
                        'billings' => 0,
                        'revenue_tnd' => 0,
                        'status' => '❌'
                    ];
                    $errorCount++;
                }
            } else {
                $results[] = [
                    'date' => $dateStr,
                    'billings' => 0,
                    'revenue_tnd' => 0,
                    'status' => '❌'
                ];
                $errorCount++;
            }
            
            $progressBar->advance();
            $currentDate->addDay();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Afficher un résumé des 10 derniers jours
        $this->info("📊 Résumé des 10 derniers jours:");
        $recentResults = array_slice($results, -10);
        $this->table(
            ['Date', 'Facturations', 'Revenu TND', 'Status'],
            array_map(function($r) {
                return [
                    $r['date'],
                    number_format($r['billings']),
                    number_format($r['revenue_tnd'], 3),
                    $r['status']
                ];
            }, $recentResults)
        );

        $this->newLine();
        $this->info("✅ Succès: {$successCount} dates");
        if ($errorCount > 0) {
            $this->error("❌ Erreurs: {$errorCount} dates");
        }

        // Statistiques globales
        $totalBillings = array_sum(array_column($results, 'billings'));
        $totalRevenue = array_sum(array_column($results, 'revenue_tnd'));
        
        $this->newLine();
        $this->info("📈 Statistiques globales:");
        $this->line("   Total facturations: " . number_format($totalBillings));
        $this->line("   Total revenu TND: " . number_format($totalRevenue, 3));
        $this->line("   Moyenne facturations/jour: " . number_format($totalBillings / max($successCount, 1), 2));

        return Command::SUCCESS;
    }
}
