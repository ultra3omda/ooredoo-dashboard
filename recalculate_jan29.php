<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\TimweStatsService;
use Carbon\Carbon;

$date = Carbon::parse('2026-01-29');

echo "=== Recalcul des stats Timwe pour le 29 janvier ===\n\n";

$service = new TimweStatsService();
$result = $service->calculateAndStoreStatsForDate($date);

if ($result) {
    echo "✓ Stats recalculées avec succès\n\n";
    
    // Lire les nouvelles valeurs
    $stat = \App\Models\TimweDailyStat::where('stat_date', $date->format('Y-m-d'))->first();
    
    if ($stat) {
        echo "Nouvelles valeurs dans timwe_daily_stats:\n";
        echo "- Nouveaux abonnements: {$stat->new_subscriptions}\n";
        echo "- Désabonnements: {$stat->unsubscriptions}\n";
        echo "- Abonnements actifs: {$stat->active_subscriptions}\n";
        echo "- FACTURATIONS: {$stat->total_billings}\n";
        echo "- Revenu TND: {$stat->revenue_tnd}\n";
        echo "- Taux facturation: {$stat->billing_rate}%\n";
        echo "- Total clients: {$stat->total_clients}\n";
    }
} else {
    echo "❌ Échec du recalcul\n";
}
