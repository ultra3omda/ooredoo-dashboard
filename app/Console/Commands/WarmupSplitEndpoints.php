<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\DashboardService;

class WarmupSplitEndpoints extends Command
{
    /**
     * Doit rester identique à DataControllerOptimized::SPLIT_CACHE_VERSION,
     * sinon le warmup écrit dans des clés que le contrôleur ne lit jamais.
     */
    public const SPLIT_CACHE_VERSION = \App\Http\Controllers\Api\DataControllerOptimized::SPLIT_CACHE_VERSION;

    protected $signature = 'dashboard:warmup-split
        {--periods=14d,1M,6M,12M,lifetime : Comma-separated period labels}
        {--operator=ALL : Operator to warm up}
        {--ttl=3600 : Cache TTL in seconds}';

    protected $description = 'Pre-compute and cache all split endpoint responses for instant (<1s) dashboard loading';

    private DashboardService $service;

    public function __construct(DashboardService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle(): int
    {
        $totalStart = microtime(true);
        ini_set('memory_limit', '1G');

        $periods = explode(',', $this->option('periods'));
        $operator = $this->option('operator');
        $ttl = (int)$this->option('ttl');

        $this->info("=== Warmup Split Endpoints ===");
        $this->info("Periods: " . implode(', ', $periods));
        $this->info("Operator: {$operator}, TTL: {$ttl}s");

        $endpoints = ['kpis', 'merchants', 'transactions', 'subscriptions', 'timwe', 'ooredoo'];
        $totalCached = 0;
        $totalErrors = 0;

        foreach ($periods as $periodLabel) {
            $dates = $this->periodToDates(trim($periodLabel));
            if (!$dates) {
                $this->warn("  Unknown period: {$periodLabel}, skipping");
                continue;
            }

            $this->info("\n--- Period: {$periodLabel} ({$dates['start']} -> {$dates['end']}) ---");

            $params = [
                'start_date' => $dates['start'],
                'end_date' => $dates['end'],
                'comparison_start_date' => $dates['comp_start'],
                'comparison_end_date' => $dates['comp_end'],
                'operator' => $operator,
                'period_days' => Carbon::parse($dates['start'])->diffInDays(Carbon::parse($dates['end'])),
            ];

            $startBound = Carbon::parse($params['start_date'])->startOfDay();
            $endExclusive = Carbon::parse($params['end_date'])->addDay()->startOfDay();
            $compStartBound = Carbon::parse($params['comparison_start_date'])->startOfDay();
            $compEndExclusive = Carbon::parse($params['comparison_end_date'])->addDay()->startOfDay();

            foreach ($endpoints as $ep) {
                $epStart = microtime(true);
                $cacheKey = "split:{$ep}:" . md5(json_encode($params));

                try {
                    $data = match ($ep) {
                        'kpis' => $this->computeKpis($startBound, $endExclusive, $compStartBound, $compEndExclusive, $operator),
                        'merchants' => $this->computeMerchants($startBound, $endExclusive, $compStartBound, $compEndExclusive, $operator),
                        'transactions' => $this->computeTransactions($startBound, $endExclusive, $operator),
                        'subscriptions' => $this->computeSubscriptions($startBound, $endExclusive, $operator, $compStartBound, $compEndExclusive),
                        'timwe' => $this->computeTimwe($startBound, $endExclusive, $compStartBound, $compEndExclusive, $operator),
                        'ooredoo' => $this->computeOoredoo($startBound, $endExclusive, $compStartBound, $compEndExclusive),
                    };

                    // Store in standard cache (for Cache::remember fallback)
                    Cache::put($cacheKey, $data, $ttl);
                    
                    // Store pre-serialized raw JSON response (for ultra-fast path)
                    $fullResponse = json_encode([
                        'success' => true,
                        'section' => $ep,
                        'data' => $data,
                        'execution_time_ms' => round((microtime(true) - $epStart) * 1000),
                        '_cached' => true,
                        '_warmed_at' => now()->toIso8601String(),
                    ]);
                    
                    // Store with full params key (legacy)
                    $rawKeyFull = 'split_raw:v' . self::SPLIT_CACHE_VERSION . ':' . $ep . ':' . md5(json_encode($params));
                    Cache::put($rawKeyFull, $fullResponse, $ttl);
                    
                    // Store with simplified key (start_date + end_date + operator only)
                    $simpleKey = [
                        'start_date' => $params['start_date'],
                        'end_date' => $params['end_date'],
                        'operator' => $params['operator'],
                    ];
                    $rawKeySimple = 'split_raw:v' . self::SPLIT_CACHE_VERSION . ':' . $ep . ':' . md5(json_encode($simpleKey));
                    Cache::put($rawKeySimple, $fullResponse, $ttl);
                    
                    $elapsed = round((microtime(true) - $epStart) * 1000);
                    $this->info("  {$ep}: cached in {$elapsed}ms");
                    $totalCached++;
                } catch (\Exception $e) {
                    $this->error("  {$ep}: FAILED - " . substr($e->getMessage(), 0, 120));
                    Log::error("WarmupSplit {$ep} failed for {$periodLabel}: " . $e->getMessage());
                    $totalErrors++;
                }
            }
        }

        $totalElapsed = round(microtime(true) - $totalStart, 1);
        $this->info("\n=== Done: {$totalCached} cached, {$totalErrors} errors in {$totalElapsed}s ===");
        Log::info("WarmupSplitEndpoints: {$totalCached} cached, {$totalErrors} errors in {$totalElapsed}s");

        Cache::put('monitoring:last_warmup', [
            'completed_at' => Carbon::now()->toIso8601String(),
            'cached' => $totalCached,
            'errors' => $totalErrors,
            'duration_seconds' => $totalElapsed,
        ], 86400);

        return $totalErrors > 0 ? 1 : 0;
    }

    private function periodToDates(string $label): ?array
    {
        $now = Carbon::today();
        $end = $now->toDateString();

        return match (strtolower($label)) {
            '14d' => [
                'start' => $now->copy()->subDays(13)->toDateString(),
                'end' => $end,
                'comp_start' => $now->copy()->subDays(27)->toDateString(),
                'comp_end' => $now->copy()->subDays(14)->toDateString(),
            ],
            '1m' => [
                'start' => $now->copy()->subMonth()->toDateString(),
                'end' => $end,
                'comp_start' => $now->copy()->subMonths(2)->toDateString(),
                'comp_end' => $now->copy()->subMonth()->toDateString(),
            ],
            '3m' => [
                'start' => $now->copy()->subMonths(3)->toDateString(),
                'end' => $end,
                'comp_start' => $now->copy()->subMonths(6)->toDateString(),
                'comp_end' => $now->copy()->subMonths(3)->toDateString(),
            ],
            '6m' => [
                'start' => $now->copy()->subMonths(6)->toDateString(),
                'end' => $end,
                'comp_start' => $now->copy()->subMonths(12)->toDateString(),
                'comp_end' => $now->copy()->subMonths(6)->toDateString(),
            ],
            '12m' => [
                'start' => $now->copy()->subYear()->toDateString(),
                'end' => $end,
                'comp_start' => $now->copy()->subYears(2)->toDateString(),
                'comp_end' => $now->copy()->subYear()->toDateString(),
            ],
            'lifetime' => [
                'start' => '2021-01-01',
                'end' => $end,
                // Pour Lifetime (>365j): comparer dernière année vs année précédente
                'comp_start' => $now->copy()->subYears(2)->toDateString(),
                'comp_end' => $now->copy()->subYear()->toDateString(),
            ],
            default => null,
        };
    }

    private function computeKpis($startBound, $endExclusive, $compStartBound, $compEndExclusive, $operator)
    {
        return $this->service->getKPIsOptimizedPublic($startBound, $endExclusive, $compStartBound, $compEndExclusive, $operator);
    }

    private function computeMerchants($startBound, $endExclusive, $compStartBound, $compEndExclusive, $operator)
    {
        return $this->service->getMerchantsOptimizedPublic($startBound, $endExclusive, $compStartBound, $compEndExclusive, $operator);
    }

    private function computeTransactions($startBound, $endExclusive, $operator)
    {
        return $this->service->getTransactionsDataPublic($startBound, $endExclusive, $operator);
    }

    private function computeSubscriptions($startBound, $endExclusive, $operator, $compStartBound, $compEndExclusive)
    {
        return $this->service->getSubscriptionsDataPublic($startBound, $endExclusive, $operator, $compStartBound, $compEndExclusive);
    }

    private function computeTimwe($startBound, $endExclusive, $compStartBound, $compEndExclusive, $operator)
    {
        $daily = $this->service->getDailyStatistics($startBound, $endExclusive, $operator);
        $dailyComp = $this->service->getDailyStatistics($compStartBound, $compEndExclusive, $operator);
        return [
            'daily_statistics' => $daily,
            'daily_statistics_comparison' => $dailyComp,
            'timwe_monthly_stats' => $this->service->groupTimweStatsByMonth($daily),
            'timwe_monthly_stats_comparison' => $this->service->groupTimweStatsByMonth($dailyComp),
        ];
    }

    private function computeOoredoo($startBound, $endExclusive, $compStartBound, $compEndExclusive)
    {
        $daily = $this->service->getOoredooDailyStatisticsPublic($startBound, $endExclusive);
        $dailyComp = $this->service->getOoredooDailyStatisticsPublic($compStartBound, $compEndExclusive);
        return [
            'daily_statistics' => $daily,
            'daily_statistics_comparison' => $dailyComp,
            'ooredoo_monthly_stats' => $this->service->groupOoredooStatsByMonthPublic($daily),
            'ooredoo_monthly_stats_comparison' => $this->service->groupOoredooStatsByMonthPublic($dailyComp),
        ];
    }
}
