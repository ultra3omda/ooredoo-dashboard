<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OptimizeMLHistoricalDataCommand extends Command
{
    protected $signature = 'ml:optimize-historical
                            {--strategy=hybrid : Stratégie (weekly, monthly, hybrid)}
                            {--dry-run : Simulation sans modification}
                            {--force : Ne pas demander de confirmation}';

    protected $description = 'Optimise ml_client_features : conserve production actuelle + échantillon historique pour ML';

    public function handle(): int
    {
        $this->info('🔧 Optimisation de l\'architecture ML...');
        
        $strategy = $this->option('strategy');
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('⚠️  MODE DRY-RUN : Aucune modification ne sera effectuée');
        }
        
        $startTime = microtime(true);
        
        // 1. ANALYSER L'ÉTAT ACTUEL
        $this->info("\n📊 Analyse de l'état actuel...");
        $stats = DB::selectOne("
            SELECT 
                (SELECT COUNT(*) FROM ml_client_features) as total_lignes,
                (SELECT COUNT(DISTINCT client_id) FROM ml_client_features) as clients_uniques,
                (SELECT COUNT(DISTINCT calculation_date) FROM ml_client_features) as dates_uniques,
                (SELECT MIN(calculation_date) FROM ml_client_features) as date_min,
                (SELECT MAX(calculation_date) FROM ml_client_features) as date_max
        ");
        
        $this->table(['Métrique', 'Valeur'], [
            ['Total lignes', number_format($stats->total_lignes)],
            ['Clients uniques', number_format($stats->clients_uniques)],
            ['Dates uniques', number_format($stats->dates_uniques)],
            ['Période', $stats->date_min . ' → ' . $stats->date_max],
        ]);
        
        // 2. STRATÉGIE D'OPTIMISATION
        $this->newLine();
        $this->info("🎯 Stratégie : {$strategy}");
        
        switch ($strategy) {
            case 'weekly':
                return $this->optimizeWeekly($stats, $dryRun);
            case 'monthly':
                return $this->optimizeMonthly($stats, $dryRun);
            case 'hybrid':
            default:
                return $this->optimizeHybrid($stats, $dryRun);
        }
    }
    
    /**
     * Stratégie Hybrid : Production (dernières) + Training (hebdomadaire)
     */
    private function optimizeHybrid($stats, bool $dryRun): int
    {
        $startTime = microtime(true);
        
        $this->info('📦 Architecture Hybride :');
        $this->line('   1. ml_client_features → Dernières features par client (production)');
        $this->line('   2. ml_client_features_training → Échantillon hebdomadaire (ML training)');
        $this->newLine();
        
        // 1. COPIER L'ÉCHANTILLON VERS LA TABLE TRAINING
        $this->info('🔄 Étape 1/3 : Création de l\'échantillon d\'entraînement...');
        
        $sampleDates = DB::select("
            SELECT DISTINCT calculation_date
            FROM ml_client_features
            WHERE DAYOFWEEK(calculation_date) = 2  -- Lundi de chaque semaine
               OR DAY(calculation_date) = 1        -- Premier du mois
               OR DAY(calculation_date) = LAST_DAY(calculation_date)  -- Dernier du mois
            ORDER BY calculation_date
        ");
        
        $this->info('   Dates sélectionnées : ' . count($sampleDates) . ' (vs ' . $stats->dates_uniques . ' originales)');
        $this->info('   Réduction : ' . round((1 - count($sampleDates) / $stats->dates_uniques) * 100, 1) . '%');
        
        if (!$dryRun) {
            $this->info('   Copie des données vers ml_client_features_training...');
            $this->info('   (Cette opération peut prendre 1-2 minutes)');
            
            // Copier TOUTES les dates en une seule requête (beaucoup plus rapide!)
            DB::statement("
                INSERT INTO ml_client_features_training 
                (client_id, calculation_date, 
                 timwe_success_rate, timwe_total_attempts, timwe_has_activity,
                 eklektik_success_rate, eklektik_total_attempts, eklektik_has_activity,
                 ooredoo_success_rate, ooredoo_total_attempts, ooredoo_has_activity,
                 total_90d_count, total_90d_sum, total_90d_avg, last_tx_90d_at,
                 timwe_90d_count, timwe_90d_sum, eklektik_90d_count, eklektik_90d_sum,
                 ooredoo_90d_count, ooredoo_90d_sum,
                 best_performing_operator, total_operators_used,
                 price_preference, preferred_frequency, client_segment,
                 payment_reliability_score, engagement_score, lifetime_value_score,
                 sample_type, created_at, updated_at)
                SELECT 
                    client_id, calculation_date,
                    timwe_success_rate, timwe_total_attempts, timwe_has_activity,
                    eklektik_success_rate, eklektik_total_attempts, eklektik_has_activity,
                    ooredoo_success_rate, ooredoo_total_attempts, ooredoo_has_activity,
                    total_90d_count, total_90d_sum, total_90d_avg, last_tx_90d_at,
                    timwe_90d_count, timwe_90d_sum, eklektik_90d_count, eklektik_90d_sum,
                    ooredoo_90d_count, ooredoo_90d_sum,
                    best_performing_operator, total_operators_used,
                    price_preference, preferred_frequency, client_segment,
                    payment_reliability_score, engagement_score, lifetime_value_score,
                    CASE 
                        WHEN DAY(calculation_date) = 1 THEN 'monthly'
                        WHEN DAY(calculation_date) = LAST_DAY(calculation_date) THEN 'key_date'
                        ELSE 'weekly'
                    END as sample_type,
                    NOW(), NOW()
                FROM ml_client_features
                WHERE DAYOFWEEK(calculation_date) = 2  -- Lundi
                   OR DAY(calculation_date) = 1        -- Premier du mois
                   OR DAY(calculation_date) = LAST_DAY(calculation_date)  -- Dernier du mois
            ");
        }
        
        $trainingCount = !$dryRun 
            ? DB::table('ml_client_features_training')->count()
            : count($sampleDates) * ($stats->total_lignes / $stats->dates_uniques);
        
        $this->info("   ✅ Table training créée : " . number_format($trainingCount) . " lignes");
        
        // 2. NETTOYER ml_client_features : GARDER SEULEMENT LES DERNIÈRES PAR CLIENT
        $this->newLine();
        $this->info('🧹 Étape 2/3 : Nettoyage de ml_client_features (garder dernières features)...');
        
        $toDelete = DB::selectOne("
            SELECT COUNT(*) as count
            FROM ml_client_features f
            WHERE NOT EXISTS (
                SELECT 1
                FROM (
                    SELECT client_id, MAX(calculation_date) as max_date
                    FROM ml_client_features
                    GROUP BY client_id
                ) latest
                WHERE f.client_id = latest.client_id 
                  AND f.calculation_date = latest.max_date
            )
        ");
        
        $this->info('   Lignes à supprimer : ' . number_format($toDelete->count));
        $this->info('   Lignes à conserver : ' . number_format($stats->clients_uniques) . ' (dernières par client)');
        
        if (!$dryRun) {
            $confirmDelete = $this->option('force') || $this->confirm('⚠️  Confirmer la suppression de ' . number_format($toDelete->count) . ' lignes ?', true);
            
            if ($confirmDelete) {
                $this->info('   Utilisation de la méthode CREATE-INSERT-SWAP (plus rapide)...');
                
                // 1. Créer une table temporaire avec la même structure
                $this->line('   - Création table temporaire...');
                DB::statement('CREATE TABLE ml_client_features_new LIKE ml_client_features');
                
                // 2. Copier uniquement les dernières features
                $this->line('   - Copie des dernières features...');
                DB::statement("
                    INSERT INTO ml_client_features_new
                    SELECT f.*
                    FROM ml_client_features f
                    INNER JOIN (
                        SELECT client_id, MAX(calculation_date) as max_date
                        FROM ml_client_features
                        GROUP BY client_id
                    ) latest ON f.client_id = latest.client_id AND f.calculation_date = latest.max_date
                ");
                
                // 3. Swap les tables (atomique)
                $this->line('   - Remplacement des tables...');
                DB::statement('RENAME TABLE ml_client_features TO ml_client_features_old, ml_client_features_new TO ml_client_features');
                
                // 4. Supprimer l'ancienne table
                $this->line('   - Suppression ancienne table...');
                DB::statement('DROP TABLE ml_client_features_old');
                
                $this->info('   ✅ Nettoyage terminé');
            } else {
                $this->warn('   ⏭️  Nettoyage annulé');
            }
        }
        
        // 3. OPTIMISER LES TABLES
        $this->newLine();
        $this->info('⚡ Étape 3/3 : Optimisation des tables...');
        
        if (!$dryRun) {
            $this->line('   Analyse de ml_client_features...');
            DB::statement('ANALYZE TABLE ml_client_features');
            
            $this->line('   Analyse de ml_client_features_training...');
            DB::statement('ANALYZE TABLE ml_client_features_training');
            
            $this->line('   Optimisation des tables...');
            DB::statement('OPTIMIZE TABLE ml_client_features');
            DB::statement('OPTIMIZE TABLE ml_client_features_training');
            
            $this->info('   ✅ Optimisation terminée');
        }
        
        // 4. RAPPORT FINAL
        $this->newLine();
        $this->info('=' . str_repeat('=', 70));
        $this->info('✅ ARCHITECTURE HYBRIDE CRÉÉE !');
        $this->info('=' . str_repeat('=', 70));
        
        if (!$dryRun) {
            $prodCount = DB::table('ml_client_features')->count();
            $trainCount = DB::table('ml_client_features_training')->count();
            
            $this->table(['Table', 'Lignes', 'Usage'], [
                ['ml_client_features', number_format($prodCount), '🚀 Production (prédictions temps réel)'],
                ['ml_client_features_training', number_format($trainCount), '🎓 Entraînement ML (échantillon)'],
                ['ml_client_features_current (vue)', number_format($prodCount), '📊 Alias pour production'],
            ]);
        } else {
            $this->table(['Table', 'Lignes Estimées', 'Usage'], [
                ['ml_client_features', number_format($stats->clients_uniques), '🚀 Production'],
                ['ml_client_features_training', number_format($trainingCount), '🎓 Entraînement ML'],
            ]);
        }
        
        $reduction = round((1 - ($stats->clients_uniques + $trainingCount) / $stats->total_lignes) * 100, 1);
        
        $this->newLine();
        $this->info('📊 Réduction totale : ' . $reduction . '%');
        $this->info('💾 Avant : ' . number_format($stats->total_lignes) . ' lignes');
        $this->info('💾 Après : ' . number_format($stats->clients_uniques + $trainingCount) . ' lignes');
        
        $this->newLine();
        $this->info('📖 Documentation : docs/ML_VOLUMETRIE_ET_SCALABILITE.md');
        
        $duration = round(microtime(true) - $startTime, 2);
        $this->info("⏱️  Temps total : {$duration}s");
        
        return 0;
    }
    
    /**
     * Stratégie Weekly : Garder seulement 1 date par semaine
     */
    private function optimizeWeekly($stats, bool $dryRun): int
    {
        $this->info('📅 Stratégie Hebdomadaire : Garder 1 date par semaine (lundis)');
        
        // À implémenter si nécessaire
        $this->warn('⚠️  Cette stratégie n\'est pas encore implémentée. Utilisez --strategy=hybrid');
        
        return 1;
    }
    
    /**
     * Stratégie Monthly : Garder seulement fin de mois
     */
    private function optimizeMonthly($stats, bool $dryRun): int
    {
        $this->info('📅 Stratégie Mensuelle : Garder fin de chaque mois');
        
        // À implémenter si nécessaire
        $this->warn('⚠️  Cette stratégie n\'est pas encore implémentée. Utilisez --strategy=hybrid');
        
        return 1;
    }
}
