<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DashboardPerformanceMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        // Configuration des limites
        $maxExecutionTime = config('dashboard.performance.api_timeout', 60);
        $maxMemoryMB = config('dashboard.performance.max_memory_mb', 512);
        $enableQueryLog = config('dashboard.database.enable_query_log', false);
        
        // Définir la limite de temps d'exécution
        set_time_limit($maxExecutionTime);
        
        // Définir la limite de mémoire
        ini_set('memory_limit', $maxMemoryMB . 'M');
        
        // Activer le log des requêtes si configuré
        if ($enableQueryLog) {
            DB::enableQueryLog();
        }
        
        // Middleware de monitoring
        $this->logRequestStart($request, $startTime, $startMemory);
        
        try {
            // Exécuter la requête
            $response = $next($request);
            
            // Monitoring post-exécution
            $this->logRequestEnd($request, $response, $startTime, $startMemory);
            
            // Ajouter les headers de performance
            $this->addPerformanceHeaders($response, $startTime, $startMemory);
            
            return $response;
            
        } catch (\Exception $e) {
            // Log des erreurs avec contexte de performance
            $this->logRequestError($request, $e, $startTime, $startMemory);
            throw $e;
        }
    }
    
    /**
     * Log du début de requête
     */
    private function logRequestStart(Request $request, float $startTime, int $startMemory): void
    {
        if (!config('dashboard.monitoring.enable_performance_log', true)) {
            return;
        }
        
        Log::info("=== DÉBUT REQUÊTE DASHBOARD ===", [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_id' => auth()->id(),
            'start_time' => $startTime,
            'start_memory_mb' => round($startMemory / 1024 / 1024, 2),
            'params' => $request->only(['start_date', 'end_date', 'operator']),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip()
        ]);
    }
    
    /**
     * Log de fin de requête avec métriques
     */
    private function logRequestEnd(Request $request, Response $response, float $startTime, int $startMemory): void
    {
        if (!config('dashboard.monitoring.enable_performance_log', true)) {
            return;
        }
        
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        $memoryUsed = memory_get_usage(true) - $startMemory;
        $peakMemory = memory_get_peak_usage(true);
        
        $metrics = [
            'execution_time_ms' => $executionTime,
            'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
            'peak_memory_mb' => round($peakMemory / 1024 / 1024, 2),
            'response_size_kb' => round(strlen($response->getContent()) / 1024, 2),
            'status_code' => $response->getStatusCode()
        ];
        
        // Ajouter les métriques de base de données si disponibles
        if (config('dashboard.database.enable_query_log', false)) {
            $queries = DB::getQueryLog();
            $metrics['query_count'] = count($queries);
            $metrics['total_query_time_ms'] = round(array_sum(array_column($queries, 'time')), 2);
        }
        
        $logLevel = $this->determineLogLevel($executionTime, $memoryUsed);
        
        Log::log($logLevel, "=== FIN REQUÊTE DASHBOARD ===", array_merge([
            'url' => $request->fullUrl(),
            'user_id' => auth()->id()
        ], $metrics));
        
        // Alertes si seuils dépassés
        $this->checkPerformanceThresholds($metrics, $request);
    }
    
    /**
     * Log des erreurs avec contexte de performance
     */
    private function logRequestError(Request $request, \Exception $e, float $startTime, int $startMemory): void
    {
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        $memoryUsed = memory_get_usage(true) - $startMemory;
        
        Log::error("=== ERREUR REQUÊTE DASHBOARD ===", [
            'url' => $request->fullUrl(),
            'user_id' => auth()->id(),
            'error_message' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'execution_time_ms' => $executionTime,
            'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
            'params' => $request->only(['start_date', 'end_date', 'operator']),
            'stack_trace' => $e->getTraceAsString()
        ]);
    }
    
    /**
     * Ajouter les headers de performance à la réponse
     */
    private function addPerformanceHeaders(Response $response, float $startTime, int $startMemory): void
    {
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        $memoryUsed = memory_get_usage(true) - $startMemory;
        
        $response->headers->set('X-Execution-Time', $executionTime . 'ms');
        $response->headers->set('X-Memory-Used', round($memoryUsed / 1024 / 1024, 2) . 'MB');
        $response->headers->set('X-Peak-Memory', round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB');
        $response->headers->set('X-Dashboard-Version', 'optimized_v2');
        
        if (config('dashboard.database.enable_query_log', false)) {
            $queries = DB::getQueryLog();
            $response->headers->set('X-Query-Count', count($queries));
            $response->headers->set('X-Query-Time', round(array_sum(array_column($queries, 'time')), 2) . 'ms');
        }
    }
    
    /**
     * Déterminer le niveau de log selon les performances
     */
    private function determineLogLevel(float $executionTime, int $memoryUsed): string
    {
        $slowQueryThreshold = config('dashboard.monitoring.slow_query_threshold', 5000);
        $memoryAlertThreshold = config('dashboard.performance.max_memory_mb', 512) * 1024 * 1024 * 0.8;
        
        if ($executionTime > $slowQueryThreshold || $memoryUsed > $memoryAlertThreshold) {
            return 'warning';
        }
        
        if ($executionTime > $slowQueryThreshold / 2) {
            return 'notice';
        }
        
        return 'info';
    }
    
    /**
     * Vérifier les seuils de performance et déclencher des alertes
     */
    private function checkPerformanceThresholds(array $metrics, Request $request): void
    {
        $slowQueryThreshold = config('dashboard.monitoring.slow_query_threshold', 5000);
        $memoryAlertThreshold = config('dashboard.performance.max_memory_mb', 512) * 0.8;
        
        $alerts = [];
        
        // Alerte temps d'exécution
        if ($metrics['execution_time_ms'] > $slowQueryThreshold) {
            $alerts[] = [
                'type' => 'slow_query',
                'threshold' => $slowQueryThreshold,
                'actual' => $metrics['execution_time_ms'],
                'message' => "Requête lente détectée: {$metrics['execution_time_ms']}ms > {$slowQueryThreshold}ms"
            ];
        }
        
        // Alerte mémoire
        if ($metrics['peak_memory_mb'] > $memoryAlertThreshold) {
            $alerts[] = [
                'type' => 'high_memory',
                'threshold' => $memoryAlertThreshold,
                'actual' => $metrics['peak_memory_mb'],
                'message' => "Utilisation mémoire élevée: {$metrics['peak_memory_mb']}MB > {$memoryAlertThreshold}MB"
            ];
        }
        
        // Alerte nombre de requêtes
        if (isset($metrics['query_count']) && $metrics['query_count'] > 50) {
            $alerts[] = [
                'type' => 'high_query_count',
                'threshold' => 50,
                'actual' => $metrics['query_count'],
                'message' => "Nombre élevé de requêtes DB: {$metrics['query_count']} > 50"
            ];
        }
        
        // Envoyer les alertes
        foreach ($alerts as $alert) {
            $this->sendPerformanceAlert($alert, $request, $metrics);
        }
    }
    
    /**
     * Envoyer une alerte de performance
     */
    private function sendPerformanceAlert(array $alert, Request $request, array $metrics): void
    {
        Log::warning("=== ALERTE PERFORMANCE DASHBOARD ===", [
            'alert' => $alert,
            'url' => $request->fullUrl(),
            'user_id' => auth()->id(),
            'params' => $request->only(['start_date', 'end_date', 'operator']),
            'metrics' => $metrics,
            'timestamp' => now()->toISOString()
        ]);
        
        // Ici on pourrait ajouter l'envoi d'emails, notifications Slack, etc.
        // selon la configuration
    }
    
    /**
     * Middleware de nettoyage post-requête
     */
    public function terminate(Request $request, Response $response): void
    {
        // Nettoyage des logs de requêtes si activés
        if (config('dashboard.database.enable_query_log', false)) {
            DB::flushQueryLog();
        }
        
        // Forcer le garbage collection pour les longues requêtes
        if (memory_get_peak_usage(true) > 100 * 1024 * 1024) { // > 100MB
            gc_collect_cycles();
        }
    }
}

