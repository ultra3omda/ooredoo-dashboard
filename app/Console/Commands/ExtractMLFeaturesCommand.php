<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MLFeatureExtractionService;
use Carbon\Carbon;

class ExtractMLFeaturesCommand extends Command
{
    protected $signature = 'ml:extract-features 
                            {--start-date= : Date de début (format: Y-m-d)} 
                            {--end-date= : Date de fin (format: Y-m-d)} 
                            {--batch-days=7 : Nombre de jours par batch}
                            {--force : Forcer la regénération même si les données existent}';

    protected $description = 'Extrait les features ML pour l\'entraînement des modèles';

    private MLFeatureExtractionService $featureService;

    public function __construct(MLFeatureExtractionService $featureService)
    {
        parent::__construct();
        $this->featureService = $featureService;
    }

    public function handle()
    {
        $this->info('🚀 Démarrage extraction des features ML pour Timwe...');
        
        // Configuration des dates
        $startDateStr = $this->option('start-date') ?: '2025-08-01';
        $endDateStr = $this->option('end-date') ?: Carbon::now()->toDateString();
        $batchDays = (int) $this->option('batch-days');
        $force = $this->option('force');
        
        try {
            $startDate = Carbon::parse($startDateStr);
            $endDate = Carbon::parse($endDateStr);
        } catch (\Exception $e) {
            $this->error('❌ Format de date invalide. Utilisez le format Y-m-d');
            return 1;
        }

        if ($startDate->gt($endDate)) {
            $this->error('❌ La date de début doit être antérieure à la date de fin');
            return 1;
        }

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $this->info("📅 Période: {$startDate->toDateString()} à {$endDate->toDateString()} ($totalDays jours)");
        $this->info("🔄 Traitement par batch de $batchDays jours");
        
        if (!$force) {
            // Vérifier si des données existent déjà
            $existingCount = \DB::table('ml_client_features')
                ->whereBetween('calculation_date', [$startDate, $endDate])
                ->count();
            
            if ($existingCount > 0) {
                $this->warn("⚠️  $existingCount enregistrements existent déjà pour cette période");
                if (!$this->confirm('Voulez-vous continuer ? (Les données existantes seront mises à jour)')) {
                    $this->info('Extraction annulée.');
                    return 0;
                }
            }
        }

        // Traitement par batches de jours
        $currentDate = $startDate->copy();
        $totalProcessed = 0;
        $progressBar = $this->output->createProgressBar($totalDays);
        $progressBar->setFormat('verbose');

        while ($currentDate->lte($endDate)) {
            $batchEndDate = $currentDate->copy()->addDays($batchDays - 1);
            if ($batchEndDate->gt($endDate)) {
                $batchEndDate = $endDate;
            }

            $this->line("\n📊 Traitement batch: {$currentDate->toDateString()} → {$batchEndDate->toDateString()}");
            
            // Traiter chaque jour du batch
            $batchDate = $currentDate->copy();
            while ($batchDate->lte($batchEndDate)) {
                try {
                    $processedCount = $this->featureService->extractAndStoreFeaturesForDate($batchDate);
                    $totalProcessed += $processedCount;
                    
                    $progressBar->advance();
                    $this->line(" ✅ {$batchDate->toDateString()}: $processedCount clients");
                    
                } catch (\Exception $e) {
                    $this->error(" ❌ Erreur pour {$batchDate->toDateString()}: {$e->getMessage()}");
                }
                
                $batchDate->addDay();
            }
            
            // Pause entre les batches pour éviter la surcharge
            if ($currentDate->lt($endDate)) {
                $this->line("⏸️  Pause de 2 secondes...");
                sleep(2);
            }
            
            $currentDate = $batchEndDate->copy()->addDay();
        }

        $progressBar->finish();
        $this->line("\n");
        
        // Statistiques finales
        $this->info("🎉 Extraction terminée !");
        $this->table(['Métrique', 'Valeur'], [
            ['Période traitée', "{$startDate->toDateString()} → {$endDate->toDateString()}"],
            ['Nombre de jours', $totalDays],
            ['Total clients traités', number_format($totalProcessed)],
            ['Moyenne clients/jour', number_format($totalProcessed / $totalDays, 1)],
        ]);

        // Vérification des données générées
        $this->checkDataQuality($startDate, $endDate);
        
        // Nettoyage optionnel des anciennes données
        if ($this->confirm('Voulez-vous nettoyer les anciennes données (> 1 an) ?')) {
            $deletedCount = $this->featureService->cleanOldFeatures();
            $this->info("🧹 $deletedCount anciens enregistrements supprimés");
        }

        return 0;
    }

    /**
     * Vérification de la qualité des données générées
     */
    private function checkDataQuality(Carbon $startDate, Carbon $endDate): void
    {
        $this->info("\n🔍 Vérification de la qualité des données...");
        
        // Statistiques générales
        $totalFeatures = \DB::table('ml_client_features')
            ->whereBetween('calculation_date', [$startDate, $endDate])
            ->count();
        
        $uniqueClients = \DB::table('ml_client_features')
            ->whereBetween('calculation_date', [$startDate, $endDate])
            ->distinct('client_id')
            ->count('client_id');
        
        // Répartition par segment
        $segmentStats = \DB::table('ml_client_features')
            ->whereBetween('calculation_date', [$startDate, $endDate])
            ->where('calculation_date', $endDate) // Dernière date seulement
            ->select('client_segment', \DB::raw('COUNT(*) as count'))
            ->groupBy('client_segment')
            ->orderBy('count', 'desc')
            ->get();

        // Statistiques des taux de succès
        $successRateStats = \DB::table('ml_client_features')
            ->whereBetween('calculation_date', [$startDate, $endDate])
            ->where('calculation_date', $endDate)
            ->selectRaw('
                AVG(payment_success_rate) as avg_success_rate,
                MIN(payment_success_rate) as min_success_rate,
                MAX(payment_success_rate) as max_success_rate,
                COUNT(CASE WHEN payment_success_rate > 0 THEN 1 END) as clients_with_payments
            ')
            ->first();

        $this->table(['Métrique', 'Valeur'], [
            ['Total enregistrements', number_format($totalFeatures)],
            ['Clients uniques', number_format($uniqueClients)],
            ['Taux succès moyen', round($successRateStats->avg_success_rate * 100, 2) . '%'],
            ['Clients avec paiements', number_format($successRateStats->clients_with_payments)],
        ]);

        if ($segmentStats->isNotEmpty()) {
            $this->info("\n📊 Répartition par segment (au {$endDate->toDateString()}):");
            $segmentData = [];
            foreach ($segmentStats as $stat) {
                $percentage = round(($stat->count / $uniqueClients) * 100, 1);
                $segmentData[] = [
                    ucfirst(str_replace('_', ' ', $stat->client_segment)),
                    number_format($stat->count),
                    $percentage . '%'
                ];
            }
            $this->table(['Segment', 'Clients', '%'], $segmentData);
        }

        // Alertes de qualité
        $warnings = [];
        
        if ($uniqueClients == 0) {
            $warnings[] = "❌ Aucun client trouvé - vérifiez la configuration Timwe";
        }
        
        if ($successRateStats->avg_success_rate == 0) {
            $warnings[] = "⚠️  Aucun paiement réussi trouvé - vérifiez les critères de facturation";
        }
        
        if ($successRateStats->avg_success_rate < 0.05) {
            $warnings[] = "⚠️  Taux de succès très bas (" . round($successRateStats->avg_success_rate * 100, 2) . "%)";
        }

        if (!empty($warnings)) {
            $this->warn("\n🚨 Alertes qualité:");
            foreach ($warnings as $warning) {
                $this->line("  $warning");
            }
        } else {
            $this->info("\n✅ Qualité des données: OK");
        }
    }
}