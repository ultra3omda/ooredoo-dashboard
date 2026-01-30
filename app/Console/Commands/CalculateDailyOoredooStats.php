<?php

namespace App\Console\Commands;

use App\Services\OoredooStatsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CalculateDailyOoredooStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ooredoo:calculate-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and store Ooredoo daily statistics for yesterday (J-1)';

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
        $this->info('🔄 Calcul des statistiques quotidiennes Ooredoo/DGV...');
        
        // Calculer pour hier (J-1)
        $yesterday = Carbon::yesterday();
        
        try {
            $this->info("📅 Date: {$yesterday->format('Y-m-d')}");
            
            $this->service->calculateAndStoreStatsForDate($yesterday);
            
            $this->info('✅ Statistiques Ooredoo calculées et stockées avec succès !');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Erreur lors du calcul des statistiques: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

