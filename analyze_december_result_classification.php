<?php
/**
 * Analyse des champs "result" de transactions_history pour DÉCEMBRE 2025.
 * Classement par: productId, pricepointId, mcc, mnc, mnoDeliveryCode, entryChannel, tags, charge_delivered/totalCharged.
 *
 * Définition facturation:
 *   - pricepointId = 63982
 *   - mnoDeliveryCode = DELIVERED
 *   - charge_delivered > 0 (ou totalCharged > 0)
 *
 * Objectif: repérer un groupe d'environ 1073 réponses (LMS) à comparer aux stats Ooredoo.
 *
 * Usage: php analyze_december_result_classification.php
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$decStart = Carbon::parse('2025-12-01')->startOfDay();
$decEnd   = Carbon::parse('2025-12-31')->endOfDay();

function extractCharge(array $r): float
{
    $keys = ['charge_delivered', 'charge_delivred', 'totalCharged', 'total_charged', 'charged_amount', 'totalChargedAmount'];
    foreach ($keys as $k) {
        if (isset($r[$k]) && (is_numeric($r[$k]) || $r[$k] !== null)) {
            return (float) $r[$k];
        }
    }
    foreach (['response', 'data', 'user'] as $parent) {
        if (isset($r[$parent]) && is_array($r[$parent])) {
            foreach ($keys as $k) {
                if (isset($r[$parent][$k]) && (is_numeric($r[$parent][$k]) || $r[$parent][$k] !== null)) {
                    return (float) $r[$parent][$k];
                }
            }
        }
    }
    return 0.0;
}

function extractPpid(array $r): ?string
{
    $keys = ['pricepointId', 'pricepoint_id', 'pricePointId', 'ppid', 'PPID'];
    foreach ($keys as $k) {
        if (isset($r[$k]) && $r[$k] !== null && $r[$k] !== '') {
            return (string) $r[$k];
        }
    }
    foreach (['response', 'data', 'user'] as $parent) {
        if (isset($r[$parent]) && is_array($r[$parent])) {
            foreach ($keys as $k) {
                if (isset($r[$parent][$k]) && $r[$parent][$k] !== null && $r[$parent][$k] !== '') {
                    return (string) $r[$parent][$k];
                }
            }
        }
    }
    return null;
}

function extractMnoDeliveryCode(array $r): ?string
{
    $k = 'mnoDeliveryCode';
    if (isset($r[$k]) && $r[$k] !== null && $r[$k] !== '') {
        return (string) $r[$k];
    }
    foreach (['response', 'data'] as $parent) {
        if (isset($r[$parent][$k])) {
            return (string) $r[$parent][$k];
        }
    }
    return null;
}

function extractProductId(array $r): ?string
{
    $keys = ['productId', 'product_id'];
    foreach ($keys as $k) {
        if (isset($r[$k]) && $r[$k] !== null && $r[$k] !== '') {
            return (string) $r[$k];
        }
    }
    foreach (['response', 'data'] as $parent) {
        if (isset($r[$parent]) && is_array($r[$parent])) {
            foreach ($keys as $key) {
                if (isset($r[$parent][$key])) {
                    return (string) $r[$parent][$key];
                }
            }
        }
    }
    return null;
}

function extractEntryChannel(array $r): ?string
{
    $k = 'entryChannel';
    if (isset($r[$k]) && $r[$k] !== null && $r[$k] !== '') {
        return (string) $r[$k];
    }
    foreach (['response', 'data'] as $parent) {
        if (isset($r[$parent][$k])) {
            return (string) $r[$parent][$k];
        }
    }
    return null;
}

echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "  ANALYSE RESULT – DÉCEMBRE 2025 (toute la table transactions_history)\n";
echo "  Facturation = pricepointId 63982 + mnoDeliveryCode DELIVERED + charge_delivered > 0\n";
echo "  Objectif: trouver un groupe ~1073 (LMS)\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n\n";

// Agrégats en mémoire (clés = chaînes de classification)
$byPpid = [];
$byPpidMnoCharged = []; // "ppid|mno|charged>0"
$byProductId = [];
$byEntryChannel = [];
$byPpidEntryChannel = [];
$facturationNormale = 0; // pricepointId 63982, DELIVERED, charge > 0
$totalAvecResult = 0;
$totalSansResult = 0;
$totalInvalidJson = 0;

$chunkSize = 2000;
$lastId = 0;

while (true) {
    $rows = DB::table('transactions_history')
        ->where('created_at', '>=', $decStart)
        ->where('created_at', '<=', $decEnd)
        ->where('transaction_history_id', '>', $lastId)
        ->orderBy('transaction_history_id')
        ->limit($chunkSize)
        ->get(['transaction_history_id', 'status', 'result']);

    if ($rows->isEmpty()) {
        break;
    }

    foreach ($rows as $row) {
        $lastId = $row->transaction_history_id;
        if ($row->result === null || $row->result === '') {
            $totalSansResult++;
            continue;
        }
        $r = json_decode($row->result, true);
        if (!is_array($r)) {
            $totalInvalidJson++;
            continue;
        }
        $totalAvecResult++;

        $ppid = extractPpid($r);
        $mno = extractMnoDeliveryCode($r);
        $charge = extractCharge($r);
        $chargedPos = $charge > 0 ? 1 : 0;
        $productId = extractProductId($r);
        $entryChannel = extractEntryChannel($r);

        $ppidKey = $ppid ?? '(null)';
        $mnoKey = $mno ?? '(null)';
        $productKey = $productId ?? '(null)';
        $channelKey = $entryChannel ?? '(null)';

        if (!isset($byPpid[$ppidKey])) {
            $byPpid[$ppidKey] = 0;
        }
        $byPpid[$ppidKey]++;

        $keyPpidMno = $ppidKey . '|' . $mnoKey . '|' . $chargedPos;
        if (!isset($byPpidMnoCharged[$keyPpidMno])) {
            $byPpidMnoCharged[$keyPpidMno] = 0;
        }
        $byPpidMnoCharged[$keyPpidMno]++;

        if (!isset($byProductId[$productKey])) {
            $byProductId[$productKey] = 0;
        }
        $byProductId[$productKey]++;

        if ($channelKey !== '(null)') {
            if (!isset($byEntryChannel[$channelKey])) {
                $byEntryChannel[$channelKey] = 0;
            }
            $byEntryChannel[$channelKey]++;
            $keyPpidCh = $ppidKey . '|' . $channelKey;
            if (!isset($byPpidEntryChannel[$keyPpidCh])) {
                $byPpidEntryChannel[$keyPpidCh] = 0;
            }
            $byPpidEntryChannel[$keyPpidCh]++;
        }

        // Facturation normale: pricepointId 63982, mnoDeliveryCode DELIVERED, charge > 0
        if ($ppid === '63982' && $mno === 'DELIVERED' && $charge > 0) {
            $facturationNormale++;
        }
    }

    echo "  Traité jusqu'à id {$lastId}...\n";
}

$totalProcessed = $totalAvecResult + $totalSansResult + $totalInvalidJson;
echo "\n1) VOLUMÉTRIE DÉCEMBRE 2025\n";
echo "   Total lignes traitées     : " . number_format($totalProcessed) . "\n";
echo "   Avec result non vide     : " . number_format($totalAvecResult) . "\n";
echo "   Sans result / vide       : " . number_format($totalSansResult) . "\n";
echo "   Result JSON invalide     : " . number_format($totalInvalidJson) . "\n";
echo "\n";

echo "2) FACTURATION NORMALE (pricepointId=63982, mnoDeliveryCode=DELIVERED, charge_delivered>0)\n";
echo "   Nombre : " . number_format($facturationNormale) . "\n";
echo "\n";

echo "3) RÉPARTITION PAR pricepointId (décembre)\n";
arsort($byPpid);
foreach ($byPpid as $ppid => $cnt) {
    $flag = (abs($cnt - 1073) <= 150) ? "  <-- proche de 1073" : "";
    echo "   pricepointId " . $ppid . " : " . number_format($cnt) . $flag . "\n";
}
echo "\n";

echo "4) RÉPARTITION PAR (pricepointId | mnoDeliveryCode | charged>0)\n";
arsort($byPpidMnoCharged);
$shown = 0;
foreach ($byPpidMnoCharged as $key => $cnt) {
    if ($shown >= 40) {
        echo "   ... (tronqué)\n";
        break;
    }
    $flag = (abs($cnt - 1073) <= 150) ? "  <-- proche de 1073" : "";
    echo "   " . $key . " : " . number_format($cnt) . $flag . "\n";
    $shown++;
}
echo "\n";

echo "5) RÉPARTITION PAR productId (décembre)\n";
arsort($byProductId);
foreach (array_slice($byProductId, 0, 25) as $pid => $cnt) {
    $flag = (abs($cnt - 1073) <= 150) ? "  <-- proche de 1073" : "";
    echo "   productId " . $pid . " : " . number_format($cnt) . $flag . "\n";
}
echo "\n";

echo "6) RÉPARTITION PAR entryChannel (décembre)\n";
arsort($byEntryChannel);
foreach ($byEntryChannel as $ch => $cnt) {
    $flag = (abs($cnt - 1073) <= 150) ? "  <-- proche de 1073" : "";
    echo "   entryChannel " . $ch . " : " . number_format($cnt) . $flag . "\n";
}
echo "\n";

echo "7) GROUPES PROCHES DE 1073 (écart <= 150)\n";
$near1073 = [];
foreach ($byPpidMnoCharged as $key => $cnt) {
    if (abs($cnt - 1073) <= 150) {
        $near1073[] = ['key' => $key, 'count' => $cnt];
    }
}
foreach ($byProductId as $pid => $cnt) {
    if (abs($cnt - 1073) <= 150) {
        $near1073[] = ['key' => 'productId=' . $pid, 'count' => $cnt];
    }
}
foreach ($byEntryChannel as $ch => $cnt) {
    if (abs($cnt - 1073) <= 150) {
        $near1073[] = ['key' => 'entryChannel=' . $ch, 'count' => $cnt];
    }
}
foreach ($byPpidEntryChannel as $key => $cnt) {
    if (abs($cnt - 1073) <= 150) {
        $near1073[] = ['key' => $key, 'count' => $cnt];
    }
}
if (empty($near1073)) {
    echo "   Aucun groupe avec effectif entre 923 et 1223.\n";
} else {
    foreach ($near1073 as $item) {
        echo "   " . $item['key'] . " : " . $item['count'] . "\n";
    }
}
echo "\n";

// Requête SQL directe pour facturation normale (63982, DELIVERED, charge > 0)
echo "8) VÉRIFICATION SQL (facturation = pricepointId 63982 + mnoDeliveryCode DELIVERED + charge > 0)\n";
$sqlFact = DB::select("
    SELECT COUNT(*) AS cnt
    FROM transactions_history
    WHERE created_at >= ? AND created_at <= ?
    AND result IS NOT NULL AND result != '' AND JSON_VALID(result) = 1
    AND (
        TRIM(BOTH '\"' FROM COALESCE(JSON_UNQUOTE(JSON_EXTRACT(result, '$.pricepointId')), JSON_UNQUOTE(JSON_EXTRACT(result, '$.pricepoint_id')), JSON_UNQUOTE(JSON_EXTRACT(result, '$.response.pricepointId')), '')) = '63982'
    )
    AND (
        LOWER(TRIM(BOTH '\"' FROM COALESCE(JSON_UNQUOTE(JSON_EXTRACT(result, '$.mnoDeliveryCode')), JSON_UNQUOTE(JSON_EXTRACT(result, '$.response.mnoDeliveryCode')), ''))) = 'delivered'
    )
    AND (
        (COALESCE(JSON_EXTRACT(result, '$.totalCharged'), JSON_EXTRACT(result, '$.charge_delivered'), JSON_EXTRACT(result, '$.response.totalCharged'), 0) + 0) > 0
    )
", [$decStart, $decEnd]);
echo "   Facturation normale (SQL) : " . ($sqlFact[0]->cnt ?? 0) . "\n";
echo "\n";

echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "  Fin analyse. Comparer les effectifs ci‑dessus aux 1073 LMS Ooredoo.\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";
