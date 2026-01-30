<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\TimweStatsService;
use Carbon\Carbon;

$startDate = Carbon::parse('2026-01-23');
$endDate = Carbon::parse('2026-01-30');

echo "=== Recalcul des stats Timwe pour la période ===\n";
echo "Du {$startDate->format('Y-m-d')} au {$endDate->format('Y-m-d')}\n\n";

$service = new TimweStatsService();
$totalBillings = 0;

$currentDate = $startDate->copy();
while ($currentDate->lte($endDate)) {
    echo "Calcul pour {$currentDate->format('Y-m-d')}... ";
    
    $result = $service->calculateAndStoreStatsForDate($currentDate);
    
    if ($result) {
        $stat = \App\Models\TimweDailyStat::where('stat_date', $currentDate->format('Y-m-d'))->first();
        if ($stat) {
            echo "✓ {$stat->total_billings} facturations\n";
            $totalBillings += $stat->total_billings;
        }
    } else {
        echo "❌ Échec\n";
    }
    
    $currentDate->addDay();
}

echo "\n=== RÉSULTAT FINAL ===\n";
echo "Somme des facturations quotidiennes: $totalBillings\n";
echo "\nLe Dashboard devrait maintenant afficher $totalBillings au lieu de 1877\n";
