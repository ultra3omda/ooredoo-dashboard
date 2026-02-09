<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BuildMLTrainingSamplesCommand extends Command
{
    protected $signature = 'ml:build-training-samples 
                            {--start-date=2021-04-12 : Date de début}
                            {--end-date= : Date de fin (défaut: aujourd\'hui - 30j)}
                            {--window=30 : Fenêtre de prédiction en jours}
                            {--lookback=90 : Période historique pour features en jours}
                            {--sample-rate=0.2 : Taux d\'échantillonnage (0.2 = 20% des dates)}
                            {--truncate : Vider la table avant}';

    protected $description = 'Construit les samples d\'entraînement ML sans data leakage (features passées → label futur)';

    public function handle()
    {
        $this->info('🏗️  Construction des samples ML (time-series)...');
        
        $startDate = Carbon::parse($this->option('start-date'));
        $endDate = $this->option('end-date') 
            ? Carbon::parse($this->option('end-date'))
            : Carbon::now()->subDays(30);
        
        $window = (int) $this->option('window');
        $lookback = (int) $this->option('lookback');
        $sampleRate = (float) $this->option('sample-rate');
        
        $this->info("📅 Période: {$startDate->toDateString()} → {$endDate->toDateString()}");
        $this->info("🔙 Lookback: {$lookback}j | 🔜 Window: {$window}j | 📊 Sample: " . ($sampleRate * 100) . "%");
        
        if ($this->option('truncate')) {
            if ($this->confirm('⚠️  Vider la table ml_training_samples ?', false)) {
                DB::statement('TRUNCATE TABLE ml_training_samples');
                $this->info('✅ Table vidée');
            }
        }
        
        if (!$this->confirm('Cette opération peut prendre du temps. Continuer ?', true)) {
            return Command::SUCCESS;
        }
        
        $this->newLine();
        $this->info('🔍 Sélection des dates échantillonnées...');
        
        // Sélectionner des dates (lundis + 1er du mois)
        $dates = [];
        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            if ($current->isMonday() || $current->day == 1) {
                if (rand(1, 100) <= ($sampleRate * 100)) {
                    $dates[] = $current->toDateString();
                }
            }
            $current->addDay();
        }
        
        $this->info('   • Dates sélectionnées : ' . count($dates));
        
        if (empty($dates)) {
            $this->error('Aucune date sélectionnée !');
            return Command::FAILURE;
        }
        
        $this->newLine();
        $this->info('📦 Construction des samples...');
        
        $bar = $this->output->createProgressBar(count($dates));
        $bar->start();
        
        $totalSamples = 0;
        
        foreach ($dates as $featureDate) {
            $count = $this->buildSamplesForDate($featureDate, $window, $lookback);
            $totalSamples += $count;
            $bar->advance();
        }
        
        $bar->finish();
        
        $this->newLine(2);
        $this->info('✅ Construction terminée !');
        $this->info('   • Samples créés : ' . number_format($totalSamples));
        
        // Stats finales
        $stats = DB::selectOne("
            SELECT 
                COUNT(*) as total,
                SUM(had_success_next_30d) as positives,
                COUNT(DISTINCT client_id) as clients,
                MIN(feature_date) as min_date,
                MAX(feature_date) as max_date
            FROM ml_training_samples
        ");
        
        if ($stats && $stats->total > 0) {
            $this->newLine();
            $this->table(['Métrique', 'Valeur'], [
                ['Total samples', number_format($stats->total)],
                ['Positifs (succès)', number_format($stats->positives) . ' (' . round($stats->positives / $stats->total * 100, 1) . '%)'],
                ['Clients uniques', number_format($stats->clients)],
                ['Période', $stats->min_date . ' → ' . $stats->max_date],
            ]);
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Construit les samples pour une date donnée
     */
    private function buildSamplesForDate(string $featureDate, int $window, int $lookback): int
    {
        $targetDate = Carbon::parse($featureDate)->addDays($window)->toDateString();
        $featureDateStart = Carbon::parse($featureDate)->subDays($lookback)->toDateString();
        $recent7d = Carbon::parse($featureDate)->subDays(7)->toDateString();
        
        // Calculer les features PASSÉES
        $pastFeatures = DB::select("
            SELECT 
                th.client_id,
                
                SUM(CASE WHEN LOWER(th.status) LIKE '%timwe%' THEN 1 ELSE 0 END) as timwe_past_attempts,
                SUM(CASE WHEN LOWER(th.status) LIKE '%timwe%' AND LOWER(th.result) LIKE '%success%' THEN 1 ELSE 0 END) as timwe_past_successes,
                
                SUM(CASE WHEN LOWER(th.status) LIKE '%eklektik%' THEN 1 ELSE 0 END) as eklektik_past_attempts,
                SUM(CASE WHEN LOWER(th.status) LIKE '%eklektik%' AND LOWER(th.result) LIKE '%success%' THEN 1 ELSE 0 END) as eklektik_past_successes,
                
                SUM(CASE WHEN LOWER(th.status) LIKE '%ooredoo%' OR LOWER(th.status) LIKE '%dgv%' THEN 1 ELSE 0 END) as ooredoo_past_attempts,
                SUM(CASE WHEN (LOWER(th.status) LIKE '%ooredoo%' OR LOWER(th.status) LIKE '%dgv%') AND LOWER(th.result) LIKE '%success%' THEN 1 ELSE 0 END) as ooredoo_past_successes,
                
                COUNT(*) as total_past_attempts,
                SUM(CASE WHEN LOWER(th.result) LIKE '%success%' THEN 1 ELSE 0 END) as total_past_successes,
                COALESCE(SUM(CASE WHEN LOWER(th.result) LIKE '%success%' THEN at.abonnement_tarifs_prix ELSE 0 END), 0) as total_past_revenue,
                
                SUM(CASE WHEN th.created_at >= ? THEN 1 ELSE 0 END) as activity_7d,
                SUM(CASE WHEN th.created_at >= ? AND LOWER(th.result) LIKE '%success%' THEN 1 ELSE 0 END) as success_7d
                
            FROM transactions_history th
            LEFT JOIN abonnement_tarifs at ON th.tarif_id = at.abonnement_tarifs_id
            WHERE th.created_at >= ? AND th.created_at < ?
            GROUP BY th.client_id
            HAVING total_past_attempts >= 2
        ", [$recent7d, $recent7d, $featureDateStart, $featureDate]);
        
        if (empty($pastFeatures)) {
            return 0;
        }
        
        // Récupérer les client_ids (filtrer les NULL)
        $clientIds = array_filter(
            array_map(fn($f) => $f->client_id, $pastFeatures),
            fn($id) => !is_null($id) && $id > 0
        );
        
        if (empty($clientIds)) {
            return 0;
        }
        
        // Calculer les labels FUTURS
        $futureLabels = DB::select("
            SELECT 
                th.client_id,
                SUM(CASE WHEN LOWER(th.result) LIKE '%success%' THEN 1 ELSE 0 END) as success_count_next_30d,
                MAX(CASE 
                    WHEN LOWER(th.result) LIKE '%success%' AND LOWER(th.status) LIKE '%timwe%' THEN 'timwe'
                    WHEN LOWER(th.result) LIKE '%success%' AND LOWER(th.status) LIKE '%eklektik%' THEN 'eklektik'
                    WHEN LOWER(th.result) LIKE '%success%' AND (LOWER(th.status) LIKE '%ooredoo%' OR LOWER(th.status) LIKE '%dgv%') THEN 'ooredoo'
                END) as best_operator_next_30d
            FROM transactions_history th
            WHERE th.client_id IN (" . implode(',', $clientIds) . ")
              AND th.created_at >= ?
              AND th.created_at < ?
            GROUP BY th.client_id
        ", [$featureDate, $targetDate]);
        
        // Mapper les futurs par client_id
        $futureMap = [];
        foreach ($futureLabels as $f) {
            $futureMap[$f->client_id] = $f;
        }
        
        // Construire et insérer les samples
        $samples = [];
        foreach ($pastFeatures as $past) {
            $future = $futureMap[$past->client_id] ?? null;
            $hadSuccess = $future && $future->success_count_next_30d > 0 ? 1 : 0;
            
            $operatorsCount = ($past->timwe_past_attempts > 0 ? 1 : 0)
                            + ($past->eklektik_past_attempts > 0 ? 1 : 0)
                            + ($past->ooredoo_past_attempts > 0 ? 1 : 0);
            
            $dominantOp = null;
            if ($operatorsCount > 0) {
                $max = max($past->timwe_past_attempts, $past->eklektik_past_attempts, $past->ooredoo_past_attempts);
                if ($past->timwe_past_attempts == $max) $dominantOp = 'timwe';
                elseif ($past->eklektik_past_attempts == $max) $dominantOp = 'eklektik';
                else $dominantOp = 'ooredoo';
            }
            
            $samples[] = [
                'client_id' => $past->client_id,
                'feature_date' => $featureDate,
                'target_date' => $targetDate,
                
                'timwe_past_attempts' => $past->timwe_past_attempts,
                'timwe_past_successes' => $past->timwe_past_successes,
                'timwe_past_failures' => $past->timwe_past_attempts - $past->timwe_past_successes,
                'timwe_past_avg_success_rate' => $past->timwe_past_attempts > 0 ? $past->timwe_past_successes / $past->timwe_past_attempts : null,
                'timwe_days_since_last_success' => null,
                
                'eklektik_past_attempts' => $past->eklektik_past_attempts,
                'eklektik_past_successes' => $past->eklektik_past_successes,
                'eklektik_past_failures' => $past->eklektik_past_attempts - $past->eklektik_past_successes,
                'eklektik_past_avg_success_rate' => $past->eklektik_past_attempts > 0 ? $past->eklektik_past_successes / $past->eklektik_past_attempts : null,
                'eklektik_days_since_last_success' => null,
                
                'ooredoo_past_attempts' => $past->ooredoo_past_attempts,
                'ooredoo_past_successes' => $past->ooredoo_past_successes,
                'ooredoo_past_failures' => $past->ooredoo_past_attempts - $past->ooredoo_past_successes,
                'ooredoo_past_avg_success_rate' => $past->ooredoo_past_attempts > 0 ? $past->ooredoo_past_successes / $past->ooredoo_past_attempts : null,
                'ooredoo_days_since_last_success' => null,
                
                'total_past_attempts' => $past->total_past_attempts,
                'total_past_successes' => $past->total_past_successes,
                'total_past_revenue' => $past->total_past_revenue,
                'consecutive_failures_before' => 0,
                'days_since_any_success' => null,
                
                'operators_used_count' => $operatorsCount,
                'dominant_operator' => $dominantOp,
                'engagement_trend' => 'stable',
                'had_recent_activity_7d' => $past->activity_7d > 0,
                'had_recent_success_7d' => $past->success_7d > 0,
                
                'had_success_next_30d' => $hadSuccess,
                'success_count_next_30d' => $future->success_count_next_30d ?? 0,
                'best_operator_next_30d' => $future->best_operator_next_30d ?? null,
                
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        if (!empty($samples)) {
            // Insertion par batch de 1000
            foreach (array_chunk($samples, 1000) as $batch) {
                DB::table('ml_training_samples')->insert($batch);
            }
        }
        
        return count($samples);
    }
}
