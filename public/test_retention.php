<?php
header('Content-Type: text/plain');
set_time_limit(300);
echo "Start\n";

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$start = microtime(true);
try {
    $startBound = \Carbon\Carbon::parse('2026-03-25');
    $endExclusive = \Carbon\Carbon::parse('2026-03-27');
    
    echo "Testing calculateRetentionTrendOptimized...\n";
    $service = app(\App\Services\DashboardService::class);
    
    // Use reflection to call private method
    $ref = new ReflectionMethod($service, 'calculateRetentionTrendOptimized');
    $ref->setAccessible(true);
    $result = $ref->invoke($service, $startBound, $endExclusive, 'Timwe');
    
    echo "Result: " . count($result) . " items (" . round((microtime(true)-$start)*1000) . "ms)\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . " (" . round((microtime(true)-$start)*1000) . "ms)\n";
}
echo "Done\n";
