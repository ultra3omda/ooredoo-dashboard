<?php
/**
 * Extrait les critères pour identifier la facturation LMS.
 * Parcourt les "result" (décembre 2025, OOREDOO_PAYMENT_OFFLINE_INIT) et affiche
 * les valeurs distinctes des champs susceptibles de distinguer LMS de la facturation normale
 * (type, channel, entryChannel, source, productId, etc.).
 *
 * Usage: php extract_lms_criteria.php
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$decStart = Carbon::parse('2025-12-01')->startOfDay();
$decEnd   = Carbon::parse('2025-12-31')->endOfDay();

// Champs à extraire (racine + chemins imbriqués). dimensions.* identifié comme candidat pour LMS.
$fieldsToExtract = [
    'type' => ['type', 'response.type', 'data.type'],
    'channel' => ['channel', 'response.channel', 'data.channel', 'data.source'],
    'entryChannel' => ['entryChannel', 'response.entryChannel', 'data.entryChannel'],
    'source' => ['source', 'response.source', 'data.source'],
    'productId' => ['productId', 'product_id', 'response.productId', 'data.productId'],
    'pricepointId' => ['pricepointId', 'pricepoint_id', 'response.pricepointId', 'data.pricepointId'],
    'dimensions.billingChannel' => ['dimensions.billingChannel'],
    'dimensions.networkChannel' => ['dimensions.networkChannel'],
    'dimensions.orderChannel' => ['dimensions.orderChannel'],
];

function getValueAtPath(array $data, string $path) {
    $keys = explode('.', $path);
    $current = $data;
    foreach ($keys as $key) {
        if (!is_array($current) || !array_key_exists($key, $current)) {
            return null;
        }
        $current = $current[$key];
    }
    return $current;
}

function collectKeysRecursive(array $arr, string $prefix = ''): array {
    $out = [];
    foreach ($arr as $k => $v) {
        $path = $prefix ? $prefix . '.' . $k : $k;
        $out[$path] = true;
        if (is_array($v) && !empty($v)) {
            $out = array_merge($out, collectKeysRecursive($v, $path));
        }
    }
    return $out;
}

$distinctByField = [];
foreach (array_keys($fieldsToExtract) as $name) {
    $distinctByField[$name] = [];
}
$allKeys = [];
$sampleWithLms = null;
$sampleInvoice = null;
$chunkSize = 3000;
$lastId = 0;
$totalProcessed = 0;
$maxProcess = 50000; // limiter pour ne pas timeout

echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "  EXTRACTION CRITÈRES FACTURATION LMS (décembre 2025, OOREDOO_PAYMENT_OFFLINE_INIT)\n";
echo "  Objectif: repérer le paramètre différent par rapport à la facturation normale\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n\n";

while ($totalProcessed < $maxProcess) {
    $rows = DB::table('transactions_history')
        ->where('status', 'OOREDOO_PAYMENT_OFFLINE_INIT')
        ->where('created_at', '>=', $decStart)
        ->where('created_at', '<=', $decEnd)
        ->where('transaction_history_id', '>', $lastId)
        ->whereNotNull('result')
        ->where('result', '!=', '')
        ->orderBy('transaction_history_id')
        ->limit($chunkSize)
        ->get(['transaction_history_id', 'result']);

    if ($rows->isEmpty()) {
        break;
    }

    foreach ($rows as $row) {
        $lastId = $row->transaction_history_id;
        $totalProcessed++;
        $r = json_decode($row->result, true);
        if (!is_array($r)) {
            continue;
        }

        $allKeys = array_merge($allKeys, collectKeysRecursive($r));

        foreach ($fieldsToExtract as $fieldName => $paths) {
            foreach ($paths as $path) {
                $v = getValueAtPath($r, $path);
                if ($v !== null && $v !== '') {
                    $key = is_bool($v) ? ($v ? 'true' : 'false') : (string)$v;
                    if (!isset($distinctByField[$fieldName][$key])) {
                        $distinctByField[$fieldName][$key] = 0;
                    }
                    $distinctByField[$fieldName][$key]++;
                    break; // une seule valeur par champ
                }
            }
        }

        // Garder un échantillon dont result contient "LMS" (pour afficher dimensions, etc.)
        if ($sampleWithLms === null && (stripos($row->result, 'LMS') !== false)) {
            $sampleWithLms = $r;
        }
        // Aussi garder un échantillon type=INVOICE pour comparer la structure
        if (!isset($sampleInvoice) && isset($r['type']) && $r['type'] === 'INVOICE') {
            $sampleInvoice = $r;
        }
    }

    echo "  Traité " . number_format($totalProcessed) . " lignes...\n";
}

echo "\n1) VALEURS DISTINCTES PAR CHAMP (échantillon décembre)\n";
echo str_repeat('─', 70) . "\n";
foreach ($distinctByField as $fieldName => $values) {
    if (empty($values)) {
        echo "   {$fieldName}: (aucune valeur trouvée)\n";
        continue;
    }
    arsort($values);
    echo "   {$fieldName}:\n";
    foreach ($values as $val => $count) {
        $flag = (stripos($val, 'LMS') !== false) ? "  <-- contient LMS" : "";
        echo "      - " . substr($val, 0, 80) . " : " . number_format($count) . $flag . "\n";
    }
    echo "\n";
}

echo "2) CLÉS PRÉSENTES DANS result (racine ou imbriquées)\n";
$uniqueKeys = array_unique(array_keys($allKeys));
sort($uniqueKeys);
$lmsRelated = array_filter($uniqueKeys, function ($k) {
    return stripos($k, 'lms') !== false || stripos($k, 'channel') !== false || stripos($k, 'type') !== false || stripos($k, 'source') !== false;
});
echo "   Clés liées à type/channel/source/LMS: " . implode(', ', array_slice($lmsRelated, 0, 30)) . "\n";
echo "   (Total clés vues: " . count($uniqueKeys) . ")\n\n";

echo "3) EXEMPLE result CONTENANT 'LMS' (structure)\n";
if ($sampleWithLms !== null) {
    echo "   Clés racine: " . implode(', ', array_keys($sampleWithLms)) . "\n";
    foreach (['type', 'channel', 'entryChannel', 'source', 'status'] as $k) {
        if (isset($sampleWithLms[$k])) {
            $v = $sampleWithLms[$k];
            echo "   result.{$k} = " . (is_array($v) ? json_encode($v) : $v) . "\n";
        }
        if (isset($sampleWithLms['data'][$k])) {
            echo "   result.data.{$k} = " . (is_array($sampleWithLms['data'][$k]) ? json_encode($sampleWithLms['data'][$k]) : $sampleWithLms['data'][$k]) . "\n";
        }
    }
    if (isset($sampleWithLms['dimensions']) && is_array($sampleWithLms['dimensions'])) {
        echo "   result.dimensions: " . json_encode($sampleWithLms['dimensions']) . "\n";
    }
} else {
    echo "   Aucun result contenant 'LMS' trouvé dans l'échantillon.\n";
}

if (isset($sampleInvoice) && $sampleWithLms !== null) {
    echo "\n4) DIFFÉRENCE type INVOICE vs result contenant 'LMS' (paramètre différent)\n";
    echo "   INVOICE sample - dimensions: " . (isset($sampleInvoice['dimensions']) ? json_encode($sampleInvoice['dimensions']) : '(absent)') . "\n";
    echo "   LMS sample     - dimensions: " . (isset($sampleWithLms['dimensions']) ? json_encode($sampleWithLms['dimensions']) : '(absent)') . "\n";
}

echo "\n═══════════════════════════════════════════════════════════════════════════════\n";
echo "  Le paramètre différent pour la facturation LMS est en priorité result.type = 'LMS'.\n";
echo "  Si type n'est pas utilisé, vérifier les valeurs ci‑dessus (channel, entryChannel, source).\n";
echo "  Voir CRITERES_FACTURATION_LMS.md pour la synthèse.\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";
