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
        case 'generate_report':
            runReportGeneration($taskId, $params);
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
    
    updateStatus($taskId, ['status' => 'running', 'message' => 'Extraction batch optimisée...', 'progress' => 5]);
    
    $featureService = app(\App\Services\MLFeatureExtractionService::class);
    $totalProcessed = 0;
    $currentDate = $startDate->copy();
    $totalDays = $startDate->diffInDays($endDate) + 1;
    $daysDone = 0;
    
    while ($currentDate->lte($endDate)) {
        $daysDone++;
        $dayBaseProgress = (($daysDone - 1) / $totalDays) * 100;
        $dayEndProgress = ($daysDone / $totalDays) * 100;
        
        $progressCallback = function (int $chunkIdx, int $totalChunks, int $processed, int $total) use ($taskId, $currentDate, $daysDone, $totalDays, $dayBaseProgress, $dayEndProgress) {
            $chunkProgress = $dayBaseProgress + (($chunkIdx / $totalChunks) * ($dayEndProgress - $dayBaseProgress));
            $progress = min(95, round($chunkProgress));
            updateStatus($taskId, [
                'status' => 'running',
                'progress' => $progress,
                'message' => "Jour {$daysDone}/{$totalDays} - {$currentDate->toDateString()} (chunk {$chunkIdx}/{$totalChunks}, {$processed}/{$total} clients)",
            ]);
        };
        
        $processedCount = $featureService->extractAndStoreFeaturesForDateBatch($currentDate, $progressCallback);
        $totalProcessed += $processedCount;
        
        updateStatus($taskId, [
            'status' => 'running',
            'progress' => min(95, round(($daysDone / $totalDays) * 100)),
            'message' => "Jour {$daysDone}/{$totalDays} - {$currentDate->toDateString()} ({$processedCount} features)",
            'total_processed' => $totalProcessed
        ]);
        
        $currentDate->addDay();
    }
    
    updateStatus($taskId, [
        'status' => 'completed',
        'progress' => 100,
        'message' => "Extraction batch terminée: {$totalProcessed} features extraites pour {$totalDays} jours",
        'total_processed' => $totalProcessed,
        'finished_at' => now()->toIso8601String()
    ]);
    
    Log::info("MLAsyncWorker - Extraction batch terminée", ['task_id' => $taskId, 'total' => $totalProcessed]);
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


function runReportGeneration(string $taskId, array $params): void
{
    updateStatus($taskId, ['status' => 'running', 'message' => 'Génération du rapport IA...', 'progress' => 10]);
    
    $pythonPath = env('PYTHON_PATH', '/root/.venv/bin/python3');
    $reportScript = base_path('ml_models/generate_report.py');
    
    $process = new \Symfony\Component\Process\Process(
        [$pythonPath, $reportScript],
        base_path(),
        [
            'DB_HOST' => env('DB_HOST'),
            'DB_PORT' => env('DB_PORT', '3306'),
            'DB_USERNAME' => env('DB_USERNAME'),
            'DB_PASSWORD' => env('DB_PASSWORD'),
            'DB_DATABASE' => env('DB_DATABASE'),
            'EMERGENT_LLM_KEY' => env('EMERGENT_LLM_KEY'),
        ],
        null,
        300
    );
    
    updateStatus($taskId, ['progress' => 30, 'message' => 'Collecte des métriques...']);
    
    $process->run(function ($type, $buffer) use ($taskId) {
        $line = trim($buffer);
        if ($line !== '') {
            if (str_contains($line, 'Generating')) {
                updateStatus($taskId, ['progress' => 50, 'message' => 'Génération IA en cours...']);
            } elseif (str_contains($line, 'Report saved')) {
                updateStatus($taskId, ['progress' => 90, 'message' => 'Rapport sauvegardé']);
            }
        }
    });
    
    if (!$process->isSuccessful()) {
        updateStatus($taskId, [
            'status' => 'failed',
            'message' => 'Erreur génération rapport: ' . $process->getErrorOutput(),
            'finished_at' => now()->toIso8601String()
        ]);
        return;
    }
    
    // Find the latest report file
    $reportsDir = storage_path('app/ml_reports');
    $reports = glob($reportsDir . '/weekly_report_*.json');
    $latestReport = !empty($reports) ? end($reports) : null;
    $reportData = $latestReport ? json_decode(file_get_contents($latestReport), true) : null;
    
    updateStatus($taskId, [
        'status' => 'completed',
        'progress' => 100,
        'message' => 'Rapport IA généré avec succès',
        'report' => $reportData['report'] ?? null,
        'report_file' => $latestReport ? basename($latestReport) : null,
        'finished_at' => now()->toIso8601String()
    ]);
    
    Log::info("MLAsyncWorker - Rapport généré", ['task_id' => $taskId]);
}
