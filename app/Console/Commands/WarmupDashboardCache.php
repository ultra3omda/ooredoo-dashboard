<?php

namespace App\Console\Commands;

use App\Services\DashboardCacheService;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WarmupDashboardCache extends Command
{
    protected $signature = 'dashboard:cache:warmup
                            {--operators=ALL,Timwe,Ooredoo : Opérateurs à précharger (séparés par virgule)}
                            {--days=7,30,90 : Périodes en jours à précharger (séparées par virgule)}';

    protected $description = 'Précharger le cache Redis pour les périodes courantes du dashboard';

    protected DashboardCacheService $cacheService;
    protected DashboardService $dashboardService;

    public function __construct(DashboardCacheService $cacheService, DashboardService $dashboardService)
    {
        parent::__construct();
        $this->cacheService = $cacheService;
        $this->dashboardService = $dashboardService;
    }

    public function handle(): int
    {
        $this->info('🔥 Préchauffage du cache Redis pour le dashboard...');
        $this->newLine();

        // Parser les opérateurs
        $operators = array_map('trim', explode(',', $this->option('operators')));
        
        // Parser les périodes
        $daysList = array_map('intval', explode(',', $this->option('days')));
        
        // Générer les périodes
        $now = Carbon::now();
        $periods = [];
        
        foreach ($daysList as $days) {
            $periods[] = [
                $now->copy()->subDays($days - 1)->toDateString(),
                $now->toDateString()
            ];
        }
        
        // Ajouter le mois en cours et le mois précédent
        $periods[] = [
            $now->copy()->startOfMonth()->toDateString(),
            $now->toDateString()
        ];
        
        $periods[] = [
            $now->copy()->subMonth()->startOfMonth()->toDateString(),
            $now->copy()->subMonth()->endOfMonth()->toDateString()
        ];
        
        $total = count($operators) * count($periods);
        $this->info("📊 Configuration:");
        $this->line("   Opérateurs: " . implode(', ', $operators));
        $this->line("   Périodes: " . count($periods));
        $this->line("   Total combinaisons: {$total}");
        $this->newLine();
        
        $progressBar = $this->output->createProgressBar($total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%');
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($operators as $operator) {
            foreach ($periods as [$startDate, $endDate]) {
                try {
                    // Calculer les dates de comparaison (14 jours avant)
                    $comparisonEndDate = Carbon::parse($startDate)->subDay()->toDateString();
                    $comparisonStartDate = Carbon::parse($comparisonEndDate)->subDays(13)->toDateString();
                    
                    // Forcer le calcul et le cache
                    $this->dashboardService->getDashboardData(
                        $startDate,
                        $endDate,
                        $comparisonStartDate,
                        $comparisonEndDate,
                        $operator
                    );
                    
                    $successCount++;
                    
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error("Cache warmup error for {$operator} {$startDate}-{$endDate}: " . $e->getMessage());
                }
                
                $progressBar->advance();
            }
        }
        
        $progressBar->finish();
        $this->newLine(2);
        
        // Afficher les statistiques
        $stats = $this->cacheService->getStats();
        
        $this->info("✅ Préchauffage terminé!");
        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Succès', $successCount],
                ['Erreurs', $errorCount],
                ['Total clés cache', $stats['total_keys'] ?? 'N/A'],
                ['Mémoire utilisée', $stats['memory_used'] ?? 'N/A'],
                ['Taux de hit', ($stats['hit_rate'] ?? 0) . '%'],
            ]
        );
        
        return Command::SUCCESS;
    }
}
