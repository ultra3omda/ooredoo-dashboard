<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AIContextCache extends Model
{
    protected $table = 'ai_agent_context_cache';
    
    protected $fillable = [
        'cache_key',
        'context_type',
        'context_data',
        'expires_at',
        'data_size_kb'
    ];
    
    protected $casts = [
        'context_data' => 'array',
        'expires_at' => 'datetime'
    ];

    /**
     * Vérifie si le cache est expiré
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
    
    /**
     * Récupère ou crée un cache avec callback
     */
    public static function getOrCreate(string $key, string $type, callable $callback, int $ttlMinutes = 60): array
    {
        // Vérifier le cache existant
        $cache = self::where('cache_key', $key)
                    ->where('expires_at', '>', now())
                    ->first();
        
        if ($cache) {
            Log::debug("AIContextCache - Cache HIT pour $key");
            return $cache->context_data;
        }
        
        Log::debug("AIContextCache - Cache MISS pour $key, génération...");
        
        // Générer les nouvelles données
        $data = $callback();
        $dataSize = strlen(json_encode($data)) / 1024; // Taille en KB
        
        // Sauvegarder en cache
        self::updateOrCreate(
            ['cache_key' => $key],
            [
                'context_type' => $type,
                'context_data' => $data,
                'expires_at' => now()->addMinutes($ttlMinutes),
                'data_size_kb' => round($dataSize, 2)
            ]
        );
        
        Log::info("AIContextCache - Nouveau cache créé", [
            'key' => $key,
            'type' => $type,
            'ttl_minutes' => $ttlMinutes,
            'size_kb' => round($dataSize, 2)
        ]);
        
        return $data;
    }

    /**
     * Nettoie les caches expirés
     */
    public static function cleanExpired(): int
    {
        $deleted = self::where('expires_at', '<', now())->delete();
        
        if ($deleted > 0) {
            Log::info("AIContextCache - Nettoyage effectué", ['deleted_count' => $deleted]);
        }
        
        return $deleted;
    }

    /**
     * Statistiques du cache
     */
    public static function getCacheStats(): array
    {
        $stats = self::selectRaw('
            context_type,
            COUNT(*) as entries,
            AVG(data_size_kb) as avg_size_kb,
            SUM(data_size_kb) as total_size_kb,
            MIN(expires_at) as earliest_expiry,
            MAX(expires_at) as latest_expiry
        ')
        ->where('expires_at', '>', now())
        ->groupBy('context_type')
        ->get();
        
        return $stats->map(function($stat) {
            return [
                'type' => $stat->context_type,
                'entries' => $stat->entries,
                'avg_size_kb' => round($stat->avg_size_kb, 2),
                'total_size_kb' => round($stat->total_size_kb, 2),
                'earliest_expiry' => Carbon::parse($stat->earliest_expiry)->diffForHumans(),
                'latest_expiry' => Carbon::parse($stat->latest_expiry)->diffForHumans()
            ];
        })->toArray();
    }

    /**
     * Force le refresh d'un type de cache
     */
    public static function invalidateType(string $contextType): int
    {
        $deleted = self::where('context_type', $contextType)->delete();
        Log::info("AIContextCache - Type $contextType invalidé", ['deleted' => $deleted]);
        return $deleted;
    }

    /**
     * Préchauffe les caches importants
     */
    public static function warmupEssentialCaches(): array
    {
        $warmedUp = [];
        
        try {
            // Cache système
            $contextProvider = app(AIContextProvider::class);
            
            $systemContext = $contextProvider->getSystemContext();
            $warmedUp['system'] = count($systemContext);
            
            $kpisContext = $contextProvider->getKPIsContext();
            $warmedUp['kpis'] = count($kpisContext);
            
            Log::info("AIContextCache - Warmup terminé", $warmedUp);
            
        } catch (\Exception $e) {
            Log::error("AIContextCache - Erreur warmup: " . $e->getMessage());
        }
        
        return $warmedUp;
    }
}