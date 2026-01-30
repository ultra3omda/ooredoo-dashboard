<?php
/**
 * Analyse des "result" décembre 2025 via requêtes SQL (rapide).
 * Classement par pricepointId, mnoDeliveryCode, charge > 0.
 * Facturation = pricepointId 63982, mnoDeliveryCode DELIVERED, charge_delivered/totalCharged > 0.
 * Objectif: repérer un groupe ~1073 (LMS).
 *
 * Usage: php analyze_december_result_sql.php
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$decStart = '2025-12-01 00:00:00';
$decEnd   = '2025-12-31 23:59:59';

echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "  ANALYSE RESULT DÉCEMBRE 2025 (SQL)\n";
echo "  Facturation = pricepointId 63982 + mnoDeliveryCode DELIVERED + charge > 0\n";
echo "  Objectif: groupe ~1073 (LMS)\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n\n";

// Colonne charge: totalCharged ou charge_delivered (plusieurs chemins possibles). JSON_EXTRACT retourne un JSON, on cast en nombre avec + 0.
$chargeExpr = "(
    COALESCE(JSON_EXTRACT(result, '$.totalCharged') + 0, 0) +
    COALESCE(JSON_EXTRACT(result, '$.charge_delivered') + 0, 0) +
    COALESCE(JSON_EXTRACT(result, '$.charge_delivred') + 0, 0) +
    COALESCE(JSON_EXTRACT(result, '$.response.totalCharged') + 0, 0) +
    COALESCE(JSON_EXTRACT(result, '$.data.totalCharged') + 0, 0) +
    COALESCE(JSON_EXTRACT(result, '$.invoice.price') + 0, 0) * 1000
)";
// Pour charged > 0 on prend le max des chemins connus
$chargeGt0Expr = "(
    (JSON_EXTRACT(result, '$.totalCharged') + 0) > 0
    OR (JSON_EXTRACT(result, '$.charge_delivered') + 0) > 0
    OR (JSON_EXTRACT(result, '$.response.totalCharged') + 0) > 0
    OR (JSON_EXTRACT(result, '$.data.totalCharged') + 0) > 0
    OR (JSON_EXTRACT(result, '$.invoice.price') IS NOT NULL AND (JSON_EXTRACT(result, '$.invoice.price') + 0) > 0
)";
$ppidExpr = "COALESCE(
    JSON_UNQUOTE(JSON_EXTRACT(result, '$.pricepointId')),
    JSON_UNQUOTE(JSON_EXTRACT(result, '$.pricepoint_id')),
    JSON_UNQUOTE(JSON_EXTRACT(result, '$.response.pricepointId')),
    JSON_UNQUOTE(JSON_EXTRACT(result, '$.data.pricepointId')),
    'null'
)";
$mnoExpr = "LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(result, '$.mnoDeliveryCode')), JSON_UNQUOTE(JSON_EXTRACT(result, '$.response.mnoDeliveryCode')), 'x'))";
// Simplifié pour éviter erreur SQL: charged_pos = 1 si totalCharged ou response.totalCharged > 0
$chargedPosExpr = "IF((JSON_EXTRACT(result, '$.totalCharged') + 0) > 0 OR (JSON_EXTRACT(result, '$.response.totalCharged') + 0) > 0, 1, 0)";

// 1a) 63982 + DELIVERED (sans condition charge). Compare mno à 'delivered' (COALESCE default 'x' donc pas de match si absent)
$fact63982Delivered = DB::selectOne("
    SELECT COUNT(*) AS cnt FROM transactions_history
    WHERE created_at >= ? AND created_at <= ?
    AND result IS NOT NULL AND result != '' AND JSON_VALID(result) = 1
    AND ({$ppidExpr}) = '63982'
    AND ({$mnoExpr}) = 'delivered'
", [$decStart, $decEnd]);

// 1b) Facturation normale: 63982, DELIVERED, charge > 0 (totalCharged, charge_delivered, charge_delivred, response.totalCharged)
$chargeCond = "((JSON_EXTRACT(result, '$.totalCharged') + 0) > 0 OR (JSON_EXTRACT(result, '$.response.totalCharged') + 0) > 0 OR (JSON_EXTRACT(result, '$.charge_delivered') + 0) > 0 OR (JSON_EXTRACT(result, '$.charge_delivred') + 0) > 0)";
$factNormale = DB::selectOne("
    SELECT COUNT(*) AS cnt FROM transactions_history
    WHERE created_at >= ? AND created_at <= ?
    AND result IS NOT NULL AND result != '' AND JSON_VALID(result) = 1
    AND ({$ppidExpr}) = '63982'
    AND ({$mnoExpr}) = 'delivered'
    AND {$chargeCond}
", [$decStart, $decEnd]);

echo "1) FACTURATION (pricepointId=63982, mnoDeliveryCode=DELIVERED)\n";
echo "   63982 + DELIVERED (tous)     : " . number_format($fact63982Delivered->cnt ?? 0) . "\n";
echo "   63982 + DELIVERED + charge>0 : " . number_format($factNormale->cnt ?? 0) . " (facturation normale)\n\n";

// 2) Répartition par pricepointId
echo "2) RÉPARTITION PAR pricepointId (décembre)\n";
$byPpid = DB::select("
    SELECT ({$ppidExpr}) AS ppid, COUNT(*) AS cnt
    FROM transactions_history
    WHERE created_at >= ? AND created_at <= ?
    AND result IS NOT NULL AND result != '' AND JSON_VALID(result) = 1
    GROUP BY ppid
    ORDER BY cnt DESC
    LIMIT 30
", [$decStart, $decEnd]);
foreach ($byPpid as $row) {
    $label = $row->ppid === null || $row->ppid === 'null' ? '(null)' : $row->ppid;
    $flag = (abs((int)$row->cnt - 1073) <= 150) ? "  <-- proche 1073" : "";
    echo "   " . $label . " : " . number_format($row->cnt) . $flag . "\n";
}
echo "\n";

// 3) Pour 63982 + DELIVERED: combien avec charged>0 vs charged=0 (deux COUNT simples)
echo "3) RÉPARTITION 63982 + DELIVERED (charged>0 vs charged=0)\n";
$cnt63982Charged0 = DB::selectOne("
    SELECT COUNT(*) AS cnt FROM transactions_history
    WHERE created_at >= ? AND created_at <= ?
    AND result IS NOT NULL AND result != '' AND JSON_VALID(result) = 1
    AND ({$ppidExpr}) = '63982' AND ({$mnoExpr}) = 'delivered'
    AND (JSON_EXTRACT(result, '$.totalCharged') + 0) <= 0 AND (JSON_EXTRACT(result, '$.response.totalCharged') + 0) <= 0
", [$decStart, $decEnd]);
$cnt63982Charged1 = DB::selectOne("
    SELECT COUNT(*) AS cnt FROM transactions_history
    WHERE created_at >= ? AND created_at <= ?
    AND result IS NOT NULL AND result != '' AND JSON_VALID(result) = 1
    AND ({$ppidExpr}) = '63982' AND ({$mnoExpr}) = 'delivered'
    AND ((JSON_EXTRACT(result, '$.totalCharged') + 0) > 0 OR (JSON_EXTRACT(result, '$.response.totalCharged') + 0) > 0)
", [$decStart, $decEnd]);
echo "   63982|delivered|0 (sans charge) : " . number_format($cnt63982Charged0->cnt ?? 0) . "\n";
echo "   63982|delivered|1 (charge>0)   : " . number_format($cnt63982Charged1->cnt ?? 0);
if (abs(($cnt63982Charged1->cnt ?? 0) - 1073) <= 150) {
    echo "  <-- proche 1073";
}
echo "\n\n";

// 4) entryChannel pour ppid 63980 et 63982 uniquement (pour perf)
echo "4) RÉPARTITION PAR entryChannel (63980 + 63982, décembre)\n";
$byChannel = DB::select("
    SELECT
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(result, '$.entryChannel')), JSON_UNQUOTE(JSON_EXTRACT(result, '$.response.entryChannel')), 'null') AS ch,
        COUNT(*) AS cnt
    FROM transactions_history
    WHERE created_at >= ? AND created_at <= ?
    AND result IS NOT NULL AND result != '' AND JSON_VALID(result) = 1
    AND ({$ppidExpr}) IN ('63980','63982')
    GROUP BY ch
    ORDER BY cnt DESC
    LIMIT 15
", [$decStart, $decEnd]);
foreach ($byChannel as $row) {
    $label = $row->ch === null || $row->ch === 'null' ? '(null)' : $row->ch;
    $flag = (abs((int)$row->cnt - 1073) <= 150) ? "  <-- proche 1073" : "";
    echo "   entryChannel " . $label . " : " . number_format($row->cnt) . $flag . "\n";
}
echo "\n";

// 5) productId pour ppid 63980 et 63982 uniquement
echo "5) RÉPARTITION PAR productId (63980 + 63982, décembre)\n";
$byProduct = DB::select("
    SELECT
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(result, '$.productId')), JSON_UNQUOTE(JSON_EXTRACT(result, '$.product_id')), 'null') AS product_id,
        COUNT(*) AS cnt
    FROM transactions_history
    WHERE created_at >= ? AND created_at <= ?
    AND result IS NOT NULL AND result != '' AND JSON_VALID(result) = 1
    AND ({$ppidExpr}) IN ('63980','63982')
    GROUP BY product_id
    ORDER BY cnt DESC
    LIMIT 15
", [$decStart, $decEnd]);
foreach ($byProduct as $row) {
    $label = $row->product_id === null || $row->product_id === 'null' ? '(null)' : $row->product_id;
    $flag = (abs((int)$row->cnt - 1073) <= 150) ? "  <-- proche 1073" : "";
    echo "   productId " . $label . " : " . number_format($row->cnt) . $flag . "\n";
}
echo "\n";

// 6) Groupes proches de 1073 – seulement combinaisons (ppid, entryChannel) pour 63980/63982
echo "6) GROUPES PROCHES DE 1073 (entryChannel pour 63982)\n";
$near = DB::select("
    SELECT ({$ppidExpr}) AS ppid, COALESCE(JSON_UNQUOTE(JSON_EXTRACT(result, '$.entryChannel')), 'null') AS ch, COUNT(*) AS cnt
    FROM transactions_history
    WHERE created_at >= ? AND created_at <= ?
    AND result IS NOT NULL AND result != '' AND JSON_VALID(result) = 1
    AND ({$ppidExpr}) = '63982'
    GROUP BY ppid, ch
    HAVING cnt BETWEEN 923 AND 1223
    ORDER BY cnt DESC
    LIMIT 20
", [$decStart, $decEnd]);
if (empty($near)) {
    echo "   Aucun groupe (63982 + entryChannel) entre 923 et 1223.\n";
} else {
    foreach ($near as $row) {
        echo "   ppid=" . $row->ppid . " entryChannel=" . $row->ch . " : " . $row->cnt . "\n";
    }
}
echo "\n";

// 9) Un échantillon result pour 63982 + DELIVERED (voir structure charge)
echo "9) EXEMPLE result (63982 + DELIVERED, décembre)\n";
$sample = DB::selectOne("
    SELECT result FROM transactions_history
    WHERE created_at >= ? AND created_at <= ?
    AND result IS NOT NULL AND result != '' AND JSON_VALID(result) = 1
    AND ({$ppidExpr}) = '63982'
    AND ({$mnoExpr}) = 'delivered'
    LIMIT 1
", [$decStart, $decEnd]);
if ($sample && !empty($sample->result)) {
    $r = json_decode($sample->result, true);
    if (is_array($r)) {
        echo "   Clés racine: " . implode(', ', array_keys($r)) . "\n";
        foreach (['totalCharged','charge_delivered','charge_delivred','pricepointId','mnoDeliveryCode','entryChannel','productId'] as $k) {
            $v = $r[$k] ?? (isset($r['response'][$k]) ? $r['response'][$k] : (isset($r['data'][$k]) ? $r['data'][$k] : null));
            if ($v !== null) {
                echo "   {$k}: " . (is_array($v) ? json_encode($v) : $v) . "\n";
            }
        }
    }
} else {
    echo "   Aucun échantillon trouvé.\n";
}
echo "\n";

echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "  Comparer les effectifs ci‑dessus aux 1073 LMS Ooredoo.\n";
echo "  Si facturation normale = 0, vérifier le nom du champ charge dans l'exemple (9).\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";
