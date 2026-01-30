<?php

namespace App\Console\Commands;

use App\Services\OoredooStatsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CalculateHistoricalOoredooStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ooredoo:calculate-historical 
                            {--start-date= : Date de début (Y-m-d), par défaut il y a 365 jours}
                            {--end-date= : Date de fin (Y-m-d), par défaut hier}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and store historical Ooredoo daily statistics for a date range';

    private OoredooStatsService $service;

    public function __construct(OoredooStatsService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Calcul des statistiques historiques Ooredoo/DGV...');
        
        // Dates par défaut
        $startDate = $this->option('start-date') 
            ? Carbon::parse($this->option('start-date'))
            : Carbon::now()->subDays(365);
            
        $endDate = $this->option('end-date')
            ? Carbon::parse($this->option('end-date'))
            : Carbon::yesterday();
        
        $this->info("📅 Période: {$startDate->format('Y-m-d')} → {$endDate->format('Y-m-d')}");
        
        $totalDays = $startDate->diffInDays($endDate) + 1;
        $this->info("📊 Total: {$totalDays} jours à calculer");
        
        $currentDate = $startDate->copy();
        $processed = 0;
        $errors = 0;
        
        $progressBar = $this->output->createProgressBar($totalDays);
        $progressBar->start();
        
        while ($currentDate->lte($endDate)) {
            try {
                $this->service->calculateAndStoreStatsForDate($currentDate);
                $processed++;
            } catch (\Exception $e) {
                $this->error("\n❌ Erreur pour {$currentDate->format('Y-m-d')}: " . $e->getMessage());
                $errors++;
            }
            
            $currentDate->addDay();
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        
        $this->info("✅ Traitement terminé !");
        $this->info("   📊 Jours traités: {$processed}");
        if ($errors > 0) {
            $this->warn("   ⚠️  Erreurs: {$errors}");
        }
        
        return Command::SUCCESS;
    }
}

