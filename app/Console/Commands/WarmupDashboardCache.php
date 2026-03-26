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

        $total = count($operators) * count($periods);
        $this->info("Warming up cache for {$total} combinations...");

        $success = 0;
        foreach ($operators as $operator) {
            foreach ($periods as [$start, $end, $compStart, $compEnd]) {
                $this->info("  [{$operator}] {$start} -> {$end}");
                try {
                    set_time_limit(180);
                    ini_set('memory_limit', '512M');
                    $dashboardService->getDashboardData($start, $end, $compStart, $compEnd, $operator);
                    $success++;
                    $this->info("    OK (cached)");
                } catch (\Exception $e) {
                    $this->error("    FAILED: " . $e->getMessage());
                    Log::error("Warmup failed for {$operator} {$start}-{$end}: " . $e->getMessage());
                }
            }
        }

        $this->info("Cache warmup complete: {$success}/{$total} successful");
        return $success === $total ? 0 : 1;
    }
}
