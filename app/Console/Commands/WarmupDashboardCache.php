<?php

namespace App\Console\Commands;

use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WarmupDashboardCache extends Command
{
    protected $signature = 'dashboard:warmup {--operator=ALL : Operator to warm up}';
    protected $description = 'Pre-warm the dashboard cache for common periods';

    public function handle(DashboardService $dashboardService): int
    {
        $operators = $this->option('operator') === 'ALL' 
            ? ['ALL', 'Timwe', "S'abonner via Timwe"]
            : [$this->option('operator')];
        
        $now = Carbon::now();
        $periods = [
            // Last 14 days (default)
            [
                $now->copy()->subDays(13)->toDateString(),
                $now->toDateString(),
                $now->copy()->subDays(27)->toDateString(),
                $now->copy()->subDays(14)->toDateString(),
            ],
            // Last 7 days
            [
                $now->copy()->subDays(6)->toDateString(),
                $now->toDateString(),
                $now->copy()->subDays(13)->toDateString(),
                $now->copy()->subDays(7)->toDateString(),
            ],
            // Current month
            [
                $now->copy()->startOfMonth()->toDateString(),
                $now->toDateString(),
                $now->copy()->subMonth()->startOfMonth()->toDateString(),
                $now->copy()->subMonth()->endOfMonth()->toDateString(),
            ],
        ];

        $total = 0;
        $success = 0;

        foreach ($operators as $operator) {
            foreach ($periods as [$start, $end, $compStart, $compEnd]) {
                $startBound = Carbon::parse($start)->startOfDay();
                $endExclusive = Carbon::parse($end)->addDay()->startOfDay();
                $compStartBound = Carbon::parse($compStart)->startOfDay();
                $compEndExclusive = Carbon::parse($compEnd)->addDay()->startOfDay();
                $periodDays = Carbon::parse($start)->diffInDays(Carbon::parse($end));

                // Reproduire exactement la structure $params du contrôleur
                $params = [
                    'start_date' => $start,
                    'end_date' => $end,
                    'comparison_start_date' => $compStart,
                    'comparison_end_date' => $compEnd,
                    'operator' => $operator,
                    'period_days' => $periodDays
                ];
                $paramHash = md5(json_encode($params));

                // Warmup KPIs split
                $total++;
                $this->info("  [{$operator}] KPIs {$start} -> {$end}");
                try {
                    set_time_limit(180);
                    ini_set('memory_limit', '512M');
                    $cacheKey = 'split:kpis:' . $paramHash;
                    Cache::remember($cacheKey, 1800, function() use ($dashboardService, $startBound, $endExclusive, $compStartBound, $compEndExclusive, $operator) {
                        return $dashboardService->getKPIsOptimizedPublic($startBound, $endExclusive, $compStartBound, $compEndExclusive, $operator);
                    });
                    $success++;
                    $this->info("    OK");
                } catch (\Exception $e) {
                    $this->error("    FAILED: " . $e->getMessage());
                    Log::error("Warmup KPIs failed for {$operator} {$start}-{$end}: " . $e->getMessage());
                }

                // Warmup Merchants split
                $total++;
                $this->info("  [{$operator}] Merchants {$start} -> {$end}");
                try {
                    set_time_limit(120);
                    $cacheKey = 'split:merchants:' . $paramHash;
                    Cache::remember($cacheKey, 1800, function() use ($dashboardService, $startBound, $endExclusive, $compStartBound, $compEndExclusive, $operator) {
                        return $dashboardService->getMerchantsOptimizedPublic($startBound, $endExclusive, $compStartBound, $compEndExclusive, $operator);
                    });
                    $success++;
                    $this->info("    OK");
                } catch (\Exception $e) {
                    $this->error("    FAILED: " . $e->getMessage());
                    Log::error("Warmup merchants failed: " . $e->getMessage());
                }

                // Warmup Transactions split
                $total++;
                $this->info("  [{$operator}] Transactions {$start} -> {$end}");
                try {
                    set_time_limit(120);
                    $cacheKey = 'split:transactions:' . $paramHash;
                    Cache::remember($cacheKey, 1800, function() use ($dashboardService, $startBound, $endExclusive, $operator) {
                        return $dashboardService->getTransactionsDataPublic($startBound, $endExclusive, $operator);
                    });
                    $success++;
                    $this->info("    OK");
                } catch (\Exception $e) {
                    $this->error("    FAILED: " . $e->getMessage());
                    Log::error("Warmup transactions failed: " . $e->getMessage());
                }

                // Warmup Subscriptions split
                $total++;
                $this->info("  [{$operator}] Subscriptions {$start} -> {$end}");
                try {
                    set_time_limit(300);
                    $cacheKey = 'split:subscriptions:' . $paramHash;
                    Cache::remember($cacheKey, 1800, function() use ($dashboardService, $startBound, $endExclusive, $operator, $compStartBound, $compEndExclusive) {
                        return $dashboardService->getSubscriptionsDataPublic($startBound, $endExclusive, $operator, $compStartBound, $compEndExclusive);
                    });
                    $success++;
                    $this->info("    OK");
                } catch (\Exception $e) {
                    $this->error("    FAILED: " . $e->getMessage());
                    Log::error("Warmup subscriptions failed: " . $e->getMessage());
                }

                // Warmup Ooredoo stats split
                $total++;
                $this->info("  [{$operator}] Ooredoo {$start} -> {$end}");
                try {
                    set_time_limit(120);
                    $cacheKey = 'split:ooredoo:' . $paramHash;
                    Cache::remember($cacheKey, 1800, function() use ($dashboardService, $startBound, $endExclusive, $compStartBound, $compEndExclusive) {
                        $daily = $dashboardService->getOoredooDailyStatisticsPublic($startBound, $endExclusive);
                        $dailyComp = $dashboardService->getOoredooDailyStatisticsPublic($compStartBound, $compEndExclusive);
                        return [
                            'daily_statistics' => $daily,
                            'daily_statistics_comparison' => $dailyComp,
                            'ooredoo_monthly_stats' => $dashboardService->groupOoredooStatsByMonthPublic($daily),
                            'ooredoo_monthly_stats_comparison' => $dashboardService->groupOoredooStatsByMonthPublic($dailyComp)
                        ];
                    });
                    $success++;
                    $this->info("    OK");
                } catch (\Exception $e) {
                    $this->error("    FAILED: " . $e->getMessage());
                    Log::error("Warmup ooredoo failed: " . $e->getMessage());
                }
            }
        }

        $this->info("Cache warmup complete: {$success}/{$total} successful");
        return $success === $total ? 0 : 1;
    }
}
