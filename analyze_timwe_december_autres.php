<?php
/**
 * Analyse des réponses TIMWE du mois de décembre (TIMWE_RENEWED_NOTIF / TIMWE_CHARGE_DELIVERED).
 * Objectif : distinguer la facturation "normale" (pricepointId 63980, mnoDeliveryCode DELIVERED, totalCharged > 0)
 *            et les "facturations autres", puis grouper les autres pour comparer aux chiffres de l'intégrateur.
 *
 * Usage: php analyze_timwe_december_autres.php [référence_intégrateur]
 *   Ex: php analyze_timwe_december_autres.php 1073
 *   Si un nombre est fourni, les groupes proches (±150) de ce nombre sont signalés.
 */
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$decStart = Carbon::parse('2025-12-01')->startOfDay();
$decEnd   = Carbon::parse('2025-12-31')->endOfDay();
$billingPpid = env('TIMWE_BILLING_PPID', '63980');
$referenceIntegrateur = isset($argv[1]) && is_numeric($argv[1]) ? (int) $argv[1] : null;

function extractCharge(array $r): float
{
    $keys = ['totalCharged', 'charge_delivered', 'charge_delivred', 'total_charged', 'charged_amount', 'totalChargedAmount'];
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
        if (isset($r[$parent]) && is_array($r[$parent]) && isset($r[$parent][$k])) {
            return (string) $r[$parent][$k];
        }
    }
    return null;
}

echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "  ANALYSE TIMWE DÉCEMBRE 2025 – Facturation normale vs facturations autres\n";
echo "  Source: status TIMWE_RENEWED_NOTIF ou TIMWE_CHARGE_DELIVERED uniquement\n";
echo "  Normale = pricepointId {$billingPpid}, mnoDeliveryCode DELIVERED, totalCharged > 0\n";
echo "  Autres = tout le reste (autre ppid, autre delivery, totalCharged ≤ 0, etc.)\n";
if ($referenceIntegrateur !== null) {
    echo "  Référence intégrateur : " . number_format($referenceIntegrateur) . " (groupes proches ±150 signalés)\n";
}
echo "═══════════════════════════════════════════════════════════════════════════════\n\n";

// Compteurs
$totalTimwe = 0;
$normale = 0;       // ppid 63980, DELIVERED, totalCharged > 0
$autres = 0;        // tout le reste avec result valide
$autresAvecCharge = 0;  // autres avec totalCharged > 0 (facturations « autres » potentiellement facturables)
$sansResult = 0;
$resultInvalid = 0;
// Groupes "autres" : clé => effectif
$byPpid = [];
$byPpidMnoCharged = []; // "ppid|mno|charged>0"
$byMno = [];
$byChargedPos = [];  // "charged>0" / "charged=0"

$chunkSize = 2000;
$lastId = 0;

while (true) {
    $rows = DB::table('transactions_history')
        ->where('created_at', '>=', $decStart)
        ->where('created_at', '<=', $decEnd)
        ->where(function ($q) {
            $q->where('status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
              ->orWhere('status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
        })
        ->where('transaction_history_id', '>', $lastId)
        ->orderBy('transaction_history_id')
        ->limit($chunkSize)
        ->get(['transaction_history_id', 'status', 'result']);

    if ($rows->isEmpty()) {
        break;
    }

    foreach ($rows as $row) {
        $lastId = $row->transaction_history_id;
        $totalTimwe++;

        if ($row->result === null || $row->result === '') {
            $sansResult++;
            continue;
        }
        $r = json_decode($row->result, true);
        if (!is_array($r)) {
            $resultInvalid++;
            continue;
        }

        $ppid = extractPpid($r);
        $mno = extractMnoDeliveryCode($r);
        $charge = extractCharge($r);
        $chargedPos = $charge > 0 ? 1 : 0;

        $ppidKey = $ppid ?? '(null)';
        $mnoKey = $mno ?? '(null)';

        // Facturation normale : pricepointId 63980, mnoDeliveryCode DELIVERED, totalCharged > 0
        if ((string)$ppid === (string)$billingPpid && $mno === 'DELIVERED' && $charge > 0) {
            $normale++;
        } else {
            $autres++;
            if ($charge > 0) {
                $autresAvecCharge++;
            }
            if (!isset($byPpid[$ppidKey])) {
                $byPpid[$ppidKey] = 0;
            }
            $byPpid[$ppidKey]++;
            if (!isset($byMno[$mnoKey])) {
                $byMno[$mnoKey] = 0;
            }
            $byMno[$mnoKey]++;
            $keyCharged = $chargedPos ? 'totalCharged>0' : 'totalCharged=0';
            if (!isset($byChargedPos[$keyCharged])) {
                $byChargedPos[$keyCharged] = 0;
            }
            $byChargedPos[$keyCharged]++;
            $keyPpidMno = $ppidKey . '|' . $mnoKey . '|' . ($chargedPos ? 'charged>0' : 'charged=0');
            if (!isset($byPpidMnoCharged[$keyPpidMno])) {
                $byPpidMnoCharged[$keyPpidMno] = 0;
            }
            $byPpidMnoCharged[$keyPpidMno]++;
        }
    }
    echo "  Traité jusqu'à id {$lastId}...\n";
}

echo "\n";
echo "1) VOLUMÉTRIE DÉCEMBRE 2025 (transactions TIMWE uniquement)\n";
echo "   Total transactions Timwe (RENEWED_NOTIF / CHARGE_DELIVERED) : " . number_format($totalTimwe) . "\n";
echo "   Sans result ou result vide                                   : " . number_format($sansResult) . "\n";
echo "   Result JSON invalide                                         : " . number_format($resultInvalid) . "\n";
echo "   Avec result valide                                          : " . number_format($normale + $autres) . "\n";
echo "\n";

echo "2) FACTURATION NORMALE (pricepointId={$billingPpid}, mnoDeliveryCode=DELIVERED, totalCharged>0)\n";
echo "   Nombre : " . number_format($normale) . "\n";
echo "\n";

echo "3) FACTURATIONS AUTRES (tout le reste parmi les réponses Timwe)\n";
echo "   Nombre total « autres » (toutes réponses hors normale)     : " . number_format($autres) . "\n";
echo "   Dont « autres » avec totalCharged > 0 (facturables autres) : " . number_format($autresAvecCharge) . "\n";
echo "   (Comparer ces effectifs au chiffre envoyé par l'intégrateur pour « facturations autres »)\n";
echo "\n";

echo "4) RÉPARTITION DES « AUTRES » PAR pricepointId\n";
arsort($byPpid);
foreach ($byPpid as $ppid => $cnt) {
    $flag = ($referenceIntegrateur !== null && abs($cnt - $referenceIntegrateur) <= 150) ? "  <-- proche de " . number_format($referenceIntegrateur) : "";
    echo "   pricepointId " . $ppid . " : " . number_format($cnt) . $flag . "\n";
}
echo "\n";

echo "5) RÉPARTITION DES « AUTRES » PAR mnoDeliveryCode\n";
arsort($byMno);
foreach ($byMno as $mno => $cnt) {
    $flag = ($referenceIntegrateur !== null && abs($cnt - $referenceIntegrateur) <= 150) ? "  <-- proche de " . number_format($referenceIntegrateur) : "";
    echo "   mnoDeliveryCode " . $mno . " : " . number_format($cnt) . $flag . "\n";
}
echo "\n";

echo "6) RÉPARTITION DES « AUTRES » PAR (pricepointId | mnoDeliveryCode | totalCharged>0)\n";
arsort($byPpidMnoCharged);
$shown = 0;
$maxShow = 50;
foreach ($byPpidMnoCharged as $key => $cnt) {
    if ($shown >= $maxShow) {
        echo "   ... (" . (count($byPpidMnoCharged) - $maxShow) . " autres combinaisons)\n";
        break;
    }
    $flag = ($referenceIntegrateur !== null && abs($cnt - $referenceIntegrateur) <= 150) ? "  <-- proche de " . number_format($referenceIntegrateur) : "";
    echo "   " . $key . " : " . number_format($cnt) . $flag . "\n";
    $shown++;
}
echo "\n";

if ($referenceIntegrateur !== null) {
    echo "7) GROUPES PROCHES DU CHIFFRE INTÉGRATEUR (" . number_format($referenceIntegrateur) . " ± 150)\n";
    $near = [];
    foreach ($byPpid as $ppid => $cnt) {
        if (abs($cnt - $referenceIntegrateur) <= 150) {
            $near[] = ['key' => 'pricepointId=' . $ppid, 'count' => $cnt];
        }
    }
    foreach ($byPpidMnoCharged as $key => $cnt) {
        if (abs($cnt - $referenceIntegrateur) <= 150) {
            $near[] = ['key' => $key, 'count' => $cnt];
        }
    }
    foreach ($byMno as $mno => $cnt) {
        if (abs($cnt - $referenceIntegrateur) <= 150) {
            $near[] = ['key' => 'mnoDeliveryCode=' . $mno, 'count' => $cnt];
        }
    }
    if (empty($near)) {
        echo "   Aucun groupe dans l’intervalle [" . ($referenceIntegrateur - 150) . " – " . ($referenceIntegrateur + 150) . "].\n";
    } else {
        foreach ($near as $item) {
            echo "   " . $item['key'] . " : " . number_format($item['count']) . "\n";
        }
    }
    echo "\n";
}

echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "  Résumé : Normale = " . number_format($normale) . " | Autres (total) = " . number_format($autres) . " | Autres avec charge>0 = " . number_format($autresAvecCharge) . "\n";
echo "  Comparer ces effectifs au chiffre envoyé par l'intégrateur Timwe pour « facturations autres ».\n";
echo "  Pour signaler les groupes proches d'un chiffre : php analyze_timwe_december_autres.php 1073\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";
