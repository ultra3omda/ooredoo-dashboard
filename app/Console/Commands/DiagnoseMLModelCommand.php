<?php

namespace App\Console\Commands;

use App\Models\MLClientFeature;
use App\Services\MLPythonBridgeService;
use App\Services\MLPredictionService;
use Illuminate\Console\Command;

/**
 * Diagnostic du modèle ML : vérifie si le modèle est entraîné, disponible et produit des résultats.
 */
class DiagnoseMLModelCommand extends Command
{
    protected $signature = 'ml:diagnose-model';

    protected $description = 'Diagnostique l\'état du modèle ML (fichier .pkl, Python, prédiction test)';

    public function handle(): int
    {
        $this->info('🔍 Diagnostic du modèle ML (prédiction succès facturation)');
        $this->newLine();

        $bridge = app(MLPythonBridgeService::class);
        $modelPath = base_path('ml_models/billing_predictor_v3.pkl');

        // 1. Fichier modèle
        $this->line('1. Fichier modèle');
        if (is_file($modelPath)) {
            $this->info('   ✅ ' . $modelPath . ' présent (' . round(filesize($modelPath) / 1024, 1) . ' Ko)');
        } else {
            $this->warn('   ❌ Fichier absent: ' . $modelPath);
            $this->line('   → Lancez: php artisan ml:train-python');
            $this->newLine();
            return Command::FAILURE;
        }

        // 2. Python et dépendances
        $this->line('2. Python et dépendances');
        $pythonPath = env('PYTHON_PATH', 'python');
        $out = [];
        @exec("\"$pythonPath\" --version 2>&1", $out);
        $version = implode(' ', $out);
        if (empty($version) || stripos($version, 'Python') === false) {
            $this->warn('   ❌ Python non trouvé (PYTHON_PATH=' . $pythonPath . ')');
            $this->line('   → Définissez PYTHON_PATH dans .env (ex: python ou py)');
        } else {
            $this->info('   ✅ ' . trim($version));
        }
        foreach (['joblib', 'lightgbm', 'sklearn', 'numpy'] as $lib) {
            $check = @shell_exec("\"$pythonPath\" -c \"import $lib; print('OK')\" 2>&1");
            if (strpos($check ?? '', 'OK') !== false) {
                $this->info('   ✅ ' . $lib);
            } else {
                $this->warn('   ❌ ' . $lib . ' manquant → pip install -r ml_models/requirements.txt');
            }
        }

        // 3. isModelAvailable
        $this->line('3. Service de prédiction');
        if ($bridge->isModelAvailable()) {
            $this->info('   ✅ Modèle considéré disponible (isModelAvailable = true)');
        } else {
            $this->warn('   ❌ isModelAvailable = false (fichier non vu par le service)');
        }

        // 4. Test de prédiction sur un client
        $this->line('4. Test de prédiction (1 client)');
        $sample = MLClientFeature::orderByRaw('calculation_date DESC')->first();
        if (! $sample) {
            $this->warn('   ❌ Aucune feature en base (ml_client_features vide)');
            $this->newLine();
            return Command::FAILURE;
        }
        $this->line('   Client ID: ' . $sample->client_id . ', date: ' . $sample->calculation_date);

        try {
            $predictionService = app(MLPredictionService::class);
            $pred = $predictionService->predictPaymentSuccess($sample->client_id);
            $prob = $pred['payment_success_probability'] ?? null;
            $version = $pred['model_version'] ?? '?';
            if ($prob !== null) {
                $this->info('   ✅ Prédiction OK: probabilité = ' . round($prob * 100, 2) . ' %, modèle = ' . $version);
            } else {
                $this->warn('   ⚠️ Prédiction retournée sans probability');
            }
        } catch (\Throwable $e) {
            $this->warn('   ❌ Erreur: ' . $e->getMessage());
            $this->line('   → Vérifiez que les features envoyées correspondent à train_model.py (FEATURE_COLUMNS).');
        }

        $this->newLine();
        $this->info('Résumé: pour avoir des résultats ML, exécutez: php artisan ml:train-python');
        $this->line('Puis relancez ce diagnostic: php artisan ml:diagnose-model');

        return Command::SUCCESS;
    }
}
