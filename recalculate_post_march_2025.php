<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\OoredooStatsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

echo "═══════════════════════════════════════════════════════════════════\n";
echo "   RECALCUL DES STATISTIQUES OOREDOO (AVRIL 2025 → AUJOURD'HUI)\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

$service = new OoredooStatsService();

// Date de début : 1er avril 2025 (après les données officielles de mars 2025)
$startDate = Carbon::parse('2025-04-01');
$endDate = Carbon::today();

echo "📅 Période de recalcul:\n";
echo "   Début: " . $startDate->format('Y-m-d') . "\n";
echo "   Fin: " . $endDate->format('Y-m-d') . "\n";
echo "   Jours à traiter: " . $startDate->diffInDays($endDate) + 1 . "\n\n";

echo "🔄 Suppression des anciennes données calculées...\n";
$deleted = DB::table('ooredoo_daily_stats')
    ->where('stat_date', '>=', $startDate->format('Y-m-d'))
    ->where('stat_date', '<=', $endDate->format('Y-m-d'))
    ->where('data_source', '!=', 'officiel_dgv')
    ->delete();

echo "   ✅ $deleted lignes supprimées\n\n";

echo "📊 Recalcul des statistiques...\n";
echo str_repeat('─', 70) . "\n";

$currentDate = $startDate->copy();
$processed = 0;
$errors = 0;

while ($currentDate <= $endDate) {
    try {
        echo "   " . $currentDate->format('Y-m-d') . " ... ";
        
        $service->calculateAndStoreStatsForDate($currentDate);
        
        echo "✅\n";
        $processed++;
        
    } catch (\Exception $e) {
        echo "❌ Erreur: " . $e->getMessage() . "\n";
        $errors++;
    }
    
    $currentDate->addDay();
}

echo str_repeat('─', 70) . "\n\n";

echo "📊 RÉSUMÉ:\n";
echo str_repeat('═', 70) . "\n";
echo "  Jours traités avec succès: $processed\n";
echo "  Erreurs: $errors\n";
echo str_repeat('═', 70) . "\n\n";

// Vérifier la répartition des sources de données
echo "🔍 VÉRIFICATION DES SOURCES DE DONNÉES:\n";
echo str_repeat('═', 70) . "\n";

$sources = DB::table('ooredoo_daily_stats')
    ->select('data_source', DB::raw('COUNT(*) as count'))
    ->groupBy('data_source')
    ->get();

foreach ($sources as $source) {
    echo "  " . strtoupper($source->data_source) . ": " . number_format($source->count) . " jours\n";
}

echo "\n✅ Recalcul terminé !\n";

