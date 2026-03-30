<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class MLPythonBridgeService
{
    private string $pythonPath;

    private string $modelPath;

    private string $trainScriptPath;

    private string $predictScriptPath;

    public function __construct()
    {
        $this->pythonPath = env('PYTHON_PATH', 'python3');
        $this->modelPath = base_path('ml_models/billing_predictor_v3.pkl');
        $this->trainScriptPath = base_path('ml_models/train_model.py');
        $this->predictScriptPath = base_path('ml_models/predict.py');
    }

    /**
     * Prédit le succès de paiement avec le modèle ML (LightGBM).
     *
     * @param  array<string, float|int>  $features  Features du client (noms de colonnes ml_client_features)
     * @return array{probability: float, confidence: float}
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function predictPaymentSuccess(array $features): array
    {
        $featuresJson = json_encode(['features' => $features]);

        $process = new Process([
            $this->pythonPath,
            $this->predictScriptPath,
            $featuresJson,
        ], base_path(), null, null, 30);

        $process->run();

        if (! $process->isSuccessful()) {
            Log::error('MLPythonBridge - Erreur prédiction', [
                'error' => $process->getErrorOutput(),
                'output' => $process->getOutput(),
            ]);
            throw new ProcessFailedException($process);
        }

        $output = trim($process->getOutput());
        $decoded = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! isset($decoded['payment_success_probability'])) {
            Log::error('MLPythonBridge - Réponse invalide', ['output' => $output]);
            throw new \RuntimeException('Réponse invalide du script Python: '.$output);
        }

        return [
            'probability' => (float) ($decoded['payment_success_probability'] ?? 0),
            'confidence' => (float) ($decoded['confidence'] ?? 0.5),
        ];
    }

    /**
     * Entraîne un nouveau modèle sur les dernières données (ml_client_features).
     *
     * @param  callable(int $elapsedSeconds, string $buffer)|null  $outputCallback  Si fourni, appelé à chaque sortie avec (secondes écoulées, ligne)
     * @return array{status: string, output: string}
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function trainNewModel(?callable $outputCallback = null): array
    {
        if (! is_file($this->trainScriptPath)) {
            throw new \RuntimeException("Script d'entraînement introuvable: {$this->trainScriptPath}");
        }

        $timeout = (int) env('ML_TRAIN_TIMEOUT', 7200); // 2h par défaut (entraînement sur gros volume)
        $startTime = time();

        $process = new Process(
            [$this->pythonPath, $this->trainScriptPath],
            base_path(),
            null,
            null,
            $timeout
        );

        Log::info('MLPythonBridge - Début entraînement modèle ML');

        $process->run(function (string $type, string $buffer) use ($outputCallback, $startTime): void {
            $elapsed = time() - $startTime;
            $line = trim($buffer);
            if ($line !== '') {
                if (is_callable($outputCallback)) {
                    $outputCallback($elapsed, $line);
                }
                Log::info('ML Training: '.$line);
            }
        });

        if (! $process->isSuccessful()) {
            Log::error('MLPythonBridge - Échec entraînement', [
                'error' => $process->getErrorOutput(),
            ]);
            throw new ProcessFailedException($process);
        }

        Log::info('MLPythonBridge - Entraînement terminé avec succès');

        return [
            'status' => 'success',
            'output' => $process->getOutput(),
        ];
    }

    /**
     * Indique si le modèle entraîné est disponible.
     */
    public function isModelAvailable(): bool
    {
        return is_file($this->modelPath);
    }
}
