<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyzeOperatorPreferencesCommand extends Command
{
    protected $signature = 'ml:analyze-preferences {--days=30 : Période d\'analyse en jours}
                                                   {--export : Exporter en CSV}';

    protected $description = 'Analyse les préférences clients entre opérateurs et types d\'offres';

    public function handle()
    {
        $days = (int)$this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);
        
        $this->info("🔍 Analyse des préférences clients ($days derniers jours)...");
        
        // 1. Répartition générale des clients par opérateur
        $this->analyzeOperatorDistribution($cutoffDate);
        
        // 2. Performance comparée par opérateur  
        $this->analyzeOperatorPerformance($cutoffDate);
        
        // 3. Patterns de préférence prix/fréquence
        $this->analyzePriceFrequencyPreferences($cutoffDate);
        
        // 4. Clients multi-opérateur
        $this->analyzeMultiOperatorBehavior($cutoffDate);
        
        // 5. Recommandations stratégiques
        $this->generateStrategicRecommendations();
        
        if ($this->option('export')) {
            $this->exportAnalysis($cutoffDate);
        }
        
        return Command::SUCCESS;
    }

    private function analyzeOperatorDistribution(Carbon $cutoffDate): void
    {
        $this->info('📊 1. Répartition des clients par opérateur:');
        
        // CORRECTION v2: Utiliser une subquery pour sélectionner les dernières features
        $distribution = DB::select("
            SELECT 
                SUM(f.timwe_has_activity) as timwe_users,
                AVG(f.timwe_success_rate) as timwe_avg_success,
                SUM(f.eklektik_has_activity) as eklektik_users,
                AVG(f.eklektik_success_rate) as eklektik_avg_success,
                SUM(f.ooredoo_has_activity) as ooredoo_users,
                AVG(f.ooredoo_success_rate) as ooredoo_avg_success,
                COUNT(DISTINCT f.client_id) as total_clients
            FROM ml_client_features f
            INNER JOIN (
                SELECT client_id, MAX(calculation_date) as max_date
                FROM ml_client_features
                WHERE calculation_date >= ?
                GROUP BY client_id
            ) latest ON f.client_id = latest.client_id AND f.calculation_date = latest.max_date
        ", [$cutoffDate->toDateString()]);
        
        if (!empty($distribution)) {
            $d = $distribution[0];
            $this->table(['Opérateur', 'Clients Actifs', '% Total', 'Taux Succès Moyen'], [
                ['Timwe (3.0 TND mensuel)', number_format($d->timwe_users), round($d->timwe_users / $d->total_clients * 100, 1) . '%', round($d->timwe_avg_success * 100, 1) . '%'],
                ['Eklektik (0.3 TND quotidien)', number_format($d->eklektik_users), round($d->eklektik_users / $d->total_clients * 100, 1) . '%', round($d->eklektik_avg_success * 100, 1) . '%'],
                ['Ooredoo/DGV (0.3 TND quotidien)', number_format($d->ooredoo_users), round($d->ooredoo_users / $d->total_clients * 100, 1) . '%', round($d->ooredoo_avg_success * 100, 1) . '%'],
                ['Total', number_format($d->total_clients), '100%', '-']
            ]);
        }
    }

    private function analyzeOperatorPerformance(Carbon $cutoffDate): void
    {
        $this->newLine();
        $this->info('🏆 2. Performance comparée par opérateur:');
        
        // CORRECTION v2: Utiliser INNER JOIN pour sélectionner uniquement les dernières features
        $performance = DB::select("
            WITH latest_features AS (
                SELECT f.*
                FROM ml_client_features f
                INNER JOIN (
                    SELECT client_id, MAX(calculation_date) as max_date
                    FROM ml_client_features
                    WHERE calculation_date >= ?
                    GROUP BY client_id
                ) latest ON f.client_id = latest.client_id AND f.calculation_date = latest.max_date
            )
            SELECT 
                'Premium (>70% succès)' as segment,
                SUM(CASE WHEN timwe_success_rate > 0.7 THEN 1 ELSE 0 END) as timwe_count,
                SUM(CASE WHEN eklektik_success_rate > 0.7 THEN 1 ELSE 0 END) as eklektik_count,
                SUM(CASE WHEN ooredoo_success_rate > 0.7 THEN 1 ELSE 0 END) as ooredoo_count
            FROM latest_features
            
            UNION ALL
            
            SELECT 
                'Regular (30-70% succès)' as segment,
                SUM(CASE WHEN timwe_success_rate BETWEEN 0.3 AND 0.7 THEN 1 ELSE 0 END) as timwe_count,
                SUM(CASE WHEN eklektik_success_rate BETWEEN 0.3 AND 0.7 THEN 1 ELSE 0 END) as eklektik_count,
                SUM(CASE WHEN ooredoo_success_rate BETWEEN 0.3 AND 0.7 THEN 1 ELSE 0 END) as ooredoo_count
            FROM latest_features
            
            UNION ALL
            
            SELECT 
                'Struggling (<30% succès)' as segment,
                SUM(CASE WHEN timwe_success_rate < 0.3 THEN 1 ELSE 0 END) as timwe_count,
                SUM(CASE WHEN eklektik_success_rate < 0.3 THEN 1 ELSE 0 END) as eklektik_count,
                SUM(CASE WHEN ooredoo_success_rate < 0.3 THEN 1 ELSE 0 END) as ooredoo_count
            FROM latest_features
        ", [$cutoffDate->toDateString()]);
        
        $tableData = [];
        foreach ($performance as $p) {
            $tableData[] = [
                $p->segment,
                number_format($p->timwe_count),
                number_format($p->eklektik_count), 
                number_format($p->ooredoo_count)
            ];
        }
        
        $this->table(['Segment Performance', 'Timwe', 'Eklektik', 'Ooredoo/DGV'], $tableData);
    }

    private function analyzePriceFrequencyPreferences(Carbon $cutoffDate): void
    {
        $this->newLine();
        $this->info('💰 3. Préférences prix et fréquence:');
        
        // CORRECTION v2: Utiliser INNER JOIN
        $preferences = DB::select("
            SELECT 
                SUM(f.prefers_low_price) as low_price_users,
                SUM(f.prefers_high_price) as high_price_users,
                SUM(f.prefers_daily_offers) as daily_users,
                SUM(f.prefers_monthly_offers) as monthly_users,
                SUM(CASE WHEN f.preferred_frequency = 'mixed' THEN 1 ELSE 0 END) as flexible_users,
                COUNT(DISTINCT f.client_id) as total
            FROM ml_client_features f
            INNER JOIN (
                SELECT client_id, MAX(calculation_date) as max_date
                FROM ml_client_features
                WHERE calculation_date >= ?
                GROUP BY client_id
            ) latest ON f.client_id = latest.client_id AND f.calculation_date = latest.max_date
        ", [$cutoffDate->toDateString()]);
        
        if (!empty($preferences)) {
            $p = $preferences[0];
            $this->table(['Préférence', 'Clients', '% Total'], [
                ['Prix bas (≤1.0 TND)', number_format($p->low_price_users), round($p->low_price_users / $p->total * 100, 1) . '%'],
                ['Prix élevé (>1.0 TND)', number_format($p->high_price_users), round($p->high_price_users / $p->total * 100, 1) . '%'],
                ['Offres quotidiennes', number_format($p->daily_users), round($p->daily_users / $p->total * 100, 1) . '%'],
                ['Offres mensuelles', number_format($p->monthly_users), round($p->monthly_users / $p->total * 100, 1) . '%'],
                ['Flexibles', number_format($p->flexible_users), round($p->flexible_users / $p->total * 100, 1) . '%']
            ]);
        }
    }

    private function analyzeMultiOperatorBehavior(Carbon $cutoffDate): void
    {
        $this->newLine();
        $this->info('🌐 4. Comportement multi-opérateur:');
        
        // CORRECTION v2: Utiliser INNER JOIN
        $multiOp = DB::select("
            SELECT 
                f.total_operators_used,
                COUNT(DISTINCT f.client_id) as clients,
                AVG(f.operator_diversity_score) as avg_diversity,
                AVG(GREATEST(f.timwe_success_rate, f.eklektik_success_rate, f.ooredoo_success_rate)) as best_success_rate
            FROM ml_client_features f
            INNER JOIN (
                SELECT client_id, MAX(calculation_date) as max_date
                FROM ml_client_features
                WHERE calculation_date >= ?
                GROUP BY client_id
            ) latest ON f.client_id = latest.client_id AND f.calculation_date = latest.max_date
            WHERE f.total_operators_used > 0
            GROUP BY f.total_operators_used
            ORDER BY f.total_operators_used
        ", [$cutoffDate->toDateString()]);
        
        $tableData = [];
        foreach ($multiOp as $m) {
            $tableData[] = [
                $m->total_operators_used . ' opérateur(s)',
                number_format($m->clients),
                round($m->avg_diversity * 100, 1) . '%',
                round($m->best_success_rate * 100, 1) . '%'
            ];
        }
        
        $this->table(['Utilisation', 'Clients', 'Score Diversité Moy.', 'Meilleur Taux Succès'], $tableData);
    }

    private function generateStrategicRecommendations(): void
    {
        $this->newLine();
        $this->info('🎯 5. Recommandations stratégiques:');
        
        // Analyser les patterns pour des recommandations
        // CORRECTION v2: Utiliser INNER JOIN
        $insights = DB::select("
            SELECT 
                f.best_performing_operator,
                COUNT(DISTINCT f.client_id) as clients,
                AVG(GREATEST(f.timwe_success_rate, f.eklektik_success_rate, f.ooredoo_success_rate)) as success_rate
            FROM ml_client_features f
            INNER JOIN (
                SELECT client_id, MAX(calculation_date) as max_date
                FROM ml_client_features
                WHERE calculation_date >= ?
                GROUP BY client_id
            ) latest ON f.client_id = latest.client_id AND f.calculation_date = latest.max_date
            WHERE f.best_performing_operator != 'none'
            GROUP BY f.best_performing_operator
            ORDER BY success_rate DESC
        ", [Carbon::now()->subDays(30)->toDateString()]);
        
        $recommendations = [
            "📈 **Stratégie par Opérateur:**"
        ];
        
        foreach ($insights as $insight) {
            $op = $insight->best_performing_operator;
            $clients = number_format($insight->clients);
            $rate = round($insight->success_rate * 100, 1);
            
            if ($op === 'eklektik') {
                $recommendations[] = "   • Eklektik: $clients clients spécialisés ({$rate}% succès) → Offres quotidiennes 0.3 TND";
            } elseif ($op === 'timwe') {
                $recommendations[] = "   • Timwe: $clients clients spécialisés ({$rate}% succès) → Offres mensuelles 3.0 TND";
            } elseif ($op === 'ooredoo') {
                $recommendations[] = "   • Ooredoo: $clients clients spécialisés ({$rate}% succès) → Offres quotidiennes 0.3 TND"; // CORRECTION: 0.3 TND quotidien
            }
        }
        
        $recommendations[] = "";
        $recommendations[] = "🔄 **Actions Recommandées:**";
        $recommendations[] = "   • Segmenter les modèles ML par opérateur";
        $recommendations[] = "   • A/B test: quotidien (Eklektik/Ooredoo 0.3 TND) vs mensuel (Timwe 3.0 TND)";
        $recommendations[] = "   • Personnaliser timing selon type d'offre";
        $recommendations[] = "   • Exploiter les clients multi-opérateur pour cross-selling";
        
        foreach ($recommendations as $rec) {
            $this->line($rec);
        }
    }

    private function exportAnalysis(Carbon $cutoffDate): void
    {
        $this->newLine();
        $this->info('💾 Export de l\'analyse...');
        
        $exportPath = storage_path('ml_analysis/operator_preferences_' . date('Y_m_d_H_i') . '.csv');
        $dir = dirname($exportPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Exporter les données détaillées
        $data = DB::select("
            SELECT 
                client_id,
                best_performing_operator,
                timwe_success_rate,
                eklektik_success_rate,
                ooredoo_success_rate,
                preferred_frequency,
                price_preference,
                total_operators_used,
                operator_diversity_score
            FROM ml_client_features
            WHERE calculation_date >= ?
            ORDER BY operator_diversity_score DESC
        ", [$cutoffDate->toDateString()]);
        
        $csv = "client_id,best_operator,timwe_rate,eklektik_rate,ooredoo_rate,freq_pref,price_pref,operators_count,diversity\n";
        foreach ($data as $row) {
            $csv .= implode(',', [
                $row->client_id,
                $row->best_performing_operator,
                round($row->timwe_success_rate, 3),
                round($row->eklektik_success_rate, 3),
                round($row->ooredoo_success_rate, 3),
                $row->preferred_frequency,
                $row->price_preference,
                $row->total_operators_used,
                round($row->operator_diversity_score, 3)
            ]) . "\n";
        }
        
        file_put_contents($exportPath, $csv);
        
        $this->info("✅ Export sauvegardé: $exportPath");
    }
}