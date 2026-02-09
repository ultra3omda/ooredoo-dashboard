<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RefreshMLTrainingDataCommand extends Command
{
    protected $signature = 'ml:refresh-training 
                            {--from-date= : Date de début (défaut: date max actuelle training)}
                            {--full : Vider et reconstruire complètement}';

    protected $description = 'Rafraîchit ml_client_features_training avec les dernières données';

    public function handle()
    {
        $this->info('🔄 Rafraîchissement des données d\'entraînement ML...');
        
        $full = $this->option('full');
        
        if ($full) {
            return $this->fullRefresh();
        }
        
        return $this->incrementalRefresh();
    }
    
    /**
     * Rafraîchissement complet (recopie tout depuis ml_client_features)
     */
    private function fullRefresh(): int
    {
        $this->warn('⚠️  Mode FULL : Reconstruction complète de ml_client_features_training');
        
        if (!$this->confirm('Confirmer la suppression et reconstruction complète ?')) {
            $this->info('Annulé.');
            return Command::SUCCESS;
        }
        
        $this->info('1/3 : Vidage de ml_client_features_training...');
        DB::table('ml_client_features_training')->truncate();
        
        $this->info('2/3 : Copie échantillon (lundis + 1er/dernier du mois)...');
        DB::statement('SET SESSION innodb_lock_wait_timeout = 600');
        
        $start = microtime(true);
        DB::statement("
            INSERT INTO ml_client_features_training 
            (client_id, calculation_date, timwe_success_rate, timwe_total_attempts, timwe_has_activity,
             eklektik_success_rate, eklektik_total_attempts, eklektik_has_activity,
             ooredoo_success_rate, ooredoo_total_attempts, ooredoo_has_activity,
             total_90d_count, total_90d_sum, total_90d_avg, last_tx_90d_at,
             timwe_90d_count, timwe_90d_sum, eklektik_90d_count, eklektik_90d_sum,
             ooredoo_90d_count, ooredoo_90d_sum, best_performing_operator, total_operators_used,
             price_preference, preferred_frequency, client_segment,
             payment_reliability_score, engagement_score, lifetime_value_score,
             sample_type, created_at, updated_at)
            SELECT client_id, calculation_date, timwe_success_rate, timwe_total_attempts, timwe_has_activity,
             eklektik_success_rate, eklektik_total_attempts, eklektik_has_activity,
             ooredoo_success_rate, ooredoo_total_attempts, ooredoo_has_activity,
             total_90d_count, total_90d_sum, total_90d_avg, last_tx_90d_at,
             timwe_90d_count, timwe_90d_sum, eklektik_90d_count, eklektik_90d_sum,
             ooredoo_90d_count, ooredoo_90d_sum, best_performing_operator, total_operators_used,
             price_preference, preferred_frequency, client_segment,
             payment_reliability_score, engagement_score, lifetime_value_score,
             CASE 
                WHEN DAY(calculation_date) = 1 THEN 'monthly' 
                WHEN DAY(calculation_date) = LAST_DAY(calculation_date) THEN 'key_date' 
                ELSE 'weekly' 
             END, NOW(), NOW()
            FROM ml_client_features
            WHERE DAYOFWEEK(calculation_date) = 2 
               OR DAY(calculation_date) = 1 
               OR DAY(calculation_date) = LAST_DAY(calculation_date)
        ");
        
        $duration = round(microtime(true) - $start, 2);
        $count = DB::table('ml_client_features_training')->count();
        
        $this->info("3/3 : Optimisation...");
        DB::statement('ANALYZE TABLE ml_client_features_training');
        DB::statement('OPTIMIZE TABLE ml_client_features_training');
        
        $this->newLine();
        $this->info("✅ Reconstruction terminée !");
        $this->info("   • " . number_format($count) . " lignes copiées en {$duration}s");
        
        return Command::SUCCESS;
    }
    
    /**
     * Rafraîchissement incrémental (ajoute seulement les nouvelles dates)
     */
    private function incrementalRefresh(): int
    {
        $this->info('Mode INCRÉMENTAL : Ajout des nouvelles dates uniquement');
        
        // Trouver la dernière date dans training
        $lastDate = DB::table('ml_client_features_training')->max('calculation_date');
        
        if (!$lastDate) {
            $this->warn('⚠️  Table training vide. Utilisez --full pour reconstruction complète.');
            return Command::FAILURE;
        }
        
        $fromDate = $this->option('from-date') ?? Carbon::parse($lastDate)->addDay()->toDateString();
        $toDate = DB::table('ml_client_features')->max('calculation_date');
        
        $this->info("📅 Ajout des dates : {$fromDate} → {$toDate}");
        
        // Compter les nouvelles dates à ajouter
        $newDates = DB::select("
            SELECT COUNT(DISTINCT calculation_date) as count
            FROM ml_client_features
            WHERE calculation_date >= ? 
              AND calculation_date <= ?
              AND (DAYOFWEEK(calculation_date) = 2 
                   OR DAY(calculation_date) = 1 
                   OR DAY(calculation_date) = LAST_DAY(calculation_date))
        ", [$fromDate, $toDate]);
        
        $datesToAdd = $newDates[0]->count ?? 0;
        
        if ($datesToAdd == 0) {
            $this->info('✅ Aucune nouvelle date à ajouter. Training déjà à jour !');
            return Command::SUCCESS;
        }
        
        $this->info("➕ {$datesToAdd} nouvelles dates à ajouter...");
        
        DB::statement('SET SESSION innodb_lock_wait_timeout = 600');
        
        $start = microtime(true);
        DB::statement("
            INSERT IGNORE INTO ml_client_features_training 
            (client_id, calculation_date, timwe_success_rate, timwe_total_attempts, timwe_has_activity,
             eklektik_success_rate, eklektik_total_attempts, eklektik_has_activity,
             ooredoo_success_rate, ooredoo_total_attempts, ooredoo_has_activity,
             total_90d_count, total_90d_sum, total_90d_avg, last_tx_90d_at,
             timwe_90d_count, timwe_90d_sum, eklektik_90d_count, eklektik_90d_sum,
             ooredoo_90d_count, ooredoo_90d_sum, best_performing_operator, total_operators_used,
             price_preference, preferred_frequency, client_segment,
             payment_reliability_score, engagement_score, lifetime_value_score,
             sample_type, created_at, updated_at)
            SELECT client_id, calculation_date, timwe_success_rate, timwe_total_attempts, timwe_has_activity,
             eklektik_success_rate, eklektik_total_attempts, eklektik_has_activity,
             ooredoo_success_rate, ooredoo_total_attempts, ooredoo_has_activity,
             total_90d_count, total_90d_sum, total_90d_avg, last_tx_90d_at,
             timwe_90d_count, timwe_90d_sum, eklektik_90d_count, eklektik_90d_sum,
             ooredoo_90d_count, ooredoo_90d_sum, best_performing_operator, total_operators_used,
             price_preference, preferred_frequency, client_segment,
             payment_reliability_score, engagement_score, lifetime_value_score,
             CASE 
                WHEN DAY(calculation_date) = 1 THEN 'monthly' 
                WHEN DAY(calculation_date) = LAST_DAY(calculation_date) THEN 'key_date' 
                ELSE 'weekly' 
             END, NOW(), NOW()
            FROM ml_client_features
            WHERE calculation_date >= ? 
              AND calculation_date <= ?
              AND (DAYOFWEEK(calculation_date) = 2 
                   OR DAY(calculation_date) = 1 
                   OR DAY(calculation_date) = LAST_DAY(calculation_date))
        ", [$fromDate, $toDate]);
        
        $duration = round(microtime(true) - $start, 2);
        
        $this->info('Optimisation...');
        DB::statement('ANALYZE TABLE ml_client_features_training');
        
        $newTotal = DB::table('ml_client_features_training')->count();
        $newMax = DB::table('ml_client_features_training')->max('calculation_date');
        
        $this->newLine();
        $this->info("✅ Rafraîchissement terminé en {$duration}s !");
        $this->info("   • Total lignes: " . number_format($newTotal));
        $this->info("   • Dernière date: {$newMax}");
        
        return Command::SUCCESS;
    }
}
