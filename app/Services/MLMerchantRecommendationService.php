<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MLMerchantRecommendationService
{
    private string $apiBaseUrl;
    private int $cacheTtl;

    public function __construct()
    {
        $this->apiBaseUrl = rtrim(config('app.url', 'http://127.0.0.1:8001'), '/');
        $this->cacheTtl = 3600; // 1 hour cache
    }

    /**
     * Get personalized merchant recommendations for a client.
     */
    public function getRecommendations(int $clientId, int $topK = 10, ?int $categoryId = null, bool $excludeVisited = false): array
    {
        $cacheKey = "merchant_reco:{$clientId}:{$topK}:{$categoryId}:{$excludeVisited}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($clientId, $topK, $categoryId, $excludeVisited) {
            try {
                $payload = [
                    'client_id' => $clientId,
                    'top_k' => $topK,
                    'exclude_visited' => $excludeVisited,
                ];

                if ($categoryId) {
                    $payload['category_id'] = $categoryId;
                }

                $response = Http::timeout(30)
                    ->post('http://127.0.0.1:8001/api/merchant-recommendations', $payload);

                if ($response->successful()) {
                    $data = $response->json();
                    if ($data['success'] ?? false) {
                        return [
                            'success' => true,
                            'recommendations' => $data['recommendations'] ?? [],
                            'count' => $data['count'] ?? 0,
                            'source' => $data['source'] ?? 'ml_model',
                        ];
                    }
                }

                Log::warning("ML Recommendation API returned error for client {$clientId}", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->getFallbackRecommendations($topK);
            } catch (\Exception $e) {
                Log::error("ML Recommendation API failed for client {$clientId}: " . $e->getMessage());
                return $this->getFallbackRecommendations($topK);
            }
        });
    }

    /**
     * Check ML recommendation engine health.
     */
    public function getHealth(): array
    {
        try {
            $response = Http::timeout(10)
                ->get('http://127.0.0.1:8001/api/merchant-recommendations/health');

            if ($response->successful()) {
                return $response->json();
            }
            return ['status' => 'error', 'message' => 'API returned ' . $response->status()];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Trigger model retraining.
     */
    public function triggerRetrain(): array
    {
        try {
            $response = Http::timeout(300)
                ->post('http://127.0.0.1:8001/api/merchant-recommendations/retrain', []);

            return $response->json();
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Clear recommendation cache for a client.
     */
    public function clearCache(int $clientId): void
    {
        $patterns = Cache::get("merchant_reco_keys:{$clientId}", []);
        foreach ($patterns as $key) {
            Cache::forget($key);
        }
        Cache::forget("merchant_reco_keys:{$clientId}");
    }

    /**
     * Fallback: popularity-based recommendations when ML is unavailable.
     */
    private function getFallbackRecommendations(int $topK): array
    {
        $fallbackPath = base_path('ml_models/merchant_fallback_popular.json');
        if (file_exists($fallbackPath)) {
            $data = json_decode(file_get_contents($fallbackPath), true);
            return [
                'success' => true,
                'recommendations' => array_slice($data, 0, $topK),
                'count' => min(count($data), $topK),
                'source' => 'fallback_popularity',
            ];
        }

        return [
            'success' => false,
            'recommendations' => [],
            'count' => 0,
            'source' => 'none',
        ];
    }
}
