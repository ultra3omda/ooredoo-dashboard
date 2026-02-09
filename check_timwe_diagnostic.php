<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DIAGNOSTIC TIMWE - ÉTAT DES DONNÉES ===\n\n";

// Dernière date
$last = DB::selectOne("SELECT stat_date, total_transactions, total_billed, total_revenue_tnd FROM timwe_diagnostic_daily_summary ORDER BY stat_date DESC LIMIT 1");

if ($last) {
    echo "📅 Dernière date disponible: {$last->stat_date}\n";
    echo "   Transactions: " . number_format($last->total_transactions) . "\n";
    echo "   Facturés: " . number_format($last->total_billed) . "\n";
    echo "   Revenu: " . number_format($last->total_revenue_tnd, 2) . " TND\n\n";
} else {
    echo "⚠️  Aucune donnée\n\n";
}

// Dates récentes
echo "📊 Dernières 7 dates:\n\n";
$recent = DB::select("SELECT stat_date, total_transactions, total_billed FROM timwe_diagnostic_daily_summary ORDER BY stat_date DESC LIMIT 7");
foreach ($recent as $r) {
    echo "   {$r->stat_date}: {$r->total_transactions} trans., {$r->total_billed} facturés\n";
}

// Vérifier les données manquantes
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$hasYesterday = DB::selectOne("SELECT COUNT(*) as count FROM timwe_diagnostic_daily_summary WHERE stat_date = ?", [$yesterday]);

echo "\n🔍 Vérification:\n\n";
echo "Date d'aujourd'hui: {$today}\n";
echo "Hier: {$yesterday}\n";
echo "Données pour hier: " . ($hasYesterday->count > 0 ? "✅ OUI" : "❌ NON") . "\n";

if ($hasYesterday->count == 0) {
    echo "\n⚠️  PROBLÈME: Aucune donnée pour hier!\n";
    echo "   Il faut lancer: php artisan timwe:diagnostic-backfill --date={$yesterday}\n";
} else {
    echo "\n✅ Les données d'hier existent\n";
}

// Compter les jours manquants récents
echo "\n📅 Jours manquants dans les 30 derniers jours:\n\n";
$missingDays = DB::select("
    SELECT DATE_FORMAT(d.date, '%Y-%m-%d') as missing_date
    FROM (
        SELECT CURDATE() - INTERVAL (a.a + (10 * b.a)) DAY as date
        FROM 
            (SELECT 0 as a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) as a
            CROSS JOIN (SELECT 0 as a UNION ALL SELECT 1 UNION ALL SELECT 2) as b
    ) as d
    LEFT JOIN timwe_diagnostic_daily_summary t ON d.date = t.stat_date
    WHERE d.date >= CURDATE() - INTERVAL 30 DAY
        AND d.date < CURDATE()
        AND t.id IS NULL
    ORDER BY d.date DESC
");

if (count($missingDays) > 0) {
    echo "⚠️  " . count($missingDays) . " jour(s) manquant(s):\n";
    foreach ($missingDays as $day) {
        echo "   - {$day->missing_date}\n";
    }
    echo "\n💡 Pour remplir: php artisan timwe:diagnostic-backfill --start-date=<date> --end-date=<date>\n";
} else {
    echo "✅ Aucun jour manquant dans les 30 derniers jours\n";
}
