<?php
header('Content-Type: text/plain');
set_time_limit(300);
ini_set('memory_limit', '512M');
ob_implicit_flush(true);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$startBound = \Carbon\Carbon::parse('2026-03-25');
$endExclusive = \Carbon\Carbon::parse('2026-03-27');

$service = app(\App\Services\DashboardService::class);

$methods = [
    'calculateRetentionTrendOptimized' => [$startBound, $endExclusive, 'Timwe'],
    'calculateQuarterlyActiveLocations' => ['2026-03-26'],
    'calculateActivationsByPaymentMethod' => [$startBound, $endExclusive, 'Timwe'],
    'calculatePlanDistribution' => [$startBound, $endExclusive, 'Timwe'],
];

foreach ($methods as $name => $args) {
    $s = microtime(true);
    file_put_contents('/tmp/method_debug.txt', date('H:i:s') . " Starting: {$name}\n", FILE_APPEND);
    try {
        $m = new ReflectionMethod($service, $name);
        $m->setAccessible(true);
        $result = $m->invoke($service, ...$args);
        $time = round((microtime(true)-$s)*1000);
        file_put_contents('/tmp/method_debug.txt', date('H:i:s') . " Done: {$name} = {$time}ms\n", FILE_APPEND);
    } catch (Exception $e) {
        $time = round((microtime(true)-$s)*1000);
        file_put_contents('/tmp/method_debug.txt', date('H:i:s') . " Error: {$name} = {$time}ms - {$e->getMessage()}\n", FILE_APPEND);
    }
}
file_put_contents('/tmp/method_debug.txt', date('H:i:s') . " COMPLETE\n", FILE_APPEND);
echo "check /tmp/method_debug.txt\n";
