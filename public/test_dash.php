<?php
set_time_limit(300);
ini_set('memory_limit', '512M');
header('Content-Type: text/plain');

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$logFile = '/tmp/dash_progress.txt';
file_put_contents($logFile, date('H:i:s') . " Starting getDashboardData\n");

try {
    $service = app(\App\Services\DashboardService::class);
    $start = microtime(true);
    $data = $service->getDashboardData('2026-03-25', '2026-03-26', '2026-03-23', '2026-03-24', 'Timwe');
    $time = round((microtime(true)-$start)*1000);
    
    $kpis = $data['kpis'] ?? [];
    file_put_contents($logFile, date('H:i:s') . " Done: {$time}ms\n", FILE_APPEND);
    file_put_contents($logFile, date('H:i:s') . " activated: " . ($kpis['activatedSubscriptions']['current'] ?? 'N/A') . "\n", FILE_APPEND);
    
    echo "OK: {$time}ms\n";
    echo "activated: " . ($kpis['activatedSubscriptions']['current'] ?? 'N/A') . "\n";
} catch (Exception $e) {
    file_put_contents($logFile, date('H:i:s') . " Error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo "Error: " . $e->getMessage() . "\n";
}
