<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CacheService
{
    private const CACHE_PREFIX = 'dashboard_v2';
    private const DEFAULT_TTL = 300; // 5 minutes
    
    /**
     * Configuration TTL adaptatif selon la période et la complexité
     */
    private function calculateTTL(int $periodDays, string $dataType = 'standard'): int
    {
        $baseTTL = match($dataType) {
            'kpis' => 300,      // 5 minutes pour les KPIs
            'merchants' => 600,  // 10 minutes pour les marchands
            'transactions' => 900, // 15 minutes pour les transactions
            'heavy' => 1800,     // 30 minutes pour les calculs lourds
            default => self::DEFAULT_TTL
        };
        
        // Multiplier selon la période
        $multiplier = match(true) {
            $periodDays <= 7 => 1,      // Période courte: TTL normal
            $periodDays <= 30 => 2,     // Période moyenne: TTL x2
            $periodDays <= 90 => 4,     // Période longue: TTL x4
            default => 8                // Très longue période: TTL x8
        };
        
        return $baseTTL * $multiplier;
    }
    
    /**
     * Génère une clé de cache optimisée
     */
    public function generateKey(array $params): string
    {
        $keyData = array_merge([self::CACHE_PREFIX], $params);
        return implode(':', $keyData) . ':' . md5(serialize($params));
    }
    
    /**
     * Cache intelligent avec TTL adaptatif
     */
    public function remember(string $key, int $periodDays, string $dataType, callable $callback)
    {
        $ttl = $this->calculateTTL($periodDays, $dataType);
        
        Log::info("Cache: clé={$key}, TTL={$ttl}s, type={$dataType}, période={$periodDays}j");
        
        return Cache::remember($key, $ttl, function() use ($callback, $key, $ttl) {
            $startTime = microtime(true);
            $result = $callback();
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Ajouter des métadonnées au cache
            if (is_array($result)) {
                $result['_cache_meta'] = [
                    'cached_at' => now()->toISOString(),
                    'ttl_seconds' => $ttl,
                    'execution_time_ms' => $executionTime,
                    'cache_key' => $key
                ];
            }
            
            Log::info("Cache MISS: {$key} calculé en {$executionTime}ms, TTL={$ttl}s");
            return $result;
        });
    }
    
    /**
     * Cache avec fallback en cas d'erreur
     */
    public function rememberWithFallback(string $key, int $periodDays, string $dataType, callable $callback, callable $fallback = null)
    {
        try {
            return $this->remember($key, $periodDays, $dataType, $callback);
        } catch (\Exception $e) {
            Log::error("Cache: Erreur lors du calcul pour {$key}: " . $e->getMessage());
            
            // Essayer de récupérer une version expirée du cache
            $staleData = Cache::get($key . ':stale');
            if ($staleData) {
                Log::info("Cache: Utilisation de données expirées pour {$key}");
                return $staleData;
            }
            
            // Utiliser le fallback si disponible
            if ($fallback) {
                Log::info("Cache: Utilisation du fallback pour {$key}");
                return $fallback();
            }
            
            throw $e;
        }
    }
    
    /**
     * Stockage de données avec version "stale" pour fallback
     */
    public function putWithStale(string $key, $data, int $ttl): void
    {
        // Cache principal
        Cache::put($key, $data, $ttl);
        
        // Version "stale" avec TTL plus long pour fallback
        Cache::put($key . ':stale', $data, $ttl * 3);
        
        Log::info("Cache: Données stockées avec fallback pour {$key}");
    }
    
    /**
     * Invalidation intelligente du cache
     */
    public function invalidatePattern(string $pattern): int
    {
        $count = 0;
        
        // Pour les drivers qui supportent les patterns (Redis)
        if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
            $keys = Cache::getStore()->getRedis()->keys($pattern);
            foreach ($keys as $key) {
                Cache::forget($key);
                $count++;
            }
        } else {
            // Fallback pour les autres drivers
            Log::warning("Cache: Invalidation par pattern non supportée pour ce driver");
        }
        
        Log::info("Cache: {$count} clés invalidées pour le pattern {$pattern}");
        return $count;
    }
    
    /**
     * Invalidation par opérateur
     */
    public function invalidateOperator(string $operator): int
    {
        return $this->invalidatePattern(self::CACHE_PREFIX . ':*:' . $operator . ':*');
    }
    
    /**
     * Invalidation par période
     */
    public function invalidatePeriod(string $startDate, string $endDate): int
    {
        return $this->invalidatePattern(self::CACHE_PREFIX . ':*:' . $startDate . ':' . $endDate . ':*');
    }
    
    /**
     * Nettoyage du cache expiré
     */
    public function cleanup(): int
    {
        $count = 0;
        
        // Supprimer les données stale anciennes (> 24h)
        $staleKeys = Cache::getStore() instanceof \Illuminate\Cache\RedisStore 
            ? Cache::getStore()->getRedis()->keys('*:stale')
            : [];
            
        foreach ($staleKeys as $key) {
            $data = Cache::get($key);
            if ($data && isset($data['_cache_meta'])) {
                $cachedAt = Carbon::parse($data['_cache_meta']['cached_at']);
                if ($cachedAt->diffInHours(now()) > 24) {
                    Cache::forget($key);
                    $count++;
                }
            }
        }
        
        Log::info("Cache: {$count} entrées stale nettoyées");
        return $count;
    }
    
    /**
     * Statistiques du cache
     */
    public function getStats(): array
    {
        $stats = [
            'driver' => config('cache.default'),
            'prefix' => self::CACHE_PREFIX,
            'total_keys' => 0,
            'stale_keys' => 0,
            'memory_usage' => 'N/A'
        ];
        
        if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
            $redis = Cache::getStore()->getRedis();
            $allKeys = $redis->keys(self::CACHE_PREFIX . ':*');
            $staleKeys = $redis->keys(self::CACHE_PREFIX . ':*:stale');
            
            $stats['total_keys'] = count($allKeys);
            $stats['stale_keys'] = count($staleKeys);
            $stats['memory_usage'] = $redis->info('memory')['used_memory_human'] ?? 'N/A';
        }
        
        return $stats;
    }
    
    /**
     * Préchargement du cache pour les périodes communes
     */
    public function warmup(array $operators = ['ALL', 'Timwe'], array $periods = []): void
    {
        if (empty($periods)) {
            $now = Carbon::now();
            $periods = [
                // Derniers 7 jours
                [$now->copy()->subDays(6)->toDateString(), $now->toDateString()],
                // Derniers 30 jours
                [$now->copy()->subDays(29)->toDateString(), $now->toDateString()],
                // Mois en cours
                [$now->copy()->startOfMonth()->toDateString(), $now->toDateString()]
            ];
        }
        
        Log::info("Cache: Début du préchauffage pour " . count($operators) . " opérateurs et " . count($periods) . " périodes");
        
        foreach ($operators as $operator) {
            foreach ($periods as [$startDate, $endDate]) {
                try {
                    // Simuler une requête pour déclencher le cache
                    $key = $this->generateKey(['warmup', $startDate, $endDate, $operator]);
                    $periodDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
                    
                    $this->remember($key, $periodDays, 'warmup', function() use ($startDate, $endDate, $operator) {
                        return [
                            'warmed_up' => true,
                            'period' => "{$startDate} to {$endDate}",
                            'operator' => $operator,
                            'timestamp' => now()->toISOString()
                        ];
                    });
                    
                } catch (\Exception $e) {
                    Log::warning("Cache: Erreur lors du préchauffage pour {$operator} {$startDate}-{$endDate}: " . $e->getMessage());
                }
            }
        }
        
        Log::info("Cache: Préchauffage terminé");
    }
}

