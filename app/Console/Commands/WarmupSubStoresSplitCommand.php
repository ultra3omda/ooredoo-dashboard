<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class WarmupSubStoresSplitCommand extends Command
{
    protected $signature = 'substores:warmup
        {--ttl=14400 : Cache TTL in seconds}
        {--sub-store= : Warm specific sub-store only}
        {--password=SuperAdmin@2025 : SuperAdmin password for auth}
        {--force : Force refresh even if cache exists}';

    protected $description = 'Pre-compute and cache Sub-Stores split endpoints (kpis, stores, charts, merchants, users) for instant loading';

    public function handle(): int
    {
        $totalStart = microtime(true);
        ini_set('memory_limit', '1G');
        set_time_limit(600);

        $ttl = (int) $this->option('ttl');
        $specificStore = $this->option('sub-store');
        $force = $this->option('force');

        $this->info("=== Sub-Stores Cache Warmup ===");
        $this->info("TTL: {$ttl}s | Force: " . ($force ? 'YES' : 'NO'));

        // Step 1: Get SuperAdmin credentials
        $superAdmin = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('roles.name', 'super_admin')
            ->select('users.email')
            ->first();

        if (!$superAdmin) {
            $this->error('No SuperAdmin found in DB');
            return 1;
        }

        // Step 2: Get all sub-stores
        $subStores = $this->getSubStoresToWarm($specificStore);
        $this->info("Sub-stores to warm: " . implode(', ', $subStores));

        // Step 3: Get campaigns for Pluxee sub-stores
        $campaignsByStore = $this->getCampaignsByStore($subStores);

        // Step 4: Define date ranges to warm (most common user selections)
        $dateRanges = $this->getDateRanges();
        $this->info("Date ranges: " . count($dateRanges));

        // Step 5: Authenticate and warm each combination
        $baseUrl = $this->getBaseUrl();
        $this->info("Base URL: {$baseUrl}");

        $session = $this->authenticate($baseUrl, $superAdmin->email, $this->option('password'));
        if (!$session) {
            $this->error('Authentication failed');
            return 1;
        }
        $this->info("Authenticated as {$superAdmin->email}");

        $sections = ['kpis', 'stores', 'charts', 'merchants', 'users'];
        $totalWarmed = 0;
        $totalErrors = 0;
        $totalSkipped = 0;

        foreach ($subStores as $store) {
            $isPluxee = stripos($store, 'pluxee') !== false;
            $campaigns = $isPluxee ? ($campaignsByStore[$store] ?? []) : [null];

            // For Pluxee: warm "all campaigns" (no campaign param) + each specific campaign
            if ($isPluxee) {
                array_unshift($campaigns, null); // null = all campaigns
            }

            foreach ($dateRanges as $rangeLabel => $range) {
                foreach ($campaigns as $campaign) {
                    $campaignLabel = $campaign ?: 'ALL';
                    $this->line("\n--- {$store} | {$rangeLabel} | Campaign: {$campaignLabel} ---");

                    $params = [
                        'sub_store' => $store,
                        'start_date' => $range['start'],
                        'end_date' => $range['end'],
                        'comparison_start_date' => $range['comp_start'],
                        'comparison_end_date' => $range['comp_end'],
                    ];
                    if ($campaign) {
                        $params['campaign'] = $campaign;
                    }

                    // Check if cache already exists (skip unless --force)
                    if (!$force) {
                        $cacheKey = 'ss_split:kpis:' . md5(json_encode($this->buildCacheParams($params)));
                        if (Cache::has($cacheKey)) {
                            $this->line("  SKIP: cache exists");
                            $totalSkipped += count($sections);
                            continue;
                        }
                    }

                    foreach ($sections as $section) {
                        $epStart = microtime(true);
                        try {
                            $client = new \GuzzleHttp\Client([
                                'cookies' => $session['jar'],
                                'timeout' => 180,
                                'verify' => false,
                            ]);

                            $response = $client->get("{$baseUrl}/sub-stores/api/split/{$section}", [
                                'query' => $params,
                                'headers' => ['Accept' => 'application/json'],
                            ]);

                            $body = (string) $response->getBody();
                            $json = json_decode($body, true);

                            if ($response->getStatusCode() === 200 && ($json['success'] ?? false)) {
                                $elapsed = round((microtime(true) - $epStart) * 1000);
                                $serverTime = $json['execution_time_ms'] ?? '?';
                                $this->info("  {$section}: OK in {$elapsed}ms (server: {$serverTime}ms)");

                                // Store raw JSON for fast-path cache
                                $rawKey = 'ss_raw:' . $section . ':' . md5(json_encode([
                                    'start_date' => $params['start_date'],
                                    'end_date' => $params['end_date'],
                                    'sub_store' => $params['sub_store'],
                                    'campaign' => $params['campaign'] ?? null,
                                    'split_cache_v' => 5,
                                ]));
                                Cache::put($rawKey, $body, $ttl);

                                $totalWarmed++;
                            } else {
                                $error = $json['error'] ?? $response->getStatusCode();
                                $this->warn("  {$section}: FAILED ({$error})");
                                $totalErrors++;
                            }
                        } catch (\Exception $e) {
                            $this->error("  {$section}: ERROR - " . substr($e->getMessage(), 0, 100));
                            $totalErrors++;
                        }
                    }
                }
            }
        }

        $totalElapsed = round(microtime(true) - $totalStart, 1);
        $this->info("\n=== Warmup Complete ===");
        $this->info("Warmed: {$totalWarmed} | Skipped: {$totalSkipped} | Errors: {$totalErrors} | Time: {$totalElapsed}s");

        Log::info("SubStores Warmup: {$totalWarmed} warmed, {$totalSkipped} skipped, {$totalErrors} errors in {$totalElapsed}s");

        Cache::put('monitoring:substores_last_warmup', [
            'completed_at' => Carbon::now()->toIso8601String(),
            'warmed' => $totalWarmed,
            'skipped' => $totalSkipped,
            'errors' => $totalErrors,
            'duration_seconds' => $totalElapsed,
        ], 86400);

        return $totalErrors > 0 ? 1 : 0;
    }

    private function getSubStoresToWarm(?string $specific): array
    {
        if ($specific) {
            return [$specific];
        }

        $stores = DB::table('stores')
            ->where(function ($q) {
                $q->where('is_sub_store', 1)
                  ->orWhere('store_name', 'LIKE', '%Pluxee%')
                  ->orWhereIn('store_id', [57, 61]);
            })
            ->distinct()
            ->pluck('store_name')
            ->toArray();

        // Always include ALL
        if (!in_array('ALL', $stores)) {
            array_unshift($stores, 'ALL');
        }

        return $stores;
    }

    private function getCampaignsByStore(array $stores): array
    {
        $result = [];
        foreach ($stores as $store) {
            if (stripos($store, 'pluxee') === false) continue;

            $campaigns = DB::table('carte_recharge')
                ->join('stores', function ($j) {
                    $j->whereRaw("FIND_IN_SET(stores.store_id, carte_recharge.stores)");
                })
                ->where('stores.store_name', 'LIKE', "%{$store}%")
                ->distinct()
                ->pluck('carte_recharge.campain_name')
                ->toArray();

            $result[$store] = $campaigns;
        }
        return $result;
    }

    private function getDateRanges(): array
    {
        $now = Carbon::today();
        return [
            '12M' => [
                'start' => $now->copy()->subYear()->addDay()->toDateString(),
                'end' => $now->toDateString(),
                'comp_start' => $now->copy()->subYears(2)->addDay()->toDateString(),
                'comp_end' => $now->copy()->subYear()->toDateString(),
            ],
        ];
    }

    private function buildCacheParams(array $params): array
    {
        return [
            'start_date' => $params['start_date'],
            'end_date' => $params['end_date'],
            'comparison_start_date' => $params['comparison_start_date'],
            'comparison_end_date' => $params['comparison_end_date'],
            'sub_store' => $params['sub_store'],
            'campaign' => $params['campaign'] ?? null,
            'period_days' => Carbon::parse($params['start_date'])->diffInDays(Carbon::parse($params['end_date'])) + 1,
            'allowed_campaigns' => [],
        ];
    }

    private function getBaseUrl(): string
    {
        $appUrl = config('app.url', 'http://127.0.0.1:8002');
        // In production, use internal URL for speed
        if (str_contains($appUrl, 'https://')) {
            return 'http://127.0.0.1:8002';
        }
        return $appUrl;
    }

    private function authenticate(string $baseUrl, string $email, string $password): ?array
    {
        try {
            // Use Guzzle directly for proper cookie management
            $jar = new \GuzzleHttp\Cookie\CookieJar();
            $client = new \GuzzleHttp\Client([
                'cookies' => $jar,
                'allow_redirects' => true,
                'timeout' => 30,
                'verify' => false,
            ]);

            // Get login page for CSRF token
            $loginPage = $client->get("{$baseUrl}/login");
            $body = (string) $loginPage->getBody();
            preg_match('/name="_token" value="([^"]+)"/', $body, $matches);
            if (empty($matches[1])) return null;

            // Submit login form
            $client->post("{$baseUrl}/login", [
                'form_params' => [
                    '_token' => $matches[1],
                    'email' => $email,
                    'password' => $password,
                ],
            ]);

            // Extract cookies for Http facade
            $cookieArray = [];
            foreach ($jar as $cookie) {
                $cookieArray[$cookie->getName()] = $cookie->getValue();
            }

            $domain = parse_url($baseUrl, PHP_URL_HOST) ?: '127.0.0.1';
            return [
                'jar' => $jar,
                'cookies' => $cookieArray,
                'domain' => $domain,
            ];
        } catch (\Exception $e) {
            $this->error("Auth error: " . $e->getMessage());
        }
        return null;
    }
}
