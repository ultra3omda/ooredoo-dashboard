<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TxDailyAggMaintenanceCommand extends Command
{
    protected $signature = 'ml:tx-daily-maintenance
                            {--retention-days=120 : Nombre de jours à conserver}
                            {--dry-run : Simulation sans suppression}
                            {--vacuum : Optimise les tables après nettoyage}';

    protected $description = 'Maintenance et nettoyage des tables d\'agrégation ML';

    public function handle(): int
    {
        $startTime = microtime(true);
        $retentionDays = (int) $this->option('retention-days');
        $dryRun = $this->option('dry-run');
        $vacuum = $this->option('vacuum');

        $cutoffDate = Carbon::now()->subDays($retentionDays)->format('Y-m-d');

        $this->info("🧹 Maintenance des agrégats ML");
        $this->info("   Rétention: {$retentionDays} jours");
        $this->info("   Cutoff date: {$cutoffDate}");
        if ($dryRun) {
            $this->warn("   ⚠️  DRY RUN - Aucune suppression");
        }
        $this->newLine();

        // 1) NETTOYAGE tx_daily_agg
        $this->info("📦 Table: tx_daily_agg");
        
        $countToDelete = DB::table('tx_daily_agg')
            ->where('day', '<', $cutoffDate)
            ->count();

        $this->info("   📊 Lignes à supprimer: {$countToDelete}");

        if ($countToDelete > 0 && !$dryRun) {
            $deleteStartTime = microtime(true);
            
            $deleted = DB::table('tx_daily_agg')
                ->where('day', '<', $cutoffDate)
                ->delete();
            
            $deleteTime = round((microtime(true) - $deleteStartTime) * 1000, 2);
            $this->info("   ✅ Supprimé: {$deleted} lignes en {$deleteTime}ms");
        } elseif ($dryRun) {
            $this->warn("   ⚠️  DRY RUN - Skip suppression");
        } else {
            $this->info("   ✓ Aucune ligne à supprimer");
        }

        // 2) STATISTIQUES tx_daily_agg
        $stats = DB::select("
            SELECT 
                MIN(day) as oldest_day,
                MAX(day) as newest_day,
                COUNT(*) as total_rows,
                COUNT(DISTINCT client_id) as unique_clients,
                COUNT(DISTINCT status) as unique_statuses,
                SUM(tx_count) as total_transactions,
                SUM(amount_sum) as total_amount,
                ROUND(AVG(tx_count), 2) as avg_tx_per_row
            FROM tx_daily_agg
        ");

        if (!empty($stats)) {
            $stat = $stats[0];
            $this->newLine();
            $this->info("📊 Statistiques tx_daily_agg:");
            $this->info("   Période: {$stat->oldest_day} → {$stat->newest_day}");
            $this->info("   Total lignes: " . number_format($stat->total_rows));
            $this->info("   Clients uniques: " . number_format($stat->unique_clients));
            $this->info("   Status uniques: {$stat->unique_statuses}");
            $this->info("   Transactions totales: " . number_format($stat->total_transactions));
            $this->info("   Montant total: " . number_format($stat->total_amount, 2) . " TND");
            $this->info("   Moy tx/ligne: {$stat->avg_tx_per_row}");
        }

        // 3) STATISTIQUES ml_job_state
        $this->newLine();
        $this->info("🔖 État des jobs:");
        
        $jobs = DB::table('ml_job_state')->get();
        foreach ($jobs as $job) {
            $this->info("   • {$job->job_name}:");
            $this->info("     - Last ID: " . number_format($job->last_processed_id));
            $this->info("     - Last run: {$job->last_processed_at}");
            
            if ($job->job_name === 'tx_daily_ingest') {
                // Compter les transactions non traitées
                $pending = DB::table('transactions_history')
                    ->where('transaction_history_id', '>', $job->last_processed_id)
                    ->count();
                
                $this->info("     - Pending: " . number_format($pending) . " transactions");
            }
        }

        // 4) OPTIMISATION DES TABLES (VACUUM)
        if ($vacuum && !$dryRun) {
            $this->newLine();
            $this->info("⚡ Optimisation des tables...");
            
            $optimizeStartTime = microtime(true);
            DB::statement('OPTIMIZE TABLE tx_daily_agg');
            $optimizeTime = round((microtime(true) - $optimizeStartTime) * 1000, 2);
            
            $this->info("   ✅ tx_daily_agg optimisée en {$optimizeTime}ms");
        }

        // 5) VÉRIFICATION DES INDEX
        $this->newLine();
        $this->info("🔍 Vérification des index:");
        
        $indexes = DB::select("
            SHOW INDEX FROM tx_daily_agg
        ");
        
        foreach ($indexes as $idx) {
            $this->info("   • {$idx->Key_name} ({$idx->Column_name})");
        }

        $totalTime = round(microtime(true) - $startTime, 2);
        
        $this->newLine();
        $this->info("=" .str_repeat("=", 60));
        $this->info("✅ Maintenance terminée !");
        $this->info("   ⏱️  Temps total: {$totalTime}s");
        $this->info("=" .str_repeat("=", 60));

        return 0;
    }
}
