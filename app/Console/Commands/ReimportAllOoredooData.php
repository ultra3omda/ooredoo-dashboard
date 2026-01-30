<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class ReimportAllOoredooData extends Command
{
    protected $signature = 'ooredoo:reimport-all 
                          {--clean : Nettoyer toutes les données avant réimport}';
    
    protected $description = 'Réimporter toutes les données Ooredoo (officielles DGV + calculées)';

    public function handle()
    {
        $this->info("═══════════════════════════════════════════════════════════════════");
        $this->info("        RÉIMPORT COMPLET DES DONNÉES OOREDOO");
        $this->info("═══════════════════════════════════════════════════════════════════\n");

        // Étape 1 : Nettoyage (optionnel)
        if ($this->option('clean')) {
            if ($this->confirm('⚠️  Voulez-vous vraiment supprimer TOUTES les données existantes ?', false)) {
                $this->info("\n🗑️  Nettoyage des données existantes...");
                $deleted = DB::table('ooredoo_daily_stats')->delete();
                $this->info("✅ $deleted lignes supprimées\n");
            } else {
                $this->warn("❌ Nettoyage annulé\n");
            }
        }

        // Étape 2 : Import des données officielles DGV (juin 2021 - mars 2025)
        $this->info("📥 ÉTAPE 1/2 : Import des données officielles DGV");
        $this->info("─────────────────────────────────────────────────────────────────\n");
        
        $exitCode = Artisan::call('ooredoo:import-dgv-official');
        
        if ($exitCode === 0) {
            $this->info("\n✅ Données officielles DGV importées avec succès\n");
        } else {
            $this->error("\n❌ Erreur lors de l'import des données DGV");
            return 1;
        }

        // Étape 3 : Calcul des données pour les périodes sans données officielles
        $this->info("🔢 ÉTAPE 2/2 : Calcul des données pour les périodes restantes");
        $this->info("─────────────────────────────────────────────────────────────────\n");

        // À partir d'avril 2025 jusqu'à aujourd'hui
        $startDate = Carbon::parse('2025-04-01');
        $endDate = Carbon::today();

        $this->info("📅 Période : {$startDate->format('Y-m-d')} → {$endDate->format('Y-m-d')}");
        $this->info("📊 Total jours : " . $startDate->diffInDays($endDate) . "\n");

        if ($this->confirm('Voulez-vous calculer les données pour cette période ?', true)) {
            $exitCode = Artisan::call('ooredoo:calculate-historical', [
                '--start-date' => $startDate->format('Y-m-d'),
                '--end-date' => $endDate->format('Y-m-d'),
            ]);

            if ($exitCode === 0) {
                $this->info("\n✅ Calcul des données terminé avec succès\n");
            } else {
                $this->error("\n❌ Erreur lors du calcul des données");
                return 1;
            }
        } else {
            $this->warn("⏭️  Calcul des données ignoré\n");
        }

        // Statistiques finales
        $this->info("═══════════════════════════════════════════════════════════════════");
        $this->info("                    STATISTIQUES FINALES");
        $this->info("═══════════════════════════════════════════════════════════════════\n");

        $stats = DB::select("
            SELECT 
                data_source,
                COUNT(*) as nb_jours,
                MIN(stat_date) as premiere_date,
                MAX(stat_date) as derniere_date,
                SUM(total_billings) as total_facturations,
                SUM(revenue_tnd) as total_revenus
            FROM ooredoo_daily_stats
            GROUP BY data_source
        ");

        foreach ($stats as $stat) {
            $this->info("📊 SOURCE: " . strtoupper($stat->data_source));
            $this->info("   Nombre de jours: " . number_format($stat->nb_jours));
            $this->info("   Période: {$stat->premiere_date} → {$stat->derniere_date}");
            $this->info("   Total facturations: " . number_format($stat->total_facturations));
            $this->info("   Total revenus: " . number_format($stat->total_revenus, 2) . " TND\n");
        }

        $totalStats = DB::select("
            SELECT 
                COUNT(*) as nb_jours,
                MIN(stat_date) as premiere_date,
                MAX(stat_date) as derniere_date,
                SUM(total_billings) as total_facturations,
                SUM(revenue_tnd) as total_revenus
            FROM ooredoo_daily_stats
        ")[0];

        $this->info("═══════════════════════════════════════════════════════════════════");
        $this->info("🎯 TOTAL GÉNÉRAL");
        $this->info("   Nombre de jours: " . number_format($totalStats->nb_jours));
        $this->info("   Période: {$totalStats->premiere_date} → {$totalStats->derniere_date}");
        $this->info("   Total facturations: " . number_format($totalStats->total_facturations));
        $this->info("   Total revenus: " . number_format($totalStats->total_revenus, 2) . " TND");
        $this->info("═══════════════════════════════════════════════════════════════════\n");

        $this->info("✅ Réimport complet terminé avec succès !");

        return 0;
    }
}

