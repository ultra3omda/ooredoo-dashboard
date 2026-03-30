#!/usr/bin/env php
<?php
/**
 * Worker asynchrone pour les tâches ML longues
 * Exécuté en arrière-plan par MLAsyncTaskService
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

$paramsJson = base64_decode($argv[1] ?? '');
$config = json_decode($paramsJson, true);

if (!$config) {
    echo "Error: Invalid params\n";
    exit(1);
}

$taskId = $config['task_id'];
$taskType = $config['task_type'];
$params = $config['params'] ?? [];

$statusDir = storage_path('app/ml_tasks');

function updateStatus(string $taskId, array $data) {
    global $statusDir;
    $file = $statusDir . '/' . $taskId . '.json';
    $existing = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $merged = array_merge($existing, $data);
    file_put_contents($file, json_encode($merged, JSON_PRETTY_PRINT));
}

try {
    switch ($taskType) {
        case 'extract_features':
            runFeatureExtraction($taskId, $params);
            break;
        case 'train_model':
            runModelTraining($taskId, $params);
            break;
        default:
            updateStatus($taskId, ['status' => 'failed', 'message' => "Type de tâche inconnu: $taskType"]);
    }
} catch (\Exception $e) {
    Log::error("MLAsyncWorker - Erreur", ['task_id' => $taskId, 'error' => $e->getMessage()]);
    updateStatus($taskId, [
        'status' => 'failed',
        'message' => 'Erreur: ' . $e->getMessage(),
        'finished_at' => now()->toIso8601String()
    ]);
}

function runFeatureExtraction(string $taskId, array $params): void
{
    $startDate = \Carbon\Carbon::parse($params['start_date'] ?? now()->subDays(30));
    $endDate = \Carbon\Carbon::parse($params['end_date'] ?? now());
    
    updateStatus($taskId, ['status' => 'running', 'message' => 'Extraction des features...', 'progress' => 5]);
    
    $featureService = app(\App\Services\MLFeatureExtractionService::class);
    $totalProcessed = 0;
    $currentDate = $startDate->copy();
    $totalDays = $startDate->diffInDays($endDate) + 1;
    $daysDone = 0;
    
    while ($currentDate->lte($endDate)) {
        $processedCount = $featureService->extractAndStoreFeaturesForDate($currentDate);
        $totalProcessed += $processedCount;
        $daysDone++;
        $progress = min(95, round(($daysDone / $totalDays) * 100));
        
        updateStatus($taskId, [
            'status' => 'running',
            'progress' => $progress,
            'message' => "Jour {$daysDone}/{$totalDays} - {$currentDate->toDateString()} ({$processedCount} features)",
            'total_processed' => $totalProcessed
        ]);
        
        $currentDate->addDay();
    }
    
    updateStatus($taskId, [
        'status' => 'completed',
        'progress' => 100,
        'message' => "Extraction terminée: {$totalProcessed} features extraites pour {$totalDays} jours",
        'total_processed' => $totalProcessed,
        'finished_at' => now()->toIso8601String()
    ]);
    
    Log::info("MLAsyncWorker - Extraction terminée", ['task_id' => $taskId, 'total' => $totalProcessed]);
}

function runModelTraining(string $taskId, array $params): void
{
    updateStatus($taskId, ['status' => 'running', 'message' => 'Entraînement du modèle LightGBM...', 'progress' => 10]);
    
    $pythonPath = env('PYTHON_PATH', '/root/.venv/bin/python3');
    $trainScript = base_path('ml_models/train_model.py');
    
    $process = new \Symfony\Component\Process\Process(
        [$pythonPath, $trainScript],
        base_path(),
        null,
        null,
        7200
    );
    
    $lastOutput = '';
    $process->run(function ($type, $buffer) use ($taskId, &$lastOutput) {
        $line = trim($buffer);
        if ($line !== '') {
            $lastOutput = $line;
            // Parse progress from Python output
            if (str_contains($line, 'Training')) {
                updateStatus($taskId, ['progress' => 40, 'message' => $line]);
            } elseif (str_contains($line, 'Evaluating') || str_contains($line, 'evaluating')) {
                updateStatus($taskId, ['progress' => 70, 'message' => $line]);
            } elseif (str_contains($line, 'Saving') || str_contains($line, 'saving')) {
                updateStatus($taskId, ['progress' => 90, 'message' => $line]);
            } else {
                updateStatus($taskId, ['message' => $line]);
            }
        }
    });
    
    if (!$process->isSuccessful()) {
        updateStatus($taskId, [
            'status' => 'failed',
            'message' => 'Erreur entraînement: ' . $process->getErrorOutput(),
            'finished_at' => now()->toIso8601String()
        ]);
        return;
    }
    
    // Lire les métriques du modèle
    $metricsFile = base_path('ml_models/model_metrics.json');
    $metrics = file_exists($metricsFile) ? json_decode(file_get_contents($metricsFile), true) : null;
    
    updateStatus($taskId, [
        'status' => 'completed',
        'progress' => 100,
        'message' => 'Modèle entraîné avec succès',
        'metrics' => $metrics,
        'finished_at' => now()->toIso8601String()
    ]);
    
    Log::info("MLAsyncWorker - Entraînement terminé", ['task_id' => $taskId]);
}
