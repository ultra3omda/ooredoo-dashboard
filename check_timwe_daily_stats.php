<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\TimweDailyStat;
use Carbon\Carbon;

$startDate = Carbon::parse('2026-01-23');
$endDate = Carbon::parse('2026-01-30');

echo "=== Vérification table timwe_daily_stats ===\n\n";

$stats = TimweDailyStat::getStatsForPeriod($startDate, $endDate);

if ($stats->isEmpty()) {
    echo "ATTENTION: Aucune donnée dans timwe_daily_stats pour cette période !\n";
    echo "Le Dashboard utilise probablement le calcul direct (DashboardService ligne 2075+)\n\n";
    
    echo "Vérifions ce que le calcul direct donne...\n\n";
    
    // Reproduire le calcul du Dashboard
    $billingPpid = env('TIMWE_BILLING_PPID', '63980');
    $startBound = $startDate->copy()->startOfDay();
    $endExclusive = $endDate->copy()->addDay()->startOfDay();
    
    // Récupérer les IDs des opérateurs Timwe
    $timweOperatorIds = DB::table('country_payments_methods')
        ->whereRaw("TRIM(country_payments_methods_name) LIKE ?", ['%timwe%'])
        ->pluck('country_payments_methods_id')
        ->toArray();
    
    echo "Opérateurs Timwe trouvés: " . implode(', ', $timweOperatorIds) . "\n\n";
    
    // Compter les clients uniques avec abonnements Timwe
    $totalTimweClientsQuery = DB::table('client_abonnement as ca')
        ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
        ->where(function($q) use ($startBound, $endExclusive) {
            $q->where(function($subQ) use ($startBound, $endExclusive) {
                $subQ->where('ca.client_abonnement_creation', '>=', $startBound)
                     ->where('ca.client_abonnement_creation', '<', $endExclusive);
            })
            ->orWhere(function($subQ) use ($endExclusive) {
                $subQ->where(function($activeQ) use ($endExclusive) {
                    $activeQ->whereNull('ca.client_abonnement_expiration')
                            ->orWhere('ca.client_abonnement_expiration', '>=', $endExclusive);
                });
            });
        })
        ->select('ca.client_id')
        ->distinct();
    
    $totalTimweClients = $totalTimweClientsQuery->count();
    $timweClientIds = $totalTimweClientsQuery->pluck('client_id')->toArray();
    
    echo "Total clients Timwe dans la période: $totalTimweClients\n\n";
    
    // Récupérer les transactions
    $transactions = DB::table('transactions_history as th')
        ->whereIn('th.client_id', $timweClientIds)
        ->where('th.created_at', '>=', $startBound)
        ->where('th.created_at', '<', $endExclusive)
        ->where(function($q) {
            $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
              ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
        })
        ->select('th.client_id', 'th.result')
        ->get();
    
    echo "Total transactions trouvées: " . $transactions->count() . "\n\n";
    
    // Compter les facturations (méthode Dashboard)
    $totalBillings = 0;
    foreach ($transactions as $transaction) {
        $result = json_decode($transaction->result, true);
        if (!is_array($result)) continue;
        
        $ppid = $result['pricepointId'] ?? null;
        $delivery = $result['mnoDeliveryCode'] ?? null;
        $totalCharged = isset($result['totalCharged']) ? (int)$result['totalCharged'] : 0;
        
        if ((string)$ppid === (string)$billingPpid && $delivery === 'DELIVERED' && $totalCharged > 0) {
            $totalBillings++;
        }
    }
    
    echo "=== RÉSULTAT CALCUL DASHBOARD ===\n";
    echo "Total facturations (méthode Dashboard): $totalBillings\n";
    
} else {
    echo "Données trouvées dans timwe_daily_stats:\n\n";
    $totalBillings = 0;
    foreach ($stats as $stat) {
        echo "Date: {$stat->stat_date} - Facturations: {$stat->total_billings}\n";
        $totalBillings += $stat->total_billings;
    }
    echo "\nSomme totale des facturations: $totalBillings\n";
}
