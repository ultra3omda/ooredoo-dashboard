<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\OoredooDailyStat;
use App\Models\TimweDailyStat;
use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "VÉRIFICATION DES DONNÉES OOREDOO ET TIMWE\n";
echo "========================================\n\n";

// 1. Compter les données Ooredoo
$ooredooCount = OoredooDailyStat::count();
echo "📊 Ooredoo Daily Stats: {$ooredooCount} enregistrements\n";

if ($ooredooCount > 0) {
    $firstOoredoo = OoredooDailyStat::orderBy('stat_date', 'asc')->first();
    $lastOoredoo = OoredooDailyStat::orderBy('stat_date', 'desc')->first();
    echo "   Première date: {$firstOoredoo->stat_date}\n";
    echo "   Dernière date: {$lastOoredoo->stat_date}\n";
    echo "   Exemple (dernière date):\n";
    echo "     - New subs: {$lastOoredoo->new_subscriptions}\n";
    echo "     - Total billings: {$lastOoredoo->total_billings}\n";
    echo "     - Revenue: {$lastOoredoo->revenue_tnd} TND\n";
}

echo "\n";

// 2. Compter les données Timwe
$timweCount = TimweDailyStat::count();
echo "📊 Timwe Daily Stats: {$timweCount} enregistrements\n";

if ($timweCount > 0) {
    $firstTimwe = TimweDailyStat::orderBy('stat_date', 'asc')->first();
    $lastTimwe = TimweDailyStat::orderBy('stat_date', 'desc')->first();
    echo "   Première date: {$firstTimwe->stat_date}\n";
    echo "   Dernière date: {$lastTimwe->stat_date}\n";
    echo "   Exemple (dernière date):\n";
    echo "     - New subs: {$lastTimwe->new_subscriptions}\n";
    echo "     - Total billings: {$lastTimwe->total_billings}\n";
    echo "     - Revenue: {$lastTimwe->revenue_tnd} TND\n";
}

echo "\n";

// 3. Vérifier les transactions Ooredoo brutes
$ooredooTransactions = DB::table('transactions_history')
    ->where('status', 'LIKE', '%OORE%')
    ->whereBetween('created_at', [now()->subDays(30), now()])
    ->count();

echo "📊 Transactions Ooredoo (30 derniers jours): {$ooredooTransactions}\n";

echo "\n";

// 4. Vérifier les offres Ooredoo dans abonnement_offres
$ooredooOffers = DB::table('abonnement_offres')
    ->where('abonnement_offres_nom', 'LIKE', '%Ooredoo%')
    ->orWhere('abonnement_offres_nom', 'LIKE', '%DGV%')
    ->orWhere('abonnement_offres_nom', 'LIKE', '%Club%')
    ->get();

echo "📊 Offres Ooredoo/DGV trouvées: {$ooredooOffers->count()}\n";
foreach ($ooredooOffers as $offer) {
    echo "   - ID: {$offer->abonnement_offres_id}, Nom: {$offer->abonnement_offres_nom}\n";
}

echo "\n========================================\n";
echo "RECOMMANDATIONS:\n";
echo "========================================\n\n";

if ($ooredooCount === 0) {
    echo "⚠️  PROBLÈME: La table ooredoo_daily_stats est VIDE!\n";
    echo "   Solution: Exécuter la commande:\n";
    echo "   php artisan ooredoo:calculate-historical --from=2025-01-01 --to=" . now()->format('Y-m-d') . "\n\n";
}

if ($timweCount === 0) {
    echo "⚠️  PROBLÈME: La table timwe_daily_stats est VIDE!\n";
    echo "   Solution: Exécuter la commande:\n";
    echo "   php artisan timwe:calculate-historical --from=2025-01-01 --to=" . now()->format('Y-m-d') . "\n\n";
}

if ($ooredooTransactions === 0) {
    echo "⚠️  AVERTISSEMENT: Aucune transaction Ooredoo dans les 30 derniers jours.\n";
    echo "   Cela pourrait être normal si le service n'est pas actif.\n\n";
}

echo "\n";

