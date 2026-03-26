<?php
header('Content-Type: text/plain');
$start = microtime(true);
try {
    $redis = new Predis\Client([
        'scheme' => 'tcp',
        'host' => '51.38.187.245',
        'port' => 7905,
        'password' => 'hxtrJ74',
        'database' => 1,
    ]);
    $redis->set('fpm_test', 'works');
    echo "Redis direct: " . $redis->get('fpm_test') . " (" . round((microtime(true)-$start)*1000) . "ms)\n";
} catch (Exception $e) {
    echo "Redis error: " . $e->getMessage() . "\n";
}

// Test via Laravel Cache facade
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$start = microtime(true);
try {
    \Illuminate\Support\Facades\Cache::put('fpm_laravel_test', 'works_too', 60);
    echo "Laravel Cache: " . \Illuminate\Support\Facades\Cache::get('fpm_laravel_test') . " (" . round((microtime(true)-$start)*1000) . "ms)\n";
    echo "Driver: " . config('cache.default') . "\n";
} catch (Exception $e) {
    echo "Laravel Cache error: " . $e->getMessage() . "\n";
}
