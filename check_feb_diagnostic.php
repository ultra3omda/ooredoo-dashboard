<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DONNÉES DIAGNOSTIC TIMWE - FÉVRIER 2026 ===\n\n";

$data = DB::select("
    SELECT stat_date, total_transactions, total_billed, total_revenue_tnd
    FROM timwe_diagnostic_daily_summary
    WHERE stat_date >= '2026-02-01' AND stat_date <= '2026-02-09'
    ORDER BY stat_date DESC
");

if (empty($data)) {
    echo "❌ Aucune donnée trouvée pour février 2026\n";
} else {
    echo "📊 Données de février 2026:\n\n";
    foreach ($data as $row) {
        echo sprintf(
            "   %s: %s trans., %s facturés, %s TND\n",
            $row->stat_date,
            number_format($row->total_transactions),
            number_format($row->total_billed),
            number_format($row->total_revenue_tnd, 2)
        );
    }
}

echo "\n✅ Les données sont prêtes pour le diagnostic Timwe!\n";
