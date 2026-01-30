<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class MonitoringService
{
    private const METRICS_CACHE_KEY = 'dashboard_metrics';
    private const METRICS_TTL = 300; // 5 minutes
    
    /**
     * Enregistrer une métrique de performance
     */
    public function recordMetric(string $type, array $data): void
    {
        $metric = [
            'type' => $type,
            'timestamp' => now()->toISOString(),
            'data' => $data
        ];
        
        // Stocker en cache pour agrégation
        $this->storeMetricInCache($metric);
        
        // Log si nécessaire
        if ($this->shouldLogMetric($type, $data)) {
            Log::info("Métrique Dashboard: {$type}", $metric);
        }
    }
    
    /**
     * Enregistrer les métriques d'une requête API
     */
    public function recordApiRequest(array $params, array $metrics): void
    {
        $this->recordMetric('api_request', [
            'endpoint' => 'dashboard_data',
            'operator' => $params['operator'] ?? 'unknown',
            'period_days' => $params['period_days'] ?? 0,
            'execution_time_ms' => $metrics['execution_time_ms'] ?? 0,
            'memory_used_mb' => $metrics['memory_used_mb'] ?? 0,
            'query_count' => $metrics['query_count'] ?? 0,
            'cache_hit' => $metrics['cache_hit'] ?? false,
            'status' => $metrics['status'] ?? 'success'
        ]);
    }
    
    /**
     * Enregistrer les métriques de cache
     */
    public function recordCacheMetric(string $operation, string $key, array $data = []): void
    {
        $this->recordMetric('cache_operation', [
            'operation' => $operation, // hit, miss, put, invalidate
            'key' => $key,
            'ttl' => $data['ttl'] ?? null,
            'size_kb' => isset($data['data']) ? round(strlen(serialize($data['data'])) / 1024, 2) : null
        ]);
    }
    
    /**
     * Enregistrer une erreur avec contexte
     */
    public function recordError(string $type, \Exception $e, array $context = []): void
    {
        $this->recordMetric('error', [
            'error_type' => $type,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'context' => $context
        ]);
    }
    
    /**
     * Obtenir les métriques agrégées
     */
    public function getAggregatedMetrics(int $hours = 24): array
    {
        $cacheKey = self::METRICS_CACHE_KEY . "_aggregated_{$hours}h";
        
        return Cache::remember($cacheKey, self::METRICS_TTL, function() use ($hours) {
            $metrics = $this->getMetricsFromCache($hours);
            return $this->aggregateMetrics($metrics);
        });
    }
    
    /**
     * Obtenir les métriques de performance en temps réel
     */
    public function getPerformanceMetrics(): array
    {
        $metrics = $this->getAggregatedMetrics(1); // Dernière heure
        
        return [
            'requests' => [
                'total' => $metrics['api_request']['count'] ?? 0,
                'avg_response_time_ms' => $metrics['api_request']['avg_execution_time_ms'] ?? 0,
                'slow_requests' => $metrics['api_request']['slow_count'] ?? 0,
                'error_rate' => $this->calculateErrorRate($metrics)
            ],
            'cache' => [
                'hit_rate' => $this->calculateCacheHitRate($metrics),
                'total_operations' => $metrics['cache_operation']['count'] ?? 0,
                'avg_size_kb' => $metrics['cache_operation']['avg_size_kb'] ?? 0
            ],
            'database' => [
                'avg_queries_per_request' => $metrics['api_request']['avg_query_count'] ?? 0,
                'slow_queries' => $metrics['api_request']['slow_query_count'] ?? 0
            ],
            'memory' => [
                'avg_usage_mb' => $metrics['api_request']['avg_memory_used_mb'] ?? 0,
                'peak_usage_mb' => $metrics['api_request']['max_memory_used_mb'] ?? 0
            ],
            'errors' => [
                'total' => $metrics['error']['count'] ?? 0,
                'by_type' => $metrics['error']['by_type'] ?? []
            ]
        ];
    }
    
    /**
     * Obtenir les tendances de performance
     */
    public function getPerformanceTrends(int $days = 7): array
    {
        $trends = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dayMetrics = $this->getMetricsForDate($date);
            
            $trends[] = [
                'date' => $date->toDateString(),
                'requests' => $dayMetrics['api_request']['count'] ?? 0,
                'avg_response_time_ms' => $dayMetrics['api_request']['avg_execution_time_ms'] ?? 0,
                'error_count' => $dayMetrics['error']['count'] ?? 0,
                'cache_hit_rate' => $this->calculateCacheHitRate($dayMetrics)
            ];
        }
        
        return $trends;
    }
    
    /**
     * Détecter les anomalies de performance
     */
    public function detectAnomalies(): array
    {
        $currentMetrics = $this->getPerformanceMetrics();
        $historicalMetrics = $this->getAggregatedMetrics(24 * 7); // 7 jours
        
        $anomalies = [];
        
        // Anomalie temps de réponse
        $currentAvgTime = $currentMetrics['requests']['avg_response_time_ms'];
        $historicalAvgTime = $historicalMetrics['api_request']['avg_execution_time_ms'] ?? 0;
        
        if ($currentAvgTime > $historicalAvgTime * 2 && $currentAvgTime > 5000) {
            $anomalies[] = [
                'type' => 'slow_response',
                'severity' => 'high',
                'message' => "Temps de réponse anormalement élevé: {$currentAvgTime}ms vs {$historicalAvgTime}ms (historique)",
                'current_value' => $currentAvgTime,
                'historical_value' => $historicalAvgTime
            ];
        }
        
        // Anomalie taux d'erreur
        $currentErrorRate = $currentMetrics['requests']['error_rate'];
        $historicalErrorRate = $this->calculateErrorRate($historicalMetrics);
        
        if ($currentErrorRate > $historicalErrorRate * 3 && $currentErrorRate > 0.05) {
            $anomalies[] = [
                'type' => 'high_error_rate',
                'severity' => 'high',
                'message' => "Taux d'erreur anormalement élevé: {$currentErrorRate}% vs {$historicalErrorRate}% (historique)",
                'current_value' => $currentErrorRate,
                'historical_value' => $historicalErrorRate
            ];
        }
        
        // Anomalie cache hit rate
        $currentCacheHitRate = $currentMetrics['cache']['hit_rate'];
        $historicalCacheHitRate = $this->calculateCacheHitRate($historicalMetrics);
        
        if ($currentCacheHitRate < $historicalCacheHitRate * 0.5 && $currentCacheHitRate < 0.5) {
            $anomalies[] = [
                'type' => 'low_cache_hit_rate',
                'severity' => 'medium',
                'message' => "Taux de cache hit anormalement bas: {$currentCacheHitRate}% vs {$historicalCacheHitRate}% (historique)",
                'current_value' => $currentCacheHitRate,
                'historical_value' => $historicalCacheHitRate
            ];
        }
        
        return $anomalies;
    }
    
    /**
     * Générer un rapport de santé du système
     */
    public function getHealthReport(): array
    {
        $metrics = $this->getPerformanceMetrics();
        $anomalies = $this->detectAnomalies();
        
        // Calcul du score de santé (0-100)
        $healthScore = $this->calculateHealthScore($metrics, $anomalies);
        
        return [
            'health_score' => $healthScore,
            'status' => $this->getHealthStatus($healthScore),
            'metrics' => $metrics,
            'anomalies' => $anomalies,
            'recommendations' => $this->generateRecommendations($metrics, $anomalies),
            'generated_at' => now()->toISOString()
        ];
    }
    
    /**
     * Stocker une métrique en cache
     */
    private function storeMetricInCache(array $metric): void
    {
        $cacheKey = self::METRICS_CACHE_KEY . '_' . now()->format('Y-m-d-H');
        $metrics = Cache::get($cacheKey, []);
        $metrics[] = $metric;
        
        // Limiter à 1000 métriques par heure
        if (count($metrics) > 1000) {
            $metrics = array_slice($metrics, -1000);
        }
        
        Cache::put($cacheKey, $metrics, 3600); // 1 heure
    }
    
    /**
     * Récupérer les métriques depuis le cache
     */
    private function getMetricsFromCache(int $hours): array
    {
        $allMetrics = [];
        
        for ($i = 0; $i < $hours; $i++) {
            $hour = now()->subHours($i);
            $cacheKey = self::METRICS_CACHE_KEY . '_' . $hour->format('Y-m-d-H');
            $hourMetrics = Cache::get($cacheKey, []);
            $allMetrics = array_merge($allMetrics, $hourMetrics);
        }
        
        return $allMetrics;
    }
    
    /**
     * Agréger les métriques
     */
    private function aggregateMetrics(array $metrics): array
    {
        $aggregated = [];
        
        foreach ($metrics as $metric) {
            $type = $metric['type'];
            
            if (!isset($aggregated[$type])) {
                $aggregated[$type] = [
                    'count' => 0,
                    'data' => []
                ];
            }
            
            $aggregated[$type]['count']++;
            
            // Agrégation spécifique par type
            switch ($type) {
                case 'api_request':
                    $this->aggregateApiRequestMetrics($aggregated[$type], $metric['data']);
                    break;
                case 'cache_operation':
                    $this->aggregateCacheMetrics($aggregated[$type], $metric['data']);
                    break;
                case 'error':
                    $this->aggregateErrorMetrics($aggregated[$type], $metric['data']);
                    break;
            }
        }
        
        return $aggregated;
    }
    
    /**
     * Agréger les métriques de requêtes API
     */
    private function aggregateApiRequestMetrics(array &$aggregated, array $data): void
    {
        $fields = ['execution_time_ms', 'memory_used_mb', 'query_count'];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                if (!isset($aggregated['data'][$field])) {
                    $aggregated['data'][$field] = [];
                }
                $aggregated['data'][$field][] = $data[$field];
            }
        }
        
        // Calculer les moyennes
        foreach ($fields as $field) {
            if (isset($aggregated['data'][$field])) {
                $values = $aggregated['data'][$field];
                $aggregated["avg_{$field}"] = round(array_sum($values) / count($values), 2);
                $aggregated["max_{$field}"] = max($values);
                $aggregated["min_{$field}"] = min($values);
            }
        }
        
        // Compter les requêtes lentes
        if (isset($data['execution_time_ms']) && $data['execution_time_ms'] > 5000) {
            $aggregated['slow_count'] = ($aggregated['slow_count'] ?? 0) + 1;
        }
        
        // Compter les cache hits
        if (isset($data['cache_hit'])) {
            $aggregated['cache_hits'] = ($aggregated['cache_hits'] ?? 0) + ($data['cache_hit'] ? 1 : 0);
        }
    }
    
    /**
     * Calculer le taux d'erreur
     */
    private function calculateErrorRate(array $metrics): float
    {
        $totalRequests = $metrics['api_request']['count'] ?? 0;
        $totalErrors = $metrics['error']['count'] ?? 0;
        
        return $totalRequests > 0 ? round(($totalErrors / $totalRequests) * 100, 2) : 0;
    }
    
    /**
     * Calculer le taux de cache hit
     */
    private function calculateCacheHitRate(array $metrics): float
    {
        $totalRequests = $metrics['api_request']['count'] ?? 0;
        $cacheHits = $metrics['api_request']['cache_hits'] ?? 0;
        
        return $totalRequests > 0 ? round(($cacheHits / $totalRequests) * 100, 2) : 0;
    }
    
    /**
     * Calculer le score de santé
     */
    private function calculateHealthScore(array $metrics, array $anomalies): int
    {
        $score = 100;
        
        // Pénalités pour les anomalies
        foreach ($anomalies as $anomaly) {
            $penalty = match($anomaly['severity']) {
                'high' => 20,
                'medium' => 10,
                'low' => 5,
                default => 5
            };
            $score -= $penalty;
        }
        
        // Pénalités pour les métriques dégradées
        if ($metrics['requests']['avg_response_time_ms'] > 10000) {
            $score -= 15;
        } elseif ($metrics['requests']['avg_response_time_ms'] > 5000) {
            $score -= 10;
        }
        
        if ($metrics['requests']['error_rate'] > 10) {
            $score -= 20;
        } elseif ($metrics['requests']['error_rate'] > 5) {
            $score -= 10;
        }
        
        if ($metrics['cache']['hit_rate'] < 30) {
            $score -= 15;
        } elseif ($metrics['cache']['hit_rate'] < 50) {
            $score -= 10;
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Déterminer le statut de santé
     */
    private function getHealthStatus(int $score): string
    {
        return match(true) {
            $score >= 90 => 'excellent',
            $score >= 75 => 'good',
            $score >= 60 => 'fair',
            $score >= 40 => 'poor',
            default => 'critical'
        };
    }
    
    /**
     * Générer des recommandations
     */
    private function generateRecommendations(array $metrics, array $anomalies): array
    {
        $recommendations = [];
        
        if ($metrics['requests']['avg_response_time_ms'] > 5000) {
            $recommendations[] = "Optimiser les requêtes lentes - temps de réponse moyen: {$metrics['requests']['avg_response_time_ms']}ms";
        }
        
        if ($metrics['cache']['hit_rate'] < 50) {
            $recommendations[] = "Améliorer la stratégie de cache - taux de hit: {$metrics['cache']['hit_rate']}%";
        }
        
        if ($metrics['database']['avg_queries_per_request'] > 20) {
            $recommendations[] = "Réduire le nombre de requêtes DB par requête - moyenne: {$metrics['database']['avg_queries_per_request']}";
        }
        
        if ($metrics['memory']['avg_usage_mb'] > 200) {
            $recommendations[] = "Optimiser l'utilisation mémoire - moyenne: {$metrics['memory']['avg_usage_mb']}MB";
        }
        
        foreach ($anomalies as $anomaly) {
            if ($anomaly['severity'] === 'high') {
                $recommendations[] = "URGENT: " . $anomaly['message'];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Déterminer si une métrique doit être loggée
     */
    private function shouldLogMetric(string $type, array $data): bool
    {
        // Log les erreurs et les requêtes lentes
        if ($type === 'error') {
            return true;
        }
        
        if ($type === 'api_request' && isset($data['execution_time_ms']) && $data['execution_time_ms'] > 5000) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Agréger les métriques de cache
     */
    private function aggregateCacheMetrics(array &$aggregated, array $data): void
    {
        if (isset($data['operation'])) {
            $operation = $data['operation'];
            $aggregated['by_operation'][$operation] = ($aggregated['by_operation'][$operation] ?? 0) + 1;
        }
        
        if (isset($data['size_kb'])) {
            $aggregated['data']['size_kb'][] = $data['size_kb'];
            $sizes = $aggregated['data']['size_kb'];
            $aggregated['avg_size_kb'] = round(array_sum($sizes) / count($sizes), 2);
        }
    }
    
    /**
     * Agréger les métriques d'erreur
     */
    private function aggregateErrorMetrics(array &$aggregated, array $data): void
    {
        if (isset($data['error_type'])) {
            $type = $data['error_type'];
            $aggregated['by_type'][$type] = ($aggregated['by_type'][$type] ?? 0) + 1;
        }
    }
    
    /**
     * Obtenir les métriques pour une date spécifique
     */
    private function getMetricsForDate(Carbon $date): array
    {
        $metrics = [];
        
        for ($hour = 0; $hour < 24; $hour++) {
            $cacheKey = self::METRICS_CACHE_KEY . '_' . $date->format('Y-m-d') . '-' . sprintf('%02d', $hour);
            $hourMetrics = Cache::get($cacheKey, []);
            $metrics = array_merge($metrics, $hourMetrics);
        }
        
        return $this->aggregateMetrics($metrics);
    }
}

