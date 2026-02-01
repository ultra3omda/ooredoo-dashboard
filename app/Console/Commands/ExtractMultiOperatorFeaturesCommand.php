<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MLMultiOperatorFeatureService;
use Carbon\Carbon;
use Exception;

class ExtractMultiOperatorFeaturesCommand extends Command
{
    protected $signature = 'ml:extract-multi {--start-date= : Date de début (YYYY-MM-DD)}
                                            {--end-date= : Date de fin (YYYY-MM-DD)}
                                            {--batch-days=7 : Nombre de jours par batch}
                                            {--operator= : Opérateur spécifique (timwe/eklektik/ooredoo)}
                                            {--client-id= : Client spécifique}
                                            {--force : Forcer même si données existantes}';

    protected $description = 'Extrait les features ML pour tous les opérateurs (Timwe, Eklektik, Ooredoo/DGV)';

    private MLMultiOperatorFeatureService $featureService;

    public function __construct(MLMultiOperatorFeatureService $featureService)
    {
        parent::__construct();
        $this->featureService = $featureService;
    }

    public function handle()
    {
        $this->info('🌐 Extraction features multi-opérateur...');
        
        try {
            $startDate = $this->option('start-date') 
                ? Carbon::parse($this->option('start-date'))
                : Carbon::now()->subDays(7);
            
            $endDate = $this->option('end-date')
                ? Carbon::parse($this->option('end-date'))
                : Carbon::now();
                
            $clientId = $this->option('client-id');
            $operator = $this->option('operator');
            
            $this->info("📅 Période: {$startDate->toDateString()} → {$endDate->toDateString()}");
            
            if ($operator) {
                $this->info("🎯 Opérateur: $operator");
            } else {
                $this->info("🌐 Tous opérateurs: Timwe, Eklektik, Ooredoo/DGV");
            }

            // Extraction par client spécifique
            if ($clientId) {
                return $this->extractSingleClient($clientId, $endDate);
            }

            // Vérifier les données existantes
            if (!$this->option('force')) {
                $existingCount = \DB::table('ml_client_features')
                    ->whereBetween('calculation_date', [$startDate, $endDate])
                    ->count();
                    
                if ($existingCount > 0) {
                    if (!$this->confirm("$existingCount features existent déjà. Continuer?")) {
                        $this->info('Extraction annulée');
                        return Command::SUCCESS;
                    }
                }
            }

            // Extraction par période
            $totalProcessed = 0;
            $batchDays = (int)$this->option('batch-days');
            
            $currentDate = $startDate->copy();
            while ($currentDate->lte($endDate)) {
                $this->info("📊 Extraction pour {$currentDate->toDateString()}...");
                
                $processed = $this->featureService->extractAndStoreFeaturesForDate($currentDate);
                $totalProcessed += $processed;
                
                $this->info("✅ {$processed} clients traités pour {$currentDate->toDateString()}");
                
                $currentDate->addDays($batchDays);
            }

            $this->newLine();
            $this->info("🎉 Extraction terminée!");
            $this->table(['Métrique', 'Valeur'], [
                ['Clients traités', number_format($totalProcessed)],
                ['Période', "{$startDate->toDateString()} → {$endDate->toDateString()}"],
                ['Opérateurs', $operator ?: 'Tous (Timwe, Eklektik, Ooredoo)'],
                ['Nouvelles features', '18 features multi-opérateur ajoutées'],
                ['Features v2.0 total', '36 features par client']
            ]);

            // Analyser les résultats
            $this->analyzeExtractionResults($startDate, $endDate);
            
            return Command::SUCCESS;
            
        } catch (Exception $e) {
            $this->error("❌ Erreur extraction: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function extractSingleClient(int $clientId, Carbon $date): int
    {
        $this->info("👤 Extraction pour client $clientId...");
        
        $features = $this->featureService->extractClientFeatures($clientId, $date);
        
        // Sauvegarder
        \DB::table('ml_client_features')->upsert(
            [$features],
            ['client_id', 'calculation_date'],
            array_keys($features)
        );
        
        $this->info('✅ Features extraites:');
        
        // Afficher un résumé des features importantes
        $importantFeatures = [
            'timwe_success_rate' => 'Timwe',
            'eklektik_success_rate' => 'Eklektik', 
            'ooredoo_success_rate' => 'Ooredoo',
            'preferred_frequency' => 'Fréquence préférée',
            'price_preference' => 'Préférence prix',
            'best_performing_operator' => 'Meilleur opérateur'
        ];
        
        $tableData = [];
        foreach ($importantFeatures as $key => $label) {
            $value = $features[$key] ?? 'N/A';
            if (is_numeric($value)) {
                $value = round($value, 3);
            }
            $tableData[] = [$label, $value];
        }
        
        $this->table(['Feature', 'Valeur'], $tableData);
        
        return Command::SUCCESS;
    }

    private function analyzeExtractionResults(Carbon $startDate, Carbon $endDate): void
    {
        $this->info('📈 Analyse des résultats d\'extraction:');
        
        // Répartition par opérateur
        $operatorStats = \DB::select("
            SELECT 
                SUM(timwe_has_activity) as timwe_users,
                SUM(eklektik_has_activity) as eklektik_users, 
                SUM(ooredoo_has_activity) as ooredoo_users,
                SUM(CASE WHEN total_operators_used > 1 THEN 1 ELSE 0 END) as multi_operator_users,
                COUNT(*) as total_clients
            FROM ml_client_features 
            WHERE calculation_date BETWEEN ? AND ?
        ", [$startDate->toDateString(), $endDate->toDateString()]);
        
        if (!empty($operatorStats)) {
            $stats = $operatorStats[0];
            $this->table(['Opérateur', 'Clients Actifs', '%'], [
                ['Timwe', number_format($stats->timwe_users), round($stats->timwe_users / $stats->total_clients * 100, 1) . '%'],
                ['Eklektik', number_format($stats->eklektik_users), round($stats->eklektik_users / $stats->total_clients * 100, 1) . '%'],
                ['Ooredoo/DGV', number_format($stats->ooredoo_users), round($stats->ooredoo_users / $stats->total_clients * 100, 1) . '%'],
                ['Multi-opérateur', number_format($stats->multi_operator_users), round($stats->multi_operator_users / $stats->total_clients * 100, 1) . '%'],
                ['Total', number_format($stats->total_clients), '100%']
            ]);
        }

        $this->newLine();
        $this->info('💡 Prochaines étapes recommandées:');
        $this->line('   • Entraîner modèle multi-opérateur: php artisan ml:train --model=multi_operator_v2');
        $this->line('   • Analyser les préférences: php artisan ml:analyze-preferences');
        $this->line('   • Voir le dashboard: /admin/ml-dashboard');
    }
}