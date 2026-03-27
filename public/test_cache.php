<?php
header('Content-Type: text/plain');
echo "Start\n";

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Driver: " . config('cache.default') . "\n";
echo "Redis host: " . config('database.redis.cache.host') . "\n";
echo "Redis port: " . config('database.redis.cache.port') . "\n";
echo "Redis client: " . config('database.redis.client') . "\n";

$start = microtime(true);
try {
    \Illuminate\Support\Facades\Cache::put('test_cache_fpm', 'hello', 60);
    $val = \Illuminate\Support\Facades\Cache::get('test_cache_fpm');
    echo "Cache put/get: " . $val . " (" . round((microtime(true)-$start)*1000) . "ms)\n";
} catch (Exception $e) {
    echo "Cache error: " . $e->getMessage() . "\n";
}

$start = microtime(true);
try {
    $result = \Illuminate\Support\Facades\Cache::remember('test_remember', 60, function() {
        return 'computed_value';
    });
    echo "Cache::remember: " . $result . " (" . round((microtime(true)-$start)*1000) . "ms)\n";
} catch (Exception $e) {
    echo "Remember error: " . $e->getMessage() . "\n";
}
echo "Done\n";
