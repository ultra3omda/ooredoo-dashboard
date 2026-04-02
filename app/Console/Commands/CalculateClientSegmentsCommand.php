<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CalculateClientSegmentsCommand extends Command
{
    protected $signature = 'ml:calculate-segments
                            {--start-date= : Date de début (YYYY-MM-DD)}
                            {--end-date= : Date de fin (YYYY-MM-DD)}
                            {--force : Recalculer même si segment déjà défini}
                            {--batch-size=5000 : Nombre de lignes par batch}';

    protected $description = 'Calcule et met à jour les segments clients (premium_payers, regular_payers, etc.) dans ml_client_features';

    public function handle(): int
    {
        $this->info('🎯 Calcul des segments clients...');
        
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        $force = $this->option('force');
        $batchSize = (int) $this->option('batch-size');
        
        $startTime = microtime(true);
        
        // Construire la requête
        $query = DB::table('ml_client_features');
        
        if (!$force) {
            $query->where('client_segment', 'unknown');
        }
        
        if ($startDate) {
            $query->where('calculation_date', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('calculation_date', '<=', $endDate);
        }
        
        $total = $query->count();
        
        if ($total === 0) {
            $this->info('✅ Aucun segment à calculer.');
            return 0;
        }
        
        $this->info("📊 {$total} lignes à traiter");
        $this->newLine();
        
        $processed = 0;
        $updated = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();
        
        // Traiter par batch pour éviter les problèmes de mémoire
        DB::table('ml_client_features')
            ->when(!$force, fn($q) => $q->where('client_segment', 'unknown'))
            ->when($startDate, fn($q) => $q->where('calculation_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('calculation_date', '<=', $endDate))
            ->orderBy('id')
            ->chunk($batchSize, function ($features) use (&$processed, &$updated, $bar) {
                $updates = [];
                
                foreach ($features as $feature) {
                    $segment = $this->calculateSegment($feature);
                    
                    if ($segment !== $feature->client_segment) {
                        $updates[] = [
                            'id' => $feature->id,
                            'segment' => $segment,
                        ];
                    }
                    
                    $processed++;
                    
                    if ($processed % 100 === 0) {
                        $bar->advance(100);
                    }
                }
                
                // Mettre à jour par batch en une seule requête
                if (!empty($updates)) {
                    $cases = [];
                    $ids = [];
                    
                    foreach ($updates as $update) {
                        $ids[] = $update['id'];
                        $cases[] = "WHEN {$update['id']} THEN '{$update['segment']}'";
                    }
                    
                    if (!empty($ids)) {
                        $idsStr = implode(',', $ids);
                        $casesStr = implode(' ', $cases);
                        
                        DB::statement("
                            UPDATE ml_client_features
                            SET client_segment = CASE id {$casesStr} END,
                                updated_at = NOW()
                            WHERE id IN ({$idsStr})
                        ");
                        
                        $updated += count($updates);
                    }
                }
            });
        
        $bar->finish();
        $this->newLine(2);
        
        $duration = round(microtime(true) - $startTime, 2);
        
        $this->info("=" . str_repeat("=", 60));
        $this->info("✅ Segmentation terminée !");
        $this->info("   📊 Lignes traitées: " . number_format($processed));
        $this->info("   ♻️  Segments mis à jour: " . number_format($updated));
        $this->info("   ⏱️  Temps: {$duration}s");
        $this->info("=" . str_repeat("=", 60));
        
        // Afficher les statistiques de répartition
        $this->newLine();
        $this->displaySegmentStats($startDate, $endDate);
        
        return 0;
    }
    
    /**
     * Calcule le segment d'un client basé sur ses features
     * Adapté pour les features multi-opérateurs (timwe, eklektik, ooredoo)
     */
    private function calculateSegment($feature): string
    {
        // Utiliser les success rates par opérateur
        $timweRate = (float) ($feature->timwe_success_rate ?? 0);
        $eklektikRate = (float) ($feature->eklektik_success_rate ?? 0);
        $ooredooRate = (float) ($feature->ooredoo_success_rate ?? 0);
        
        // Calculer le taux de succès global
        $rates = array_filter([$timweRate, $eklektikRate, $ooredooRate], fn($r) => $r > 0);
        $avgSuccessRate = count($rates) > 0 ? array_sum($rates) / count($rates) : 0;
        
        // Métriques d'activité (basées sur les 90 derniers jours)
        $total90dCount = (int) ($feature->total_90d_count ?? 0);
        $total90dSum = (float) ($feature->total_90d_sum ?? 0);
        
        // Nombre d'opérateurs utilisés
        $operatorsUsed = 0;
        if ($timweRate > 0 || ($feature->timwe_90d_count ?? 0) > 0) $operatorsUsed++;
        if ($eklektikRate > 0 || ($feature->eklektik_90d_count ?? 0) > 0) $operatorsUsed++;
        if ($ooredooRate > 0 || ($feature->ooredoo_90d_count ?? 0) > 0) $operatorsUsed++;
        
        // Si aucune donnée significative
        if ($total90dCount === 0) {
            return 'unknown';
        }
        
        // Premium payers: excellent taux de succès + forte activité + multi-opérateur
        if ($avgSuccessRate >= 0.7 && $total90dCount >= 50 && $total90dSum >= 10) {
            return 'premium_payers';
        }
        
        // Regular payers: bon taux de succès + activité régulière
        if ($avgSuccessRate >= 0.3 && $total90dCount >= 10) {
            return 'regular_payers';
        }
        
        // Struggling payers: quelques transactions mais faible succès
        if ($avgSuccessRate >= 0.05 && $total90dCount >= 5) {
            return 'struggling_payers';
        }
        
        // Churn risk: activité mais très faible performance
        if ($total90dCount >= 5 && $avgSuccessRate < 0.05) {
            return 'churn_risk';
        }
        
        // High risk: peu d'activité ou très mauvais résultats
        if ($total90dCount < 5 || $avgSuccessRate < 0.02) {
            return 'high_risk';
        }
        
        // Par défaut: unknown (cas limite)
        return 'unknown';
    }
    
    /**
     * Affiche les statistiques de répartition des segments
     */
    private function displaySegmentStats(?string $startDate, ?string $endDate): void
    {
        $this->info('📈 Répartition des segments :');
        
        $query = DB::table('ml_client_features')
            ->select('client_segment', DB::raw('COUNT(*) as count'))
            ->groupBy('client_segment')
            ->orderBy('count', 'desc');
        
        if ($startDate) {
            $query->where('calculation_date', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('calculation_date', '<=', $endDate);
        }
        
        $stats = $query->get();
        $total = $stats->sum('count');
        
        $tableData = [];
        foreach ($stats as $stat) {
            $percentage = $total > 0 ? round(($stat->count / $total) * 100, 2) : 0;
            $tableData[] = [
                ucfirst(str_replace('_', ' ', $stat->client_segment)),
                number_format($stat->count),
                $percentage . '%',
                $this->getSegmentBar($percentage),
            ];
        }
        
        $this->table(
            ['Segment', 'Nombre', 'Pourcentage', 'Répartition'],
            $tableData
        );
        
        $this->newLine();
        $this->info('💡 Légende des segments :');
        $this->line('   • Premium payers: Clients fiables avec haute valeur (≥70% succès, ≥5 paiements)');
        $this->line('   • Regular payers: Clients réguliers avec performance correcte (≥30% succès, ≥2 paiements)');
        $this->line('   • Struggling payers: Clients avec difficultés mais quelques succès (≥5% succès, ≥1 paiement)');
        $this->line('   • Churn risk: Clients à risque de désabonnement (forte probabilité churn ou ≥5 échecs consécutifs)');
        $this->line('   • High risk: Clients à très faible performance (<5% succès malgré tentatives)');
        $this->line('   • Unknown: Données insuffisantes pour segmentation');
    }
    
    /**
     * Génère une barre de progression visuelle pour les pourcentages
     */
    private function getSegmentBar(float $percentage): string
    {
        $barLength = 20;
        $filledLength = (int) round(($percentage / 100) * $barLength);
        $bar = str_repeat('█', $filledLength) . str_repeat('░', $barLength - $filledLength);
        return $bar;
    }
}
