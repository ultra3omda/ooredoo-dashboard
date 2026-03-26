<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class IntelligentCacheService
{
    private const STATS_HITS_KEY = 'intelligent_cache_hits';

    private const STATS_MISSES_KEY = 'intelligent_cache_misses';

    /**
     * Cache adaptatif avec TTL dynamique selon la volatilité des données.
     *
     * @param  callable(): mixed  $callback  Calcul en cas de miss
     * @return mixed
     */
    public function remember(string $key, callable $callback, ?int $ttlSeconds = null): mixed
    {
        $ttl = $ttlSeconds ?? $this->calculateOptimalTTL($key);

        $cached = Cache::get($key);

        if ($cached !== null) {
            $this->recordCacheHit($key);

            return $cached;
        }

        $this->recordCacheMiss($key);
        $data = $callback();
        Cache::put($key, $data, $ttl);

        return $data;
    }

    /**
     * Calcule le TTL optimal (en secondes) selon le pattern de la clé.
     */
    public function calculateOptimalTTL(string $key): int
    {
        if (str_contains($key, 'kpis') || str_contains($key, 'realtime')) {
            return 300; // 5 minutes
        }
        if (str_contains($key, 'features') || str_contains($key, 'ml_')) {
            return 3600; // 1 heure
        }
        if (str_contains($key, 'prediction')) {
            return 1800; // 30 minutes
        }
        if (str_contains($key, 'recommendation')) {
            return 7200; // 2 heures
        }
        if (str_contains($key, 'system') || str_contains($key, 'context') || str_contains($key, 'warmup')) {
            return 14400; // 4 heures
        }

        return 3600; // défaut 1 heure
    }

    /**
     * Précharge les données fréquemment utilisées (contexte agent IA, KPIs, features ML).
     */
    public function warmupCache(): void
    {
        Log::info('IntelligentCache - Warmup démarré');

        $contextProvider = app(AIContextProvider::class);

        $this->remember('warmup_system_context', fn () => $contextProvider->getSystemContext(), 14400);
        $this->remember('warmup_kpis_context', fn () => $contextProvider->getKPIsContext(), 300);
        $this->remember('warmup_ml_features_context', fn () => $contextProvider->getMLFeaturesContext(), 3600);

        Log::info('IntelligentCache - Warmup terminé');
    }

    private function recordCacheHit(string $key): void
    {
        $this->incrementStat(self::STATS_HITS_KEY);
        $this->incrementKeyHit($key);
    }

    private function recordCacheMiss(string $key): void
    {
        $this->incrementStat(self::STATS_MISSES_KEY);
    }

    private function incrementStat(string $key): void
    {
        if ($this->isRedisAvailable()) {
            try {
                Redis::incr($key);
            } catch (\Throwable) {
                $this->incrementStatViaCache($key);
            }
        } else {
            $this->incrementStatViaCache($key);
        }
    }

    private function incrementStatViaCache(string $key): void
    {
        Cache::put($key, (int) Cache::get($key, 0) + 1, now()->addYear());
    }

    private function incrementKeyHit(string $key): void
    {
        if ($this->isRedisAvailable()) {
            try {
                $hashKey = 'cache_key_hits:'.$key;
                Redis::hincrby($hashKey, now()->format('Y-m-d'), 1);
                Redis::expire($hashKey, 86400 * 31); // 31 jours
            } catch (\Throwable) {
                // ignore
            }
        }
    }

    private function isRedisAvailable(): bool
    {
        return config('cache.default') === 'redis';
    }

    /**
     * Retourne les statistiques du cache (hits, misses, hit rate, mémoire si Redis).
     *
     * @return array{hits: int, misses: int, hit_rate: float, memory_used: string}
     */
    public function getStats(): array
    {
        if ($this->isRedisAvailable()) {
            try {
                $hits = (int) Redis::get(self::STATS_HITS_KEY);
                $misses = (int) Redis::get(self::STATS_MISSES_KEY);
                $memoryUsed = 'N/A';
                $conn = Redis::connection();
                if (method_exists($conn, 'info')) {
                    $info = $conn->info('memory');
                    $memoryUsed = is_array($info) ? ($info['used_memory_human'] ?? 'N/A') : 'N/A';
                }
            } catch (\Throwable) {
                $hits = (int) Cache::get(self::STATS_HITS_KEY, 0);
                $misses = (int) Cache::get(self::STATS_MISSES_KEY, 0);
                $memoryUsed = 'N/A';
            }
        } else {
            $hits = (int) Cache::get(self::STATS_HITS_KEY, 0);
            $misses = (int) Cache::get(self::STATS_MISSES_KEY, 0);
            $memoryUsed = 'N/A';
        }

        $total = $hits + $misses;
        $hitRate = $total > 0 ? round($hits / $total * 100, 2) : 0.0;

        return [
            'hits' => $hits,
            'misses' => $misses,
            'hit_rate' => $hitRate,
            'memory_used' => $memoryUsed,
        ];
    }

    /**
     * Réinitialise les compteurs de stats (hits/misses).
     */
    public function resetStats(): void
    {
        if ($this->isRedisAvailable()) {
            try {
                Redis::del(self::STATS_HITS_KEY, self::STATS_MISSES_KEY);
            } catch (\Throwable) {
                // ignore
            }
        }
        Cache::forget(self::STATS_HITS_KEY);
        Cache::forget(self::STATS_MISSES_KEY);
    }
}
