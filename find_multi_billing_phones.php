<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$startDate = '2026-01-23 00:00:00';
$endDate = '2026-01-30 23:59:59';
$billingPpid = '63980';

echo "=== Recherche de numéros facturés plusieurs fois ===\n\n";

$transactions = DB::table('transactions_history as th')
    ->join('client as c', 'th.client_id', '=', 'c.client_id')
    ->whereBetween('th.created_at', [$startDate, $endDate])
    ->where(function($q) {
        $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
          ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
    })
    ->whereNotNull('th.result')
    ->select('th.created_at', 'th.result', 'c.client_telephone', 'th.transaction_history_id')
    ->get();

$phonesByDate = [];
foreach ($transactions as $transaction) {
    $result = json_decode($transaction->result, true);
    if (!is_array($result)) continue;
    
    $ppid = $result['pricepointId'] ?? null;
    $delivery = $result['mnoDeliveryCode'] ?? null;
    $totalCharged = isset($result['totalCharged']) ? (int)$result['totalCharged'] : 0;
    
    if ((string)$ppid === (string)$billingPpid && $delivery === 'DELIVERED' && $totalCharged > 0) {
        $phone = $transaction->client_telephone;
        $date = substr($transaction->created_at, 0, 10);
        
        if (!isset($phonesByDate[$phone])) {
            $phonesByDate[$phone] = [];
        }
        if (!isset($phonesByDate[$phone][$date])) {
            $phonesByDate[$phone][$date] = 0;
        }
        $phonesByDate[$phone][$date]++;
    }
}

// Trouver les numéros facturés sur plusieurs jours
$multiDayPhones = [];
foreach ($phonesByDate as $phone => $dates) {
    if (count($dates) > 1) {
        $multiDayPhones[$phone] = $dates;
    }
}

echo "Nombre total de numéros uniques facturés: " . count($phonesByDate) . "\n";
echo "Nombre de numéros facturés sur plusieurs jours: " . count($multiDayPhones) . "\n\n";

if (count($multiDayPhones) > 0) {
    echo "=== Exemples de numéros facturés plusieurs fois ===\n\n";
    $count = 0;
    foreach ($multiDayPhones as $phone => $dates) {
        if ($count >= 5) break;
        echo "Numéro: $phone\n";
        echo "Facturé sur " . count($dates) . " jours différents:\n";
        $totalTransactions = 0;
        foreach ($dates as $date => $transactions) {
            echo "  - $date : $transactions transaction(s)\n";
            $totalTransactions += $transactions;
        }
        echo "  TOTAL: $totalTransactions transactions\n";
        echo "\n";
        $count++;
    }
    
    // Calculer la somme comme le Dashboard
    $sumByDay = 0;
    foreach ($phonesByDate as $phone => $dates) {
        $sumByDay += count($dates); // Chaque jour où le numéro est facturé compte pour 1
    }
    echo "\n=== Comparaison des méthodes de comptage ===\n";
    echo "Méthode Dashboard (somme des facturations journalières): $sumByDay\n";
    echo "Méthode Diagnostic (numéros uniques sur la période): " . count($phonesByDate) . "\n";
    echo "Différence: " . ($sumByDay - count($phonesByDate)) . "\n";
}
