<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OoredooStatsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UpdateOoredooDailyStats extends Command
{
    protected $signature = 'ooredoo:update-daily-stats 
                          {--date= : Date à traiter (YYYY-MM-DD). Par défaut: hier}
                          {--force : Forcer le recalcul même si les données existent déjà}';
    
    protected $description = 'Mettre à jour les statistiques quotidiennes Ooredoo (à lancer via CRON)';

    private $ooredooService;

    public function __construct(OoredooStatsService $ooredooService)
    {
        parent::__construct();
        $this->ooredooService = $ooredooService;
    }

    public function handle()
    {
        $startTime = microtime(true);
        
        // Déterminer la date à traiter
        $dateStr = $this->option('date');
        $date = $dateStr ? Carbon::parse($dateStr) : Carbon::yesterday();
        
        $this->info("═══════════════════════════════════════════════════════════════════");
        $this->info("        MISE À JOUR QUOTIDIENNE OOREDOO/DGV");
        $this->info("═══════════════════════════════════════════════════════════════════\n");
        $this->info("📅 Date: {$date->format('Y-m-d')} ({$date->translatedFormat('l d F Y')})");
        $this->info("⏰ Démarrage: " . now()->format('H:i:s') . "\n");

        try {
            // Vérifier si la date est dans la période des données officielles DGV
            $dgvEndDate = Carbon::parse('2025-03-31');
            
            if ($date <= $dgvEndDate) {
                $this->warn("⚠️  ATTENTION: La date {$date->format('Y-m-d')} est dans la période des données officielles DGV");
                $this->warn("   Les données officielles DGV ne doivent pas être écrasées.");
                
                if (!$this->option('force')) {
                    $this->error("❌ Traitement annulé. Utilisez --force pour forcer le recalcul.\n");
                    return 1;
                }
                
                $this->warn("   Mode --force activé, recalcul en cours...\n");
            }

            // Calculer et stocker les statistiques
            $this->info("🔄 Calcul des statistiques...");
            
            $this->ooredooService->calculateAndStoreStatsForDate($date);
            
            $this->info("✅ Statistiques calculées avec succès !\n");

            // Afficher un résumé
            $this->displaySummary($date);

            $duration = round(microtime(true) - $startTime, 2);
            
            $this->info("\n═══════════════════════════════════════════════════════════════════");
            $this->info("✅ Mise à jour terminée avec succès !");
            $this->info("⏱️  Durée: {$duration}s");
            $this->info("═══════════════════════════════════════════════════════════════════\n");

            // Log pour suivi
            Log::info("CRON Ooredoo - Mise à jour quotidienne réussie", [
                'date' => $date->format('Y-m-d'),
                'duration' => $duration,
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("\n❌ ERREUR lors de la mise à jour:");
            $this->error("   " . $e->getMessage());
            $this->error("\n" . $e->getTraceAsString());

            Log::error("CRON Ooredoo - Erreur lors de la mise à jour quotidienne", [
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    private function displaySummary(Carbon $date)
    {
        $stats = \App\Models\OoredooDailyStat::where('stat_date', $date->format('Y-m-d'))->first();

        if (!$stats) {
            $this->warn("⚠️  Aucune statistique trouvée pour cette date.");
            return;
        }

        $this->info("─────────────────────────────────────────────────────────────────");
        $this->info("📊 RÉSUMÉ DES STATISTIQUES");
        $this->info("─────────────────────────────────────────────────────────────────");
        $this->info("   📈 Nouvelles inscriptions: " . number_format($stats->new_subscriptions));
        $this->info("   📉 Désabonnements: " . number_format($stats->unsubscriptions));
        $this->info("   👥 Abonnements actifs: " . number_format($stats->active_subscriptions));
        $this->info("   👤 Total clients: " . number_format($stats->total_clients));
        $this->info("   💳 Facturations: " . number_format($stats->total_billings));
        $this->info("   📊 Taux de facturation: " . number_format($stats->billing_rate, 2) . "%");
        $this->info("   💰 Revenus: " . number_format($stats->revenue_tnd, 2) . " TND");
        $this->info("   📦 Source: " . strtoupper($stats->data_source));
        $this->info("─────────────────────────────────────────────────────────────────");
    }
}

