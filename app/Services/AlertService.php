<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AlertService
{
    const CACHE_KEY = 'monitoring:alerts';
    const MAX_ALERTS = 100;

    const SEVERITY_CRITICAL = 'critical';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_INFO = 'info';

    public function createAlert(string $type, string $message, string $severity = self::SEVERITY_WARNING, array $metadata = []): array
    {
        $alert = [
            'id' => uniqid('alert_'),
            'type' => $type,
            'message' => $message,
            'severity' => $severity,
            'metadata' => $metadata,
            'created_at' => Carbon::now()->toIso8601String(),
            'acknowledged' => false,
            'acknowledged_at' => null,
        ];

        $alerts = $this->getAlerts();
        array_unshift($alerts, $alert);
        $alerts = array_slice($alerts, 0, self::MAX_ALERTS);
        Cache::put(self::CACHE_KEY, $alerts, 86400);

        Log::channel('daily')->warning("Alert [{$severity}]: {$type} - {$message}", $metadata);

        return $alert;
    }

    public function getAlerts(bool $unacknowledgedOnly = false): array
    {
        $alerts = Cache::get(self::CACHE_KEY, []);
        if ($unacknowledgedOnly) {
            return array_values(array_filter($alerts, fn($a) => !$a['acknowledged']));
        }
        return $alerts;
    }

    public function acknowledgeAlert(string $alertId): bool
    {
        $alerts = $this->getAlerts();
        foreach ($alerts as &$alert) {
            if ($alert['id'] === $alertId) {
                $alert['acknowledged'] = true;
                $alert['acknowledged_at'] = Carbon::now()->toIso8601String();
                Cache::put(self::CACHE_KEY, $alerts, 86400);
                return true;
            }
        }
        return false;
    }

    public function acknowledgeAll(): int
    {
        $alerts = $this->getAlerts();
        $count = 0;
        foreach ($alerts as &$alert) {
            if (!$alert['acknowledged']) {
                $alert['acknowledged'] = true;
                $alert['acknowledged_at'] = Carbon::now()->toIso8601String();
                $count++;
            }
        }
        Cache::put(self::CACHE_KEY, $alerts, 86400);
        return $count;
    }

    public function clearAlerts(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function getAlertStats(): array
    {
        $alerts = $this->getAlerts();
        $unack = array_filter($alerts, fn($a) => !$a['acknowledged']);
        $bySeverity = ['critical' => 0, 'warning' => 0, 'info' => 0];
        foreach ($unack as $a) {
            $bySeverity[$a['severity']] = ($bySeverity[$a['severity']] ?? 0) + 1;
        }
        return [
            'total' => count($alerts),
            'unacknowledged' => count($unack),
            'by_severity' => $bySeverity,
        ];
    }

    public function runHealthChecks(): array
    {
        $results = [];

        // 1. Database
        $results['database'] = $this->checkDatabase();

        // 2. Redis
        $results['redis'] = $this->checkRedis();

        // 3. Warmup Cache
        $results['warmup_cache'] = $this->checkWarmupCache();

        // 4. Disk Space
        $results['disk'] = $this->checkDiskSpace();

        // 5. API Endpoints
        $results['api_endpoints'] = $this->checkApiEndpoints();

        // Create alerts for failures
        foreach ($results as $component => $check) {
            if ($check['status'] === 'critical') {
                $this->createAlert("health_check_{$component}", $check['message'], self::SEVERITY_CRITICAL, ['component' => $component]);
            } elseif ($check['status'] === 'warning') {
                $this->createAlert("health_check_{$component}", $check['message'], self::SEVERITY_WARNING, ['component' => $component]);
            }
        }

        $overall = 'healthy';
        foreach ($results as $r) {
            if ($r['status'] === 'critical') { $overall = 'critical'; break; }
            if ($r['status'] === 'warning') { $overall = 'warning'; }
        }

        $summary = [
            'overall_status' => $overall,
            'checks' => $results,
            'checked_at' => Carbon::now()->toIso8601String(),
        ];

        Cache::put('monitoring:health_check', $summary, 600);

        return $summary;
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 1);

            $tableCount = DB::select("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE()")[0]->cnt;

            if ($latency > 1000) {
                return ['status' => 'warning', 'message' => "Latence DB élevée: {$latency}ms", 'latency_ms' => $latency, 'tables' => $tableCount];
            }
            return ['status' => 'healthy', 'message' => "OK ({$latency}ms, {$tableCount} tables)", 'latency_ms' => $latency, 'tables' => $tableCount];
        } catch (\Exception $e) {
            return ['status' => 'critical', 'message' => "DB inaccessible: " . $e->getMessage(), 'latency_ms' => 0];
        }
    }

    private function checkRedis(): array
    {
        try {
            $start = microtime(true);
            Cache::put('health_check_ping', 'pong', 10);
            $value = Cache::get('health_check_ping');
            $latency = round((microtime(true) - $start) * 1000, 1);

            if ($value !== 'pong') {
                return ['status' => 'critical', 'message' => 'Redis: Read/Write failure', 'latency_ms' => $latency];
            }

            $cacheKeys = 0;
            try {
                $redis = Cache::getStore()->getRedis()->connection();
                $info = $redis->info();
                $cacheKeys = $info['Keyspace']['db0']['keys'] ?? ($info['db0'] ?? 'N/A');
            } catch (\Exception $e) {}

            if ($latency > 100) {
                return ['status' => 'warning', 'message' => "Latence Redis élevée: {$latency}ms", 'latency_ms' => $latency, 'keys' => $cacheKeys];
            }
            return ['status' => 'healthy', 'message' => "OK ({$latency}ms)", 'latency_ms' => $latency, 'keys' => $cacheKeys];
        } catch (\Exception $e) {
            return ['status' => 'critical', 'message' => "Redis inaccessible: " . $e->getMessage(), 'latency_ms' => 0];
        }
    }

    private function checkWarmupCache(): array
    {
        $periods = ['14d', 'lifetime'];
        $sections = ['kpis', 'subscriptions', 'transactions', 'merchants', 'timwe', 'ooredoo'];
        $missing = [];
        $found = 0;

        foreach ($periods as $period) {
            foreach ($sections as $section) {
                $cacheKey = "split:{$section}:{$period}:ALL";
                if (Cache::has($cacheKey)) {
                    $found++;
                } else {
                    $missing[] = "{$section}:{$period}";
                }
            }
        }

        $total = count($periods) * count($sections);
        $coverage = $total > 0 ? round(($found / $total) * 100, 1) : 0;

        if ($coverage < 50) {
            return ['status' => 'critical', 'message' => "Cache warmup très incomplet: {$coverage}% ({$found}/{$total})", 'coverage' => $coverage, 'missing' => array_slice($missing, 0, 10)];
        }
        if ($coverage < 80) {
            return ['status' => 'warning', 'message' => "Cache warmup partiel: {$coverage}%", 'coverage' => $coverage, 'missing' => $missing];
        }
        return ['status' => 'healthy', 'message' => "Cache warmup OK: {$coverage}% ({$found}/{$total})", 'coverage' => $coverage];
    }

    private function checkDiskSpace(): array
    {
        try {
            $free = disk_free_space('/');
            $total = disk_total_space('/');
            $usedPct = round((1 - ($free / $total)) * 100, 1);
            $freeGb = round($free / 1073741824, 1);

            if ($usedPct > 95) {
                return ['status' => 'critical', 'message' => "Disque presque plein: {$usedPct}% utilisé ({$freeGb}GB libre)", 'used_pct' => $usedPct, 'free_gb' => $freeGb];
            }
            if ($usedPct > 85) {
                return ['status' => 'warning', 'message' => "Disque rempli à {$usedPct}% ({$freeGb}GB libre)", 'used_pct' => $usedPct, 'free_gb' => $freeGb];
            }
            return ['status' => 'healthy', 'message' => "Disque OK: {$usedPct}% utilisé ({$freeGb}GB libre)", 'used_pct' => $usedPct, 'free_gb' => $freeGb];
        } catch (\Exception $e) {
            return ['status' => 'warning', 'message' => "Impossible de vérifier le disque", 'used_pct' => 0];
        }
    }

    private function checkApiEndpoints(): array
    {
        $lastApiHistory = Cache::get('monitoring:api_history', []);
        if (empty($lastApiHistory)) {
            return ['status' => 'info', 'message' => "Aucune donnée API récente", 'avg_response_ms' => 0];
        }

        $recentErrors = 0;
        $totalLatency = 0;
        $count = 0;
        foreach (array_slice($lastApiHistory, 0, 20) as $entry) {
            if (($entry['status'] ?? 200) >= 500) $recentErrors++;
            $totalLatency += ($entry['response_time_ms'] ?? 0);
            $count++;
        }

        $avgLatency = $count > 0 ? round($totalLatency / $count) : 0;
        $errorRate = $count > 0 ? round(($recentErrors / $count) * 100, 1) : 0;

        if ($errorRate > 50) {
            return ['status' => 'critical', 'message' => "Taux d'erreur API élevé: {$errorRate}%", 'avg_response_ms' => $avgLatency, 'error_rate' => $errorRate];
        }
        if ($avgLatency > 5000) {
            return ['status' => 'warning', 'message' => "Latence API moyenne élevée: {$avgLatency}ms", 'avg_response_ms' => $avgLatency, 'error_rate' => $errorRate];
        }
        return ['status' => 'healthy', 'message' => "API OK (avg {$avgLatency}ms, erreurs {$errorRate}%)", 'avg_response_ms' => $avgLatency, 'error_rate' => $errorRate];
    }
}
