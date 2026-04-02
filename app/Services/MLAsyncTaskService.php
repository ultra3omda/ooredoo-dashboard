<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Gère l'exécution de tâches ML longues en arrière-plan
 * Utilise un fichier de statut pour le suivi au lieu de Laravel Queue (pas de worker requis)
 */
class MLAsyncTaskService
{
    private string $statusDir;

    public function __construct()
    {
        $this->statusDir = storage_path('app/ml_tasks');
        if (!is_dir($this->statusDir)) {
            mkdir($this->statusDir, 0755, true);
        }
    }

    /**
     * Lance une tâche en arrière-plan
     */
    public function startTask(string $taskType, array $params = []): string
    {
        $taskId = $taskType . '_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6);
        
        $this->writeStatus($taskId, [
            'task_id' => $taskId,
            'type' => $taskType,
            'status' => 'running',
            'progress' => 0,
            'message' => 'Démarrage...',
            'started_at' => now()->toIso8601String(),
            'params' => $params,
        ]);
        
        // Lance le script PHP worker en arrière-plan
        $workerScript = base_path('ml_models/async_worker.php');
        $paramsJson = base64_encode(json_encode([
            'task_id' => $taskId,
            'task_type' => $taskType,
            'params' => $params,
        ]));
        
        $cmd = sprintf(
            'php %s %s > %s/log_%s.txt 2>&1 &',
            escapeshellarg($workerScript),
            escapeshellarg($paramsJson),
            escapeshellarg($this->statusDir),
            $taskId
        );
        
        exec($cmd);
        Log::info("MLAsyncTask - Tâche lancée", ['task_id' => $taskId, 'type' => $taskType]);
        
        return $taskId;
    }

    /**
     * Récupère le statut d'une tâche
     */
    public function getTaskStatus(string $taskId): ?array
    {
        $file = $this->statusDir . '/' . $taskId . '.json';
        if (!file_exists($file)) {
            return null;
        }
        return json_decode(file_get_contents($file), true);
    }

    /**
     * Écrit le statut d'une tâche
     */
    public function writeStatus(string $taskId, array $status): void
    {
        $file = $this->statusDir . '/' . $taskId . '.json';
        file_put_contents($file, json_encode($status, JSON_PRETTY_PRINT));
    }

    /**
     * Récupère le dernier statut d'un type de tâche
     */
    public function getLatestTaskOfType(string $taskType): ?array
    {
        $files = glob($this->statusDir . '/' . $taskType . '_*.json');
        if (empty($files)) {
            return null;
        }
        sort($files);
        $latest = end($files);
        return json_decode(file_get_contents($latest), true);
    }
}
