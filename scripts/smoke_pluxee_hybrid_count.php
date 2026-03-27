<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$storeId = 61;
$q = DB::table('carte_recharge')->where(function ($w) use ($storeId) {
    $w->where(function ($x) use ($storeId) {
        $sid = (string) $storeId;
        $col = 'carte_recharge.stores';
        $normalized = "REPLACE(REPLACE({$col}, ', ', ','), ' ', '')";
        $x->whereRaw("{$col} = ?", [$sid])
            ->orWhereRaw("FIND_IN_SET(?, {$normalized}) > 0", [$storeId])
            ->orWhereRaw("{$col} LIKE ?", [$sid . ',%'])
            ->orWhereRaw("{$col} LIKE ?", ['%,' . $sid . ',%'])
            ->orWhereRaw("{$col} LIKE ?", ['%,' . $sid]);
    });
    $w->orWhereIn('carte_recharge.carte_recharge_id', function ($sub) use ($storeId) {
        $sub->select('crc.carte_recharge_id')
            ->from('carte_recharge_client as crc')
            ->join('client as cl', 'cl.client_id', '=', 'crc.client_id')
            ->where('cl.sub_store', $storeId);
    });
})->whereNotNull('campain_name');

echo 'Hybrid Pluxee carte_recharge (campain not null): ' . $q->count() . PHP_EOL;
