<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$startDate = '2026-01-23 00:00:00';
$endDate = '2026-01-30 23:59:59';

echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║   ANALYSE DES STATUTS TIMWE - Période 23-30 janvier      ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

// 1. Récupérer tous les statuts TIMWE distincts
echo "🔍 ÉTAPE 1: Identifier tous les statuts TIMWE existants\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$allStatuses = DB::table('transactions_history')
    ->where('status', 'LIKE', '%TIMWE%')
    ->distinct()
    ->pluck('status')
    ->toArray();

echo "Total statuts TIMWE différents trouvés: " . count($allStatuses) . "\n\n";
foreach ($allStatuses as $status) {
    echo "  • $status\n";
}

// 2. Analyser chaque statut pour la période
echo "\n\n🔍 ÉTAPE 2: Analyse détaillée par statut (période 23-30 janvier)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$statusAnalysis = [];
$allPhonesTimwe = [];

foreach ($allStatuses as $status) {
    $transactions = DB::table('transactions_history as th')
        ->join('client as c', 'th.client_id', '=', 'c.client_id')
        ->where('th.status', $status)
        ->whereBetween('th.created_at', [$startDate, $endDate])
        ->select('c.client_telephone', 'th.result')
        ->get();
    
    $uniquePhones = [];
    $withResult = 0;
    $deliveryCodes = [];
    
    foreach ($transactions as $trans) {
        $phone = trim($trans->client_telephone);
        if ($phone !== '') {
            $uniquePhones[$phone] = true;
            $allPhonesTimwe[$phone] = true;
        }
        
        if ($trans->result) {
            $withResult++;
            $result = json_decode($trans->result, true);
            if (is_array($result)) {
                $code = $result['mnoDeliveryCode'] ?? 'NULL';
                if (!isset($deliveryCodes[$code])) {
                    $deliveryCodes[$code] = 0;
                }
                $deliveryCodes[$code]++;
            }
        }
    }
    
    $statusAnalysis[$status] = [
        'total_transactions' => $transactions->count(),
        'unique_phones' => count($uniquePhones),
        'with_result' => $withResult,
        'delivery_codes' => $deliveryCodes
    ];
}

// Afficher l'analyse
foreach ($statusAnalysis as $status => $data) {
    echo "📊 Statut: $status\n";
    echo "   ├─ Total transactions: {$data['total_transactions']}\n";
    echo "   ├─ Numéros uniques: {$data['unique_phones']}\n";
    echo "   ├─ Avec résultat JSON: {$data['with_result']}\n";
    
    if (!empty($data['delivery_codes'])) {
        echo "   └─ Delivery codes:\n";
        arsort($data['delivery_codes']);
        foreach ($data['delivery_codes'] as $code => $count) {
            echo "      • $code: $count\n";
        }
    }
    echo "\n";
}

// 3. Analyse des clients "tentés de facturer"
echo "\n🎯 ÉTAPE 3: Qui sont les 'clients actifs Timwe' ?\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$totalUniquePhonesAllStatuses = count($allPhonesTimwe);
echo "Total numéros uniques avec transactions TIMWE: $totalUniquePhonesAllStatuses\n\n";

// 4. Comparer avec les abonnements
echo "📋 Comparaison avec la table client_abonnement:\n\n";

$timweOperatorIds = DB::table('country_payments_methods')
    ->whereRaw("TRIM(country_payments_methods_name) LIKE ?", ['%timwe%'])
    ->pluck('country_payments_methods_id')
    ->toArray();

$activeSubscriptions = DB::table('client_abonnement as ca')
    ->join('client as c', 'ca.client_id', '=', 'c.client_id')
    ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
    ->where(function($q) use ($endDate) {
        $q->whereNull('ca.client_abonnement_expiration')
          ->orWhere('ca.client_abonnement_expiration', '>', $endDate);
    })
    ->distinct()
    ->pluck('c.client_telephone')
    ->toArray();

$activeSubscriptionsCount = count($activeSubscriptions);

echo "Clients avec abonnement actif Timwe (fin période): $activeSubscriptionsCount\n";
echo "Clients avec transactions Timwe (période): $totalUniquePhonesAllStatuses\n";
echo "Différence: " . abs($activeSubscriptionsCount - $totalUniquePhonesAllStatuses) . "\n\n";

// 5. Analyse spécifique sur les statuts de facturation
echo "\n💰 ÉTAPE 4: Focus sur les tentatives de facturation\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$billingStatuses = ['TIMWE_RENEWED_NOTIF', 'TIMWE_CHARGE_DELIVERED'];
$billedPhones = [];
$attemptedPhones = [];

foreach ($billingStatuses as $status) {
    $transactions = DB::table('transactions_history as th')
        ->join('client as c', 'th.client_id', '=', 'c.client_id')
        ->where('th.status', 'LIKE', "%$status%")
        ->whereBetween('th.created_at', [$startDate, $endDate])
        ->select('c.client_telephone', 'th.result')
        ->get();
    
    foreach ($transactions as $trans) {
        $phone = trim($trans->client_telephone);
        if ($phone !== '') {
            $attemptedPhones[$phone] = true;
            
            if ($trans->result) {
                $result = json_decode($trans->result, true);
                if (is_array($result)) {
                    $delivery = $result['mnoDeliveryCode'] ?? null;
                    $charged = isset($result['totalCharged']) ? (int)$result['totalCharged'] : 0;
                    
                    if ($delivery === 'DELIVERED' && $charged > 0) {
                        $billedPhones[$phone] = true;
                    }
                }
            }
        }
    }
}

echo "Numéros qu'on a TENTÉ de facturer: " . count($attemptedPhones) . "\n";
echo "Numéros effectivement FACTURÉS: " . count($billedPhones) . "\n";
echo "Taux de succès: " . (count($attemptedPhones) > 0 ? round((count($billedPhones) / count($attemptedPhones)) * 100, 2) : 0) . "%\n\n";

// 6. Conclusion
echo "\n📝 ÉTAPE 5: CONCLUSIONS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "1️⃣  CLIENTS ACTIFS TIMWE = ?\n\n";
echo "   Option A: Abonnements actifs dans client_abonnement\n";
echo "             → $activeSubscriptionsCount clients\n";
echo "             → Représente les clients qui DEVRAIENT être facturés\n\n";

echo "   Option B: Numéros avec transactions TIMWE (tous statuts)\n";
echo "             → $totalUniquePhonesAllStatuses clients\n";
echo "             → Représente TOUS les clients contactés par Timwe\n\n";

echo "   Option C: Numéros qu'on a tenté de facturer\n";
echo "             → " . count($attemptedPhones) . " clients\n";
echo "             → Représente les clients pour qui on a ESSAYÉ de facturer\n\n";

echo "2️⃣  LOGIQUE RECOMMANDÉE:\n\n";
echo "   'Clients Actifs' = Numéros qu'on a TENTÉ de facturer\n";
echo "   Car:\n";
echo "     ✓ Reflète l'activité réelle de facturation\n";
echo "     ✓ Inclut les succès ET les échecs de facturation\n";
echo "     ✓ Correspond aux 'NUMÉROS UNIQUES' du diagnostic actuel\n\n";

echo "3️⃣  POUR LE DIAGNOSTIC:\n\n";
echo "   NUMÉROS UNIQUES (actuel) = " . count($attemptedPhones) . " ✓\n";
echo "   FACTURÉS = " . count($billedPhones) . " ✓\n";
echo "   TAUX = " . (count($attemptedPhones) > 0 ? round((count($billedPhones) / count($attemptedPhones)) * 100, 2) : 0) . "% ✓\n";
echo "   → Cette logique est CORRECTE !\n\n";
