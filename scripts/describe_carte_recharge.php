<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$cols = Schema::getColumnListing('carte_recharge');
echo "Columns carte_recharge: " . implode(', ', $cols) . "\n\n";

$row = DB::table('carte_recharge')->first();
if ($row) {
    echo "First row (truncated):\n";
    foreach ((array) $row as $k => $v) {
        $s = is_string($v) ? $v : json_encode($v);
        if (strlen($s) > 120) {
            $s = substr($s, 0, 120) . '...';
        }
        echo "  {$k}: {$s}\n";
    }
}

echo "\n=== Search 61 in any string column (limit 5 rows) ===\n";
$driver = DB::getDriverName();
if ($driver === 'mysql') {
    $db = DB::getDatabaseName();
    $rows = DB::select("
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'carte_recharge'
        AND DATA_TYPE IN ('varchar','text','char','json')
    ", [$db]);
    foreach ($rows as $c) {
        $col = $c->COLUMN_NAME;
        try {
            $n = DB::table('carte_recharge')->where($col, 'like', '%61%')->count();
            if ($n > 0) {
                echo "column {$col}: {$n} rows like %61%\n";
                $ex = DB::table('carte_recharge')->where($col, 'like', '%61%')->select($col)->first();
                if ($ex) {
                    echo "  example: " . json_encode($ex->{$col}) . "\n";
                }
            }
        } catch (Throwable $e) {
            // skip
        }
    }
}
