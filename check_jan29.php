<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$date = '2026-01-29';
$startDate = "$date 00:00:00";
$endDate = "$date 23:59:59";
$billingPpid = '63980';

echo "=== Analyse détaillée du 29 janvier 2026 ===\n\n";

$transactions = DB::table('transactions_history as th')
    ->join('client as c', 'th.client_id', '=', 'c.client_id')
    ->whereBetween('th.created_at', [$startDate, $endDate])
    ->where(function($q) {
        $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
          ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
    })
    ->whereNotNull('th.result')
    ->select('th.created_at', 'th.result', 'c.client_telephone')
    ->get();

echo "Total transactions Timwe ce jour: " . $transactions->count() . "\n\n";

$withCorrectPpid = 0;
$withoutCorrectPpid = 0;
$delivered = 0;
$charged = 0;
$fullyBilled = 0;

$ppidCounts = [];

foreach ($transactions as $transaction) {
    $result = json_decode($transaction->result, true);
    if (!is_array($result)) continue;
    
    $ppid = $result['pricepointId'] ?? 'NULL';
    $delivery = $result['mnoDeliveryCode'] ?? 'NULL';
    $totalCharged = isset($result['totalCharged']) ? (int)$result['totalCharged'] : 0;
    
    // Compter par pricepointId
    if (!isset($ppidCounts[$ppid])) {
        $ppidCounts[$ppid] = 0;
    }
    $ppidCounts[$ppid]++;
    
    if ((string)$ppid === (string)$billingPpid) {
        $withCorrectPpid++;
    } else {
        $withoutCorrectPpid++;
    }
    
    if ($delivery === 'DELIVERED') {
        $delivered++;
    }
    
    if ($totalCharged > 0) {
        $charged++;
    }
    
    if ((string)$ppid === (string)$billingPpid && $delivery === 'DELIVERED' && $totalCharged > 0) {
        $fullyBilled++;
    }
}

echo "=== Répartition des critères ===\n";
echo "Avec pricepointId = $billingPpid : $withCorrectPpid\n";
echo "Avec autre pricepointId : $withoutCorrectPpid\n";
echo "Avec DELIVERED : $delivered\n";
echo "Avec totalCharged > 0 : $charged\n";
echo "Facturés (3 critères) : $fullyBilled\n\n";

echo "=== Répartition par pricepointId ===\n";
arsort($ppidCounts);
foreach ($ppidCounts as $ppid => $count) {
    echo "pricepointId $ppid : $count transactions\n";
}

echo "\n=== CONCLUSION ===\n";
echo "Table timwe_daily_stats affiche: 602 facturations\n";
echo "Réalité (avec critères corrects): $fullyBilled facturations\n";
echo "Différence: " . (602 - $fullyBilled) . "\n";
