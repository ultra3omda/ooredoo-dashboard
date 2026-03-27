<?php
header('Content-Type: text/plain');
set_time_limit(300);
ini_set('memory_limit', '512M');

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$start_total = microtime(true);
$startBound = \Carbon\Carbon::parse('2026-03-25');
$endExclusive = \Carbon\Carbon::parse('2026-03-27');

$service = app(\App\Services\DashboardService::class);
$ref = fn($name) => (function() use ($service, $name) {
    $m = new ReflectionMethod($service, $name);
    $m->setAccessible(true);
    return $m;
})();

$methods = [
    ['calculateRetentionTrendOptimized', [$startBound, $endExclusive, 'Timwe']],
    ['calculateQuarterlyActiveLocations', ['2026-03-26']],
    ['calculateActivationsByPaymentMethod', [$startBound, $endExclusive, 'Timwe']],
    ['calculatePlanDistribution', [$startBound, $endExclusive, 'Timwe']],
    ['calculateCohorts', ['2026-03-25', '2026-03-26', 'Timwe']],
    ['calculateRenewalRate', ['2026-03-25', '2026-03-26', 'Timwe']],
    ['calculateAverageLifespan', ['2026-03-25', '2026-03-26', 'Timwe']],
    ['getSubscriptionDetails', [$startBound, $endExclusive, 'Timwe']],
];

foreach ($methods as [$name, $args]) {
    $s = microtime(true);
    try {
        $m = new ReflectionMethod($service, $name);
        $m->setAccessible(true);
        $result = $m->invoke($service, ...$args);
        $time = round((microtime(true)-$s)*1000);
        $count = is_array($result) ? count($result) : (is_object($result) ? 1 : $result);
        echo "{$name}: {$time}ms (result: {$count})\n";
    } catch (Exception $e) {
        $time = round((microtime(true)-$s)*1000);
        echo "{$name}: ERROR {$time}ms - {$e->getMessage()}\n";
    }
}

echo "\nTotal: " . round((microtime(true)-$start_total)*1000) . "ms\n";
