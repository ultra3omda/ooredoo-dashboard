<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class MonitoringController extends Controller
{
    protected AlertService $alertService;

    public function __construct(AlertService $alertService)
    {
        $this->alertService = $alertService;
    }

    /**
     * Dashboard de monitoring - Métriques temps réel
     */
    public function dashboard(): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            // 1. Test connexion MySQL
            $mysqlLatency = $this->measureMysqlLatency();
            
            // 2. Test connexion Redis
            $redisLatency = $this->measureRedisLatency();
            
            // 3. Cache stats
            $cacheStats = $this->getCacheStats();
            
            // 4. Database stats
            $dbStats = $this->getDatabaseStats();
            
            // 5. API response time history (from cache)
            $apiHistory = $this->getApiResponseHistory();
            
            $totalTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return response()->json([
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'monitoring_time_ms' => $totalTime,
                'services' => [
                    'mysql' => [
                        'status' => $mysqlLatency < 5000 ? 'healthy' : 'degraded',
                        'latency_ms' => $mysqlLatency,
                        'host' => config('database.connections.mysql.host'),
                    ],
                    'redis' => [
                        'status' => $redisLatency < 1000 ? 'healthy' : 'degraded',
                        'latency_ms' => $redisLatency,
                        'host' => config('database.redis.default.host'),
                    ],
                ],
                'cache' => $cacheStats,
                'database' => $dbStats,
                'api_history' => $apiHistory,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Enregistre un temps de réponse API pour le monitoring
     */
    public function recordApiTime(): JsonResponse
    {
        $time = request()->input('time_ms', 0);
        $endpoint = request()->input('endpoint', 'unknown');
        $cacheHit = request()->input('cache_hit', false);
        
        $history = Cache::get('monitoring:api_history', []);
        $history[] = [
            'endpoint' => $endpoint,
            'time_ms' => $time,
            'cache_hit' => $cacheHit,
            'timestamp' => now()->toISOString(),
        ];
        
        // Garder les 100 dernières entrées
        $history = array_slice($history, -100);
        Cache::put('monitoring:api_history', $history, 3600);
        
        return response()->json(['recorded' => true]);
    }

    private function measureMysqlLatency(): float
    {
        $start = microtime(true);
        try {
            DB::select('SELECT 1');
            return round((microtime(true) - $start) * 1000, 2);
        } catch (\Exception $e) {
            return -1;
        }
    }

    private function measureRedisLatency(): float
    {
        $start = microtime(true);
        try {
            Cache::store('redis')->put('monitoring:ping', 'pong', 10);
            Cache::store('redis')->get('monitoring:ping');
            return round((microtime(true) - $start) * 1000, 2);
        } catch (\Exception $e) {
            return -1;
        }
    }

    private function getCacheStats(): array
    {
        try {
            $redis = Redis::connection('cache');
            $info = $redis->info();
            
            $dashboardKeys = count($redis->keys('*dashboard*'));
            $sectionKeys = count($redis->keys('*dashboard_section*'));
            
            return [
                'driver' => config('cache.default'),
                'total_keys' => $dashboardKeys,
                'section_cached_keys' => $sectionKeys,
                'memory_used' => $info['used_memory_human'] ?? 'N/A',
                'hit_rate' => isset($info['keyspace_hits'], $info['keyspace_misses']) && ($info['keyspace_hits'] + $info['keyspace_misses']) > 0
                    ? round($info['keyspace_hits'] / ($info['keyspace_hits'] + $info['keyspace_misses']) * 100, 1) . '%'
                    : 'N/A',
                'hits' => $info['keyspace_hits'] ?? 0,
                'misses' => $info['keyspace_misses'] ?? 0,
                'uptime_seconds' => $info['uptime_in_seconds'] ?? 0,
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'driver' => config('cache.default')];
        }
    }

    private function getDatabaseStats(): array
    {
        // Use cached counts to avoid scanning millions of rows on each call
        return Cache::remember('monitoring:db_stats', 300, function() {
            try {
                return [
                    'clients' => DB::table('client')->count(),
                    'subscriptions' => DB::table('client_abonnement')->count(),
                    'transactions_history' => DB::table('transactions_history')->count(),
                    'history' => DB::table('history')->count(),
                    'partners' => DB::table('partner')->where('partener_active', 1)->count(),
                    'users' => DB::table('users')->where('status', 'active')->count(),
                ];
            } catch (\Exception $e) {
                return ['error' => $e->getMessage()];
            }
        });
    }

    private function getApiResponseHistory(): array
    {
        return Cache::get('monitoring:api_history', []);
    }

    public function healthCheck(): JsonResponse
    {
        $results = $this->alertService->runHealthChecks();
        $statusCode = $results['overall_status'] === 'healthy' ? 200 : ($results['overall_status'] === 'critical' ? 503 : 200);
        return response()->json($results, $statusCode);
    }

    public function getAlerts(Request $request): JsonResponse
    {
        $unackOnly = $request->boolean('unacknowledged_only', false);
        return response()->json([
            'alerts' => $this->alertService->getAlerts($unackOnly),
            'stats' => $this->alertService->getAlertStats(),
        ]);
    }

    public function acknowledgeAlert(Request $request, string $alertId): JsonResponse
    {
        $success = $this->alertService->acknowledgeAlert($alertId);
        return response()->json(['success' => $success]);
    }

    public function acknowledgeAllAlerts(): JsonResponse
    {
        $count = $this->alertService->acknowledgeAll();
        return response()->json(['acknowledged' => $count]);
    }

    public function clearAlerts(): JsonResponse
    {
        $this->alertService->clearAlerts();
        return response()->json(['cleared' => true]);
    }

    public function warmupStatus(): JsonResponse
    {
        $periods = ['14d', '30d', '90d', 'lifetime'];
        $sections = ['kpis', 'subscriptions', 'transactions', 'merchants', 'timwe', 'ooredoo'];
        $operators = ['ALL'];
        $status = [];

        foreach ($periods as $period) {
            foreach ($sections as $section) {
                foreach ($operators as $op) {
                    $cacheKey = "split:{$section}:{$period}:{$op}";
                    $exists = Cache::has($cacheKey);
                    $ttl = 0;
                    try {
                        if ($exists) {
                            $redis = Cache::getStore()->getRedis()->connection();
                            $prefix = config('cache.prefix', 'laravel_cache');
                            $ttl = $redis->ttl("{$prefix}:{$cacheKey}");
                        }
                    } catch (\Exception $e) {}

                    $status[] = [
                        'period' => $period,
                        'section' => $section,
                        'operator' => $op,
                        'cached' => $exists,
                        'ttl_seconds' => max(0, $ttl),
                        'expires_in' => $ttl > 0 ? Carbon::now()->addSeconds($ttl)->diffForHumans() : 'N/A',
                    ];
                }
            }
        }

        $totalCached = count(array_filter($status, fn($s) => $s['cached']));
        $totalExpected = count($status);

        return response()->json([
            'total_cached' => $totalCached,
            'total_expected' => $totalExpected,
            'coverage_pct' => $totalExpected > 0 ? round(($totalCached / $totalExpected) * 100, 1) : 0,
            'last_warmup' => Cache::get('monitoring:last_warmup', 'N/A'),
            'details' => $status,
        ]);
    }
}
