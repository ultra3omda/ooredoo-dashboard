<?php
/**
 * Classement des résultats transactions_history (status LIKE '%charge%' / facturations Ooredoo)
 * pour alignement avec les stats Ooredoo : novembre 2284, décembre Ooredoo Privilege 4399 + LMS 1073.
 *
 * Usage: php analyze_ooredoo_billing_classification.php
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$novStart = Carbon::parse('2025-11-01')->startOfDay();
$novEnd   = Carbon::parse('2025-11-30')->endOfDay();
$decStart = Carbon::parse('2025-12-01')->startOfDay();
$decEnd   = Carbon::parse('2025-12-31')->endOfDay();

echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "  CLASSEMENT FACTURATIONS OOREDOO (transactions_history)\n";
echo "  Référence Ooredoo: Nov = 2284 | Déc = Ooredoo Privilege 4399 + LMS 1073\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n\n";

// 1) Statuts contenant "charge" (facturation / charge)
$statusesCharge = DB::select("
    SELECT status, COUNT(*) as cnt
    FROM transactions_history
    WHERE status LIKE '%charge%'
    GROUP BY status
    ORDER BY cnt DESC
");
echo "1) STATUTS CONTENANT 'charge' (tous les temps):\n";
if (empty($statusesCharge)) {
    echo "   Aucun statut trouvé avec 'charge'.\n";
} else {
    foreach ($statusesCharge as $row) {
        echo "   - " . $row->status . " : " . number_format($row->cnt) . "\n";
    }
}
echo "\n";

// 2) Statuts Ooredoo facturation (OFFLINE / OFFLINE_INIT) – tous les temps
$statusesOore = DB::select("
    SELECT status, COUNT(*) as cnt
    FROM transactions_history
    WHERE status IN ('OOREDOO_PAYMENT_OFFLINE', 'OOREDOO_PAYMENT_OFFLINE_INIT')
    GROUP BY status
    ORDER BY cnt DESC
");
echo "2) STATUTS OOREDOO FACTURATION (OFFLINE / OFFLINE_INIT):\n";
foreach ($statusesOore as $row) {
    echo "   - " . $row->status . " : " . number_format($row->cnt) . "\n";
}
echo "\n";

// 3) Novembre 2025 – facturations (logique actuelle dashboard: OFFLINE_INIT type=INVOICE + SUCCESS, ou OFFLINE)
$novOffline = (int) DB::table('transactions_history')
    ->where('status', 'OOREDOO_PAYMENT_OFFLINE')
    ->whereBetween('created_at', [$novStart, $novEnd])
    ->count();
$novOfflineInitInvoice = (int) DB::table('transactions_history')
    ->where('status', 'OOREDOO_PAYMENT_OFFLINE_INIT')
    ->whereBetween('created_at', [$novStart, $novEnd])
    ->whereRaw("JSON_EXTRACT(result, '$.type') = 'INVOICE'")
    ->whereRaw("JSON_EXTRACT(result, '$.status') = 'SUCCESS'")
    ->count();
$novTotalCurrent = $novOffline + $novOfflineInitInvoice;

echo "3) NOVEMBRE 2025 – Facturations (logique actuelle dashboard):\n";
echo "   OOREDOO_PAYMENT_OFFLINE       : " . $novOffline . "\n";
echo "   OOREDOO_PAYMENT_OFFLINE_INIT (type=INVOICE, status=SUCCESS) : " . $novOfflineInitInvoice . "\n";
echo "   TOTAL actuel                  : " . $novTotalCurrent . " (référence Ooredoo: 2284)\n";
echo "\n";

// 4) Décembre 2025 – même logique
$decOffline = (int) DB::table('transactions_history')
    ->where('status', 'OOREDOO_PAYMENT_OFFLINE')
    ->whereBetween('created_at', [$decStart, $decEnd])
    ->count();
$decOfflineInitInvoice = (int) DB::table('transactions_history')
    ->where('status', 'OOREDOO_PAYMENT_OFFLINE_INIT')
    ->whereBetween('created_at', [$decStart, $decEnd])
    ->whereRaw("JSON_EXTRACT(result, '$.type') = 'INVOICE'")
    ->whereRaw("JSON_EXTRACT(result, '$.status') = 'SUCCESS'")
    ->count();
$decTotalCurrent = $decOffline + $decOfflineInitInvoice;

echo "4) DÉCEMBRE 2025 – Facturations (logique actuelle dashboard):\n";
echo "   OOREDOO_PAYMENT_OFFLINE       : " . $decOffline . "\n";
echo "   OOREDOO_PAYMENT_OFFLINE_INIT (type=INVOICE, status=SUCCESS) : " . $decOfflineInitInvoice . "\n";
echo "   TOTAL actuel                  : " . $decTotalCurrent . "\n";
echo "   Référence Ooredoo            : Ooredoo Privilege 4399 + LMS 1073 = 5472\n";
echo "   Écart (Ooredoo Privilege 4399 - notre total actuel) : " . (4399 - $decTotalCurrent) . "\n";
echo "\n";

// 5) Types distincts dans result pour OOREDOO_PAYMENT_OFFLINE_INIT (décembre 2025)
$decTypes = DB::select("
    SELECT 
        TRIM(BOTH '\"' FROM JSON_UNQUOTE(JSON_EXTRACT(result, '$.type'))) AS type_val,
        COUNT(*) AS cnt
    FROM transactions_history
    WHERE status = 'OOREDOO_PAYMENT_OFFLINE_INIT'
      AND created_at >= ? AND created_at <= ?
      AND result IS NOT NULL AND result != '' AND JSON_VALID(result)
    GROUP BY type_val
    ORDER BY cnt DESC
", [$decStart, $decEnd]);

echo "5) DÉCEMBRE 2025 – Valeurs de result->type (OOREDOO_PAYMENT_OFFLINE_INIT):\n";
foreach ($decTypes as $row) {
    $typeLabel = $row->type_val === null || $row->type_val === '' ? '(vide/NULL)' : $row->type_val;
    echo "   - type = " . $typeLabel . " : " . number_format($row->cnt) . "\n";
}
echo "\n";

// 6) Compter LMS si type = 'LMS' ou équivalent (décembre)
$decLmsByType = (int) DB::table('transactions_history')
    ->where('status', 'OOREDOO_PAYMENT_OFFLINE_INIT')
    ->whereBetween('created_at', [$decStart, $decEnd])
    ->whereRaw("JSON_EXTRACT(result, '$.type') = 'LMS'")
    ->whereRaw("JSON_EXTRACT(result, '$.status') = 'SUCCESS'")
    ->count();

// Aussi vérifier channel/source ou result brut contenant "LMS"
$decLmsByChannel = (int) DB::table('transactions_history')
    ->where('status', 'OOREDOO_PAYMENT_OFFLINE_INIT')
    ->whereBetween('created_at', [$decStart, $decEnd])
    ->whereRaw("(JSON_EXTRACT(result, '$.channel') = 'LMS' OR JSON_EXTRACT(result, '$.data.channel') = 'LMS' OR JSON_UNQUOTE(JSON_EXTRACT(result, '$.type')) LIKE '%LMS%')")
    ->whereRaw("JSON_EXTRACT(result, '$.status') = 'SUCCESS'")
    ->count();

// Résultats dont le JSON result contient la chaîne "LMS" (offre, channel, etc.)
$decLmsInResult = (int) DB::table('transactions_history')
    ->where('status', 'OOREDOO_PAYMENT_OFFLINE_INIT')
    ->whereBetween('created_at', [$decStart, $decEnd])
    ->where('result', 'LIKE', '%LMS%')
    ->whereRaw("JSON_EXTRACT(result, '$.status') = 'SUCCESS'")
    ->count();

echo "6) DÉCEMBRE 2025 – Facturations LMS (à inclure pour coller aux 1073):\n";
echo "   type = 'LMS' et status = SUCCESS : " . $decLmsByType . "\n";
echo "   channel/data.channel/type LIKE '%LMS%' : " . $decLmsByChannel . "\n";
echo "   result contient 'LMS' (n'importe où) : " . $decLmsInResult . "\n";
echo "\n";

// 7) Exemples result pour OFFLINE_INIT décembre (voir structure LMS)
$samples = DB::table('transactions_history')
    ->where('status', 'OOREDOO_PAYMENT_OFFLINE_INIT')
    ->whereBetween('created_at', [$decStart, $decEnd])
    ->whereNotNull('result')
    ->limit(5)
    ->get(['transaction_history_id', 'result', 'created_at']);

echo "7) EXEMPLES result (OOREDOO_PAYMENT_OFFLINE_INIT, décembre, 5 premiers):\n";
foreach ($samples as $i => $s) {
    $r = $s->result ? json_decode($s->result, true) : null;
    if (!$r) continue;
    $type = $r['type'] ?? '(absent)';
    $status = $r['status'] ?? '(absent)';
    $channel = $r['channel'] ?? ($r['data']['channel'] ?? '(absent)');
    echo "   [" . ($i+1) . "] id={$s->transaction_history_id} type={$type} status={$status} channel={$channel}\n";
}
echo "\n";

// 8) Recommandation classement
echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "  RECOMMANDATION CLASSEMENT / CALCUL FACTURATION\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "  - Ooredoo Privilege (app) = OFFLINE_INIT avec type=INVOICE + SUCCESS (et ancien OFFLINE).\n";
echo "  - LMS Ooredoo Privilege    = OFFLINE_INIT avec type=LMS (ou channel=LMS) + SUCCESS.\n";
echo "  - Total facturations      = Ooredoo Privilege + LMS Ooredoo Privilege.\n";
echo "  - Adapter OoredooStatsService et DashboardService pour compter LMS à part et total.\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";
