<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "📅 Données synchronisées (31/01 au 08/02) :\n\n";

$dates = DB::select("
    SELECT date, COUNT(*) as count, ROUND(SUM(revenu_ttc_tnd), 2) as revenue
    FROM eklektik_stats_daily
    WHERE date BETWEEN '2026-01-31' AND '2026-02-08'
    GROUP BY date
    ORDER BY date
");

foreach ($dates as $d) {
    echo "   {$d->date} : {$d->count} enr. | " . number_format($d->revenue, 2) . " TND\n";
}

echo "\n📊 Résumé :\n\n";
$total = DB::selectOne("
    SELECT 
        COUNT(*) as total_records,
        COUNT(DISTINCT date) as total_days,
        ROUND(SUM(revenu_ttc_tnd), 2) as total_revenue
    FROM eklektik_stats_daily
    WHERE date BETWEEN '2026-01-31' AND '2026-02-08'
");

echo "Total enregistrements : " . $total->total_records . "\n";
echo "Total jours : " . $total->total_days . " / 9 jours attendus\n";
echo "Revenu total : " . number_format($total->total_revenue, 2) . " TND\n";

if ($total->total_days == 9) {
    echo "\n✅ Toutes les données sont synchronisées !\n";
} else {
    echo "\n⚠️  Il manque " . (9 - $total->total_days) . " jour(s)\n";
}
