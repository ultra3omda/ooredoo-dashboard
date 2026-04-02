<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MLModelTrainingService;
use App\Services\MLABTestingService;
use Carbon\Carbon;

class ValidateMLModelCommand extends Command
{
    protected $signature = 'ml:validate {--model=lightgbm_v1 : Modèle à valider}
                                        {--create-test : Créer un test A/B automatiquement}';

    protected $description = 'Valide les performances d\'un modèle ML et configure optionnellement un test A/B';

    private MLModelTrainingService $trainingService;
    private MLABTestingService $abTestingService;

    public function __construct(MLModelTrainingService $trainingService, MLABTestingService $abTestingService)
    {
        parent::__construct();
        $this->trainingService = $trainingService;
        $this->abTestingService = $abTestingService;
    }

    public function handle()
    {
        $modelName = $this->option('model');
        
        $this->info("🧪 Validation du modèle $modelName...");
        
        try {
            // 1. Valider les performances
            $validation = $this->trainingService->validateModel($modelName);
            
            $this->displayValidationResults($validation);
            
            // 2. Créer un test A/B si demandé
            if ($this->option('create-test') || $this->confirm('Créer un test A/B pour ce modèle?')) {
                $this->createABTest($modelName);
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Erreur validation: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function displayValidationResults(array $validation): void
    {
        if ($validation['status'] === 'no_data') {
            $this->warn('⚠️  ' . $validation['message']);
            return;
        }
        
        $this->info('✅ Validation terminée');
        $this->table(['Métrique', 'Valeur'], [
            ['Statut', $validation['status']],
            ['Date validation', $validation['validation_date']],
            ['Prédictions analysées', $validation['predictions_count'] ?? 'N/A'],
            ['Accuracy estimée', round(($validation['accuracy_estimate'] ?? 0) * 100, 1) . '%'],
            ['Drift détecté', $validation['drift_detected'] ? 'Oui ⚠️' : 'Non ✅']
        ]);
    }

    private function createABTest(string $modelName): void
    {
        $this->info('🧪 Création du test A/B...');
        
        $participants = $this->ask('Nombre de participants cibles?', '500');
        $duration = $this->ask('Durée en jours?', '14');
        $percentage = $this->ask('Pourcentage traitement (ML)?', '50');
        
        $config = [
            'test_name' => "ml_rollout_$modelName",
            'description' => "Test A/B - Déploiement modèle $modelName vs rule-based",
            'target_participants' => (int)$participants,
            'duration_days' => (int)$duration,
            'treatment_percentage' => (int)$percentage,
            'success_metric' => 'billing_success_rate',
            'minimum_effect_size' => 0.05, // 5% amélioration minimum
        ];
        
        $testId = $this->abTestingService->createMLRolloutTest($config);
        
        $this->info("✅ Test A/B créé avec l'ID: $testId");
        $this->info("🎯 Pour voir les résultats: php artisan ml:ab-test --test-id=$testId");
    }
}