<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$startDate = '2026-01-23 00:00:00';
$endDate = '2026-01-30 23:59:59';
$billingPpid = '63980';

echo "=== Comparaison des 3 méthodes de comptage ===\n\n";

// Méthode 1: Comptage direct dans TimweStatsService (par jour)
$dates = [
    '2026-01-23', '2026-01-24', '2026-01-25', '2026-01-26',
    '2026-01-27', '2026-01-28', '2026-01-29', '2026-01-30'
];

$totalByDay = 0;
foreach ($dates as $date) {
    $stat = \App\Models\TimweDailyStat::where('stat_date', $date)->first();
    if ($stat) {
        $totalByDay += $stat->total_billings;
    }
}

echo "Méthode 1 (TimweStatsService - somme par jour): $totalByDay\n";

// Méthode 2: Comptage par téléphone unique
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

$uniquePhones = [];
$uniquePhonesPerDay = [];

foreach ($transactions as $transaction) {
    $result = json_decode($transaction->result, true);
    if (!is_array($result)) continue;
    
    $ppid = $result['pricepointId'] ?? null;
    $delivery = $result['mnoDeliveryCode'] ?? null;
    $totalCharged = isset($result['totalCharged']) ? (int)$result['totalCharged'] : 0;
    
    if ((string)$ppid === (string)$billingPpid && $delivery === 'DELIVERED' && $totalCharged > 0) {
        $phone = trim($transaction->client_telephone);
        $date = substr($transaction->created_at, 0, 10);
        
        // Pour la méthode 2: numéros uniques sur toute la période
        if ($phone !== '') {
            $uniquePhones[$phone] = true;
        }
        
        // Pour calculer la somme par jour
        if (!isset($uniquePhonesPerDay[$date])) {
            $uniquePhonesPerDay[$date] = [];
        }
        if ($phone !== '') {
            $uniquePhonesPerDay[$date][$phone] = true;
        }
    }
}

$method2 = count($uniquePhones);
echo "Méthode 2 (Numéros uniques sur la période): $method2\n";

// Calculer la somme des uniques par jour
$sumPerDay = 0;
foreach ($uniquePhonesPerDay as $date => $phones) {
    $count = count($phones);
    $sumPerDay += $count;
    echo "  $date: $count numéros uniques\n";
}

echo "\nMéthode 3 (Somme des numéros uniques par jour): $sumPerDay\n";

// Méthode 4: Diagnostic (sans pricepointId)
$diagnosticCount = 0;
foreach ($transactions as $transaction) {
    $result = json_decode($transaction->result, true);
    if (!is_array($result)) continue;
    
    $delivery = $result['mnoDeliveryCode'] ?? null;
    $totalCharged = isset($result['totalCharged']) ? (int)$result['totalCharged'] : 0;
    
    if ($delivery === 'DELIVERED' && $totalCharged > 0) {
        $diagnosticCount++;
    }
}

echo "Méthode 4 (Diagnostic - transactions sans critère ppid): $diagnosticCount\n";

echo "\n=== CONCLUSION ===\n";
echo "Dashboard affiche: $totalByDay (méthode 1 ou 3)\n";
echo "Diagnostic devrait afficher: $method2 numéros uniques (méthode 2)\n";
echo "Mais actuellement affiche: 1299 (méthode 4 - mauvais critère)\n";
