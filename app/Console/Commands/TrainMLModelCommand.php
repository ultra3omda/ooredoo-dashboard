<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MLModelTrainingService;
use Carbon\Carbon;
use Exception;

class TrainMLModelCommand extends Command
{
    protected $signature = 'ml:train {--model=lightgbm_v1 : Nom du modèle} 
                                      {--start-date= : Date de début (YYYY-MM-DD)}
                                      {--end-date= : Date de fin (YYYY-MM-DD)}
                                      {--max-rounds=200 : Nombre max de rounds}
                                      {--learning-rate=0.05 : Taux d\'apprentissage}
                                      {--test-size=0.2 : Taille du set de test}
                                      {--force : Force l\'entraînement même si peu de données}';

    protected $description = 'Entraîne un modèle ML pour la prédiction de succès de facturation';

    private MLModelTrainingService $trainingService;

    public function __construct(MLModelTrainingService $trainingService)
    {
        parent::__construct();
        $this->trainingService = $trainingService;
    }

    public function handle()
    {
        $this->info('🤖 Entraînement du modèle ML...');
        
        try {
            $modelName = $this->option('model');
            $options = [
                'start_date' => $this->option('start-date') ?? Carbon::now()->subMonths(6)->toDateString(),
                'end_date' => $this->option('end-date') ?? Carbon::now()->toDateString(),
                'max_rounds' => (int)$this->option('max-rounds'),
                'learning_rate' => (float)$this->option('learning-rate'),
                'test_size' => (float)$this->option('test-size'),
                'force' => $this->option('force')
            ];
            
            $this->info("📅 Période: {$options['start_date']} → {$options['end_date']}");
            $this->info("⚙️  Paramètres: LR={$options['learning_rate']}, Rounds={$options['max_rounds']}");
            
            // Vérification des prérequis
            if (!$this->checkPrerequisites()) {
                return Command::FAILURE;
            }
            
            $this->newLine();
            $this->info('🚀 Lancement de l\'entraînement...');
            
            // Entraînement du modèle
            $results = $this->trainingService->trainLightGBModel($modelName, $options);
            
            $this->newLine();
            $this->info('✅ Entraînement terminé avec succès!');
            $this->displayResults($results);
            
            // Proposer la validation
            if ($this->confirm('Voulez-vous valider le modèle sur des données récentes?')) {
                $this->call('ml:validate', ['--model' => $modelName]);
            }
            
            return Command::SUCCESS;
            
        } catch (Exception $e) {
            $this->error("❌ Erreur lors de l'entraînement: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Vérifie que les prérequis sont remplis
     */
    private function checkPrerequisites(): bool
    {
        $this->info('🔍 Vérification des prérequis...');
        
        // 1. Vérifier Python
        $pythonResult = shell_exec('python --version 2>&1');
        if (!$pythonResult || !str_contains($pythonResult, 'Python')) {
            $this->error('❌ Python non trouvé. Installez Python 3.8+');
            return false;
        }
        $this->info("✅ Python: " . trim($pythonResult));
        
        // 2. Vérifier les librairies Python
        $requiredLibs = ['lightgbm', 'sklearn', 'numpy', 'pandas'];
        foreach ($requiredLibs as $lib) {
            $checkLib = shell_exec("python -c \"import $lib; print('OK')\" 2>&1");
            if (!str_contains($checkLib ?? '', 'OK')) {
                $this->error("❌ Librairie Python manquante: $lib");
                $this->info("💡 Installez avec: pip install $lib");
                return false;
            }
        }
        $this->info('✅ Librairies Python: ' . implode(', ', $requiredLibs));
        
        // 3. Vérifier les données ML
        $featuresCount = \DB::table('ml_client_features')->count();
        if ($featuresCount < 1000) {
            if (!$this->option('force')) {
                $this->error("❌ Pas assez de features ML ($featuresCount). Utilisez --force ou lancez ml:extract-features");
                return false;
            }
            $this->warn("⚠️  Peu de données ($featuresCount features) mais --force activé");
        }
        $this->info("✅ Features ML: " . number_format($featuresCount));
        
        return true;
    }

    /**
     * Affiche les résultats de l\'entraînement
     */
    private function displayResults(array $results): void
    {
        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Modèle', $results['model_name'] ?? 'N/A'],
                ['Échantillons entraînement', number_format($results['training_samples'] ?? 0)],
                ['Échantillons test', number_format($results['test_samples'] ?? 0)],
                ['Taux positifs', round(($results['positive_rate'] ?? 0) * 100, 2) . '%'],
                ['AUC Train', round($results['performance']['train_auc'] ?? 0, 3)],
                ['AUC Test', round($results['performance']['test_auc'] ?? 0, 3)],
                ['Accuracy', round(($results['performance']['accuracy'] ?? 0) * 100, 1) . '%'],
                ['Precision', round(($results['performance']['precision'] ?? 0) * 100, 1) . '%'],
                ['Recall', round(($results['performance']['recall'] ?? 0) * 100, 1) . '%'],
                ['F1-Score', round(($results['performance']['f1_score'] ?? 0) * 100, 1) . '%'],
                ['Seuil optimal', round($results['best_threshold'] ?? 0.5, 3)],
                ['Durée', ($results['training_duration_minutes'] ?? 0) . ' min'],
            ]
        );
        
        $this->newLine();
        $this->info('🔝 Top 5 Features Importantes:');
        $importance = $results['feature_importance'] ?? [];
        foreach (array_slice($importance, 0, 5) as $i => $feature) {
            $this->line('   ' . ($i + 1) . '. ' . $feature[0] . ' (' . round($feature[1], 1) . ')');
        }
        
        if (isset($results['model_path'])) {
            $this->newLine();
            $this->info("💾 Modèle sauvegardé: {$results['model_path']}");
        }
    }
}