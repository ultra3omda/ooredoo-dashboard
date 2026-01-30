<?php

namespace App\Console\Commands;

use App\Services\TimweStatsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateHistoricalTimweStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timwe:calculate-historical
                            {--from= : Date de début (Y-m-d)}
                            {--to= : Date de fin (Y-m-d), par défaut hier}
                            {--force : Recalculer même si les données existent}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculer les statistiques historiques Timwe et les stocker dans la table de cache';

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
        $this->info('🚀 Début du calcul des statistiques historiques Timwe...');

        // Déterminer les dates
        if ($this->option('from')) {
            try {
                $startDate = Carbon::parse($this->option('from'))->startOfDay();
            } catch (\Exception $e) {
                $this->error("Format de date invalide pour --from");
                return Command::FAILURE;
            }
        } else {
            // Par défaut, commencer à la date la plus ancienne dans client_abonnement
            $oldestDate = DB::table('client_abonnement')
                ->join('country_payments_methods', 'client_abonnement.country_payments_methods_id', '=', 'country_payments_methods.country_payments_methods_id')
                ->where('country_payments_methods.country_payments_methods_name', 'LIKE', '%timwe%')
                ->min('client_abonnement_creation');
            
            if (!$oldestDate) {
                $this->error("Aucune donnée Timwe trouvée");
                return Command::FAILURE;
            }
            
            $startDate = Carbon::parse($oldestDate)->startOfDay();
            $this->info("📅 Date de début automatique: {$startDate->format('Y-m-d')}");
        }

        if ($this->option('to')) {
            try {
                $endDate = Carbon::parse($this->option('to'))->startOfDay();
            } catch (\Exception $e) {
                $this->error("Format de date invalide pour --to");
                return Command::FAILURE;
            }
        } else {
            // Par défaut, jusqu'à hier
            $endDate = Carbon::yesterday()->startOfDay();
        }

        if ($startDate->gt($endDate)) {
            $this->error("La date de début doit être antérieure à la date de fin");
            return Command::FAILURE;
        }

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $this->info("📊 Période: du {$startDate->format('Y-m-d')} au {$endDate->format('Y-m-d')} ({$totalDays} jours)");

        if (!$this->option('force')) {
            if (!$this->confirm('Confirmer le calcul?', true)) {
                $this->info('Opération annulée');
                return Command::SUCCESS;
            }
        }

        // Créer une barre de progression
        $bar = $this->output->createProgressBar($totalDays);
        $bar->start();

        $calculated = 0;
        $skipped = 0;
        $errors = 0;
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            // Vérifier si les stats existent déjà
            if (!$this->option('force') && \App\Models\TimweDailyStat::hasStatsForDate($currentDate)) {
                $skipped++;
            } else {
                if ($this->timweStatsService->calculateAndStoreStatsForDate($currentDate)) {
                    $calculated++;
                } else {
                    $errors++;
                }
            }
            
            $bar->advance();
            $currentDate->addDay();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Calcul terminé!");
        $this->table(
            ['Statistique', 'Valeur'],
            [
                ['Total de jours', $totalDays],
                ['Calculés', $calculated],
                ['Ignorés (déjà existants)', $skipped],
                ['Erreurs', $errors],
            ]
        );

        return Command::SUCCESS;
    }
}

