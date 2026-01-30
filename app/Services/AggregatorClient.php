<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;

class AggregatorClient
{
    private string $baseUrl;
    private string $token;
    private int $timeoutSeconds;

    public function __construct()
    {
        $config = config('services.aggregator');
        $this->baseUrl = rtrim((string) Arr::get($config, 'base_url', ''), '/');
        $this->token = (string) Arr::get($config, 'token', '');
        $this->timeoutSeconds = (int) Arr::get($config, 'timeout', 30);
    }

    public function findSubscriptionId(string $msisdn, string $offreId): ?string
    {
        $url = $this->baseUrl . '/subscription/find';
        $response = Http::withToken($this->token)
            ->timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asJson()
            ->post($url, [
                'msisdn' => $msisdn,
                'offreid' => $offreId,
            ]);

        if (!$response->ok()) {
            return null;
        }

        $json = $response->json();
        return $json['subscriptionId'] ?? $json['id'] ?? null;
    }

    public function getSubscription(string $subscriptionId): ?array
    {
        $url = $this->baseUrl . '/subscription/' . urlencode($subscriptionId);
        $response = Http::withToken($this->token)
            ->timeout($this->timeoutSeconds)
            ->acceptJson()
            ->get($url);

        if (!$response->ok()) {
            return null;
        }

        return $response->json();
    }
}




