<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Samples carte_recharge.stores (non null) ===\n";
$samples = DB::table('carte_recharge')
    ->whereNotNull('stores')
    ->where('stores', '<>', '')
    ->select('stores')
    ->limit(200)
    ->get();

$freq = [];
foreach ($samples as $row) {
    $v = (string) $row->stores;
    $key = strlen($v) > 80 ? substr($v, 0, 80) . '...' : $v;
    $freq[$key] = ($freq[$key] ?? 0) + 1;
}
arsort($freq);
$i = 0;
foreach ($freq as $val => $c) {
    echo "[" . (++$i) . "] x{$c} | " . json_encode($val, JSON_UNESCAPED_UNICODE) . "\n";
    if ($i >= 25) {
        break;
    }
}

echo "\n=== Rows where stores contains digit 61 (LIKE %61%) first 20 distinct ===\n";
$like61 = DB::table('carte_recharge')
    ->where('stores', 'like', '%61%')
    ->select('stores')
    ->limit(500)
    ->get();
$seen = [];
foreach ($like61 as $row) {
    $v = (string) $row->stores;
    if (! isset($seen[$v])) {
        $seen[$v] = true;
        echo json_encode($v, JSON_UNESCAPED_UNICODE) . "\n";
        if (count($seen) >= 20) {
            break;
        }
    }
}
echo "distinct-like-61 count attempt: " . DB::table('carte_recharge')->where('stores', 'like', '%61%')->count() . "\n";

echo "\n=== campain_name contains Pluxee (sample) ===\n";
$plx = DB::table('carte_recharge')
    ->where('campain_name', 'like', '%Pluxee%')
    ->select('stores', 'campain_name')
    ->limit(15)
    ->get();
foreach ($plx as $r) {
    echo "stores=" . json_encode($r->stores) . " | campain=" . json_encode($r->campain_name) . "\n";
}

echo "\n=== campain_name contains Hutchinson (sample) ===\n";
$h = DB::table('carte_recharge')
    ->where('campain_name', 'like', '%Hutchinson%')
    ->select('stores', 'campain_name')
    ->limit(10)
    ->get();
foreach ($h as $r) {
    echo "stores=" . json_encode($r->stores) . " | campain=" . json_encode($r->campain_name) . "\n";
}
