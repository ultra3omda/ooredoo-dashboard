<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$startDate = '2026-01-23 00:00:00';
$endDate = '2026-01-30 23:59:59';
$billingPpid = '63980';

echo "=== Recherche des numéros facturés plusieurs fois ===\n\n";

$transactions = DB::table('transactions_history as th')
    ->join('client as c', 'th.client_id', '=', 'c.client_id')
    ->whereBetween('th.created_at', [$startDate, $endDate])
    ->where(function($q) {
        $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
          ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
    })
    ->whereNotNull('th.result')
    ->select('th.created_at', 'th.result', 'c.client_telephone', 'th.transaction_history_id', 'th.status')
    ->orderBy('c.client_telephone')
    ->orderBy('th.created_at')
    ->get();

$phonesByDate = [];
$phoneTransactions = [];

foreach ($transactions as $transaction) {
    $result = json_decode($transaction->result, true);
    if (!is_array($result)) continue;
    
    $ppid = $result['pricepointId'] ?? null;
    $delivery = $result['mnoDeliveryCode'] ?? null;
    $totalCharged = isset($result['totalCharged']) ? (int)$result['totalCharged'] : 0;
    
    // Critères complets de facturation
    if ((string)$ppid === (string)$billingPpid && $delivery === 'DELIVERED' && $totalCharged > 0) {
        $phone = $transaction->client_telephone;
        $date = substr($transaction->created_at, 0, 10);
        $datetime = $transaction->created_at;
        
        if (!isset($phonesByDate[$phone])) {
            $phonesByDate[$phone] = [];
        }
        
        if (!isset($phonesByDate[$phone][$date])) {
            $phonesByDate[$phone][$date] = [];
        }
        
        $phonesByDate[$phone][$date][] = [
            'datetime' => $datetime,
            'transaction_id' => $transaction->transaction_history_id,
            'status' => $transaction->status,
            'totalCharged' => $totalCharged
        ];
    }
}

// Trouver les numéros facturés sur plusieurs jours
$multiDayPhones = [];
foreach ($phonesByDate as $phone => $dates) {
    if (count($dates) > 1) {
        $multiDayPhones[$phone] = $dates;
    }
}

echo "Total numéros uniques facturés sur la période: " . count($phonesByDate) . "\n";
echo "Numéros facturés sur plusieurs jours: " . count($multiDayPhones) . "\n\n";

if (count($multiDayPhones) > 0) {
    echo "=== DÉTAILS DES " . count($multiDayPhones) . " NUMÉROS FACTURÉS PLUSIEURS FOIS ===\n\n";
    
    $index = 1;
    foreach ($multiDayPhones as $phone => $dates) {
        echo "[$index] Numéro: $phone\n";
        echo "    Facturé sur " . count($dates) . " jours différents:\n";
        
        foreach ($dates as $date => $transactions) {
            echo "    \n";
            echo "    📅 $date (" . count($transactions) . " transaction(s)):\n";
            foreach ($transactions as $trans) {
                echo "        • {$trans['datetime']} - ID: {$trans['transaction_id']}\n";
                echo "          Status: {$trans['status']}\n";
                echo "          Montant: {$trans['totalCharged']} millimes\n";
            }
        }
        echo "\n";
        $index++;
    }
    
    // Vérification du calcul
    $sumUniqueByDay = 0;
    foreach ($phonesByDate as $phone => $dates) {
        $sumUniqueByDay += count($dates); // Chaque jour où le numéro apparaît
    }
    
    echo "=== VÉRIFICATION ===\n";
    echo "Numéros uniques sur la période: " . count($phonesByDate) . "\n";
    echo "Somme des facturations par jour: $sumUniqueByDay\n";
    echo "Différence (doit être " . count($multiDayPhones) . "): " . ($sumUniqueByDay - count($phonesByDate)) . "\n";
} else {
    echo "Aucun numéro facturé plusieurs fois trouvé.\n";
}
