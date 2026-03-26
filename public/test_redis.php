<?php
header('Content-Type: text/plain');
echo "Start\n";

try {
    require __DIR__.'/../vendor/autoload.php';
    $redis = new Predis\Client([
        'scheme' => 'tcp',
        'host' => '51.38.187.245',
        'port' => 7905,
        'password' => 'hxtrJ74',
        'database' => 1,
        'read_write_timeout' => 5,
    ]);
    $redis->set('fpm_test2', 'works');
    $val = $redis->get('fpm_test2');
    echo "Redis: " . $val . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "Done\n";
