<?php
/**
 * Résultats facturations pour le mois de décembre 2025.
 * Les facturations sont recherchées par TRANSACTIONS TIMWE (status TIMWE_RENEWED_NOTIF ou TIMWE_CHARGE_DELIVERED),
 * pas par statut Ooredoo dans transactions_history.
 *
 * Usage: php resultats_decembre_2025.php
 */
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$decStart = Carbon::parse('2025-12-01')->startOfDay();
$decEnd   = Carbon::parse('2025-12-31')->endOfDay();
$billingPpid = env('TIMWE_BILLING_PPID', '63980');

echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "  RÉSULTATS DÉCEMBRE 2025 (Facturations – transactions TIMWE)\n";
echo "  Source: transactions_history avec status TIMWE_RENEWED_NOTIF ou TIMWE_CHARGE_DELIVERED\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n\n";

// Source principale: NOMBRE FACTURATION = transactions TIMWE (pas Ooredoo dans status)
// Critère: status TIMWE_RENEWED_NOTIF ou TIMWE_CHARGE_DELIVERED, puis result avec pricepointId=63980, mnoDeliveryCode=DELIVERED, totalCharged>0

// 1) Nombre facturation décembre = somme timwe_daily_stats (calculé depuis transactions Timwe)
$facturationNormale = (int) DB::table('timwe_daily_stats')
    ->whereBetween('stat_date', [$decStart->format('Y-m-d'), $decEnd->format('Y-m-d')])
    ->sum('total_billings');

// 2) LMS parmi transactions Timwe (requête ciblée sur type=LMS ou dimensions.billingChannel=LMS)
$baseTimwe = function () use ($decStart, $decEnd) {
    return DB::table('transactions_history')
        ->whereBetween('created_at', [$decStart, $decEnd])
        ->where(function ($q) {
            $q->where('status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
              ->orWhere('status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
        })
        ->whereNotNull('result')
        ->where('result', '!=', '')
        ->whereRaw('JSON_VALID(result) = 1');
};

$lmsType = $baseTimwe()
    ->whereRaw("JSON_EXTRACT(result, '$.type') = 'LMS'")
    ->whereRaw("(COALESCE(JSON_EXTRACT(result, '$.totalCharged'), 0) + 0) > 0")
    ->count();

$lmsBillingChannel = $baseTimwe()
    ->whereRaw("LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(result, '$.dimensions.billingChannel')), '')) = 'lms'")
    ->whereRaw("(COALESCE(JSON_EXTRACT(result, '$.totalCharged'), 0) + 0) > 0")
    ->count();

// 3) Répartition par pricepointId (transactions Timwe décembre) – requête agrégée
$byPpid = DB::table('transactions_history')
    ->whereBetween('created_at', [$decStart, $decEnd])
    ->where(function ($q) {
        $q->where('status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')->orWhere('status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
    })
    ->whereNotNull('result')
    ->whereRaw('JSON_VALID(result) = 1')
    ->selectRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(result, '$.pricepointId')), '(null)') as ppid, COUNT(*) as cnt")
    ->groupBy('ppid')
    ->orderByDesc('cnt')
    ->limit(10)
    ->get();

$totalTimweTransactions = null; // non calculé (lourd); voir timwe_daily_stats pour total facturations

echo "TIMWE – Facturations décembre 2025 (source: transactions avec status TIMWE_*, pas Ooredoo):\n";
echo "  Nombre facturation (timwe_daily_stats, calculé depuis transactions Timwe): " . number_format($facturationNormale) . "\n";
echo "  LMS (parmi transactions Timwe, result.type=LMS):     " . number_format($lmsType) . "\n";
echo "  LMS (parmi transactions Timwe, dimensions.billingChannel=LMS): " . number_format($lmsBillingChannel) . "\n";
echo "  ─────────────────────────────────────────\n";
echo "  Total facturations (normale + LMS si distincts):     " . number_format($facturationNormale + $lmsType + $lmsBillingChannel) . "\n\n";

echo "Répartition par pricepointId (transactions Timwe, décembre):\n";
foreach ($byPpid as $row) {
    $flag = ($row->ppid === $billingPpid) ? "  (facturation normale si DELIVERED + charge>0)" : "";
    echo "  pricepointId " . $row->ppid . " : " . number_format($row->cnt) . $flag . "\n";
}

echo "\nRéférence (stats envoyées):\n";
echo "  Ooredoo Privilege (déc.): 4 399\n";
echo "  LMS Ooredoo Privilege:    1 073\n";
echo "\n═══════════════════════════════════════════════════════════════════════════════\n";
