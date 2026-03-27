<?php

/**
 * Vérification rapide des données Pluxee / store 61 (hors HTTP, pas de timeout navigateur).
 * Usage : php scripts/check_pluxee_data.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$storeId = (int) (config('substore.pluxee_distributor_store_ids')[0] ?? 61);

echo "=== Pluxee / distributeur store_id = {$storeId} ===\n";

try {
    $store = DB::table('stores')->where('store_id', $storeId)->first();
    echo $store ? "stores: OK row 61 name=" . ($store->store_name ?? '?') . " active=" . ($store->store_active ?? '?') . " is_sub_store=" . ($store->is_sub_store ?? '?') . "\n"
        : "stores: AUCUNE ligne store_id={$storeId}\n";

    $crTotal = DB::table('carte_recharge')->count();
    echo "carte_recharge total rows: {$crTotal}\n";

    // Même logique que addWhereStoresColumnContainsStoreId (simplifié)
    $n61 = DB::table('carte_recharge')
        ->where(function ($q) use ($storeId) {
            $sid = (string) $storeId;
            $col = 'stores';
            $norm = "REPLACE(REPLACE({$col}, ', ', ','), ' ', '')";
            $q->whereRaw("{$col} = ?", [$sid])
                ->orWhereRaw("FIND_IN_SET(?, {$norm}) > 0", [$storeId])
                ->orWhereRaw("{$col} LIKE ?", [$sid . ',%'])
                ->orWhereRaw("{$col} LIKE ?", ['%,' . $sid . ',%'])
                ->orWhereRaw("{$col} LIKE ?", ['%,' . $sid]);
        })
        ->count();
    echo "carte_recharge matching distributor {$storeId}: {$n61}\n";

    $withCampaign = DB::table('carte_recharge')
        ->where(function ($q) use ($storeId) {
            $sid = (string) $storeId;
            $col = 'stores';
            $norm = "REPLACE(REPLACE({$col}, ', ', ','), ' ', '')";
            $q->whereRaw("{$col} = ?", [$sid])
                ->orWhereRaw("FIND_IN_SET(?, {$norm}) > 0", [$storeId])
                ->orWhereRaw("{$col} LIKE ?", [$sid . ',%'])
                ->orWhereRaw("{$col} LIKE ?", ['%,' . $sid . ',%'])
                ->orWhereRaw("{$col} LIKE ?", ['%,' . $sid]);
        })
        ->whereNotNull('campain_name')
        ->where('campain_name', '<>', '')
        ->distinct()
        ->count('campain_name');
    echo "distinct campain_name (non vides) for distributor: {$withCampaign}\n";

    $crc = DB::table('carte_recharge_client')->count();
    echo "carte_recharge_client total: {$crc}\n";

    $linked = DB::table('carte_recharge as cr')
        ->join('carte_recharge_client as crc', 'cr.carte_recharge_id', '=', 'crc.carte_recharge_id')
        ->where(function ($q) use ($storeId) {
            $sid = (string) $storeId;
            $col = 'cr.stores';
            $norm = "REPLACE(REPLACE({$col}, ', ', ','), ' ', '')";
            $q->whereRaw("{$col} = ?", [$sid])
                ->orWhereRaw("FIND_IN_SET(?, {$norm}) > 0", [$storeId])
                ->orWhereRaw("{$col} LIKE ?", [$sid . ',%'])
                ->orWhereRaw("{$col} LIKE ?", ['%,' . $sid . ',%'])
                ->orWhereRaw("{$col} LIKE ?", ['%,' . $sid]);
        })
        ->distinct('crc.client_id')
        ->count('crc.client_id');
    echo "clients with at least one card distributor {$storeId}: {$linked}\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== fin ===\n";
