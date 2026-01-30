<?php

namespace App\Console\Commands;

use App\Services\TimweStatsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CalculateDailyTimweStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timwe:calculate-daily
                            {--date= : Date à calculer (Y-m-d), par défaut hier}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculer les statistiques Timwe pour une date spécifique (par défaut J-1)';

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
        if ($this->option('date')) {
            try {
                $date = Carbon::parse($this->option('date'))->startOfDay();
            } catch (\Exception $e) {
                $this->error("Format de date invalide: {$this->option('date')}");
                return Command::FAILURE;
            }
        } else {
            // Par défaut, calculer pour hier
            $date = Carbon::yesterday()->startOfDay();
        }

        $this->info("🔄 Calcul des statistiques Timwe pour le {$date->format('Y-m-d')}...");

        if ($this->timweStatsService->calculateAndStoreStatsForDate($date)) {
            $this->info("✅ Statistiques calculées avec succès!");
            
            // Afficher un résumé
            $stat = \App\Models\TimweDailyStat::where('stat_date', $date->format('Y-m-d'))->first();
            if ($stat) {
                $this->table(
                    ['Métrique', 'Valeur'],
                    [
                        ['Date', $stat->stat_date->format('Y-m-d')],
                        ['Nouveaux abonnements', number_format($stat->new_subscriptions)],
                        ['Désabonnements', number_format($stat->unsubscriptions)],
                        ['Simchurn', number_format($stat->simchurn)],
                        ['Abonnements actifs', number_format($stat->active_subscriptions)],
                        ['Total facturations', number_format($stat->total_billings)],
                        ['Taux de facturation', $stat->billing_rate . '%'],
                        ['Revenu TND', number_format($stat->revenue_tnd, 3)],
                        ['Revenu USD', number_format($stat->revenue_usd, 3)],
                        ['Total clients', number_format($stat->total_clients)],
                    ]
                );
            }
            
            return Command::SUCCESS;
        } else {
            $this->error("❌ Échec du calcul des statistiques");
            return Command::FAILURE;
        }
    }
}

