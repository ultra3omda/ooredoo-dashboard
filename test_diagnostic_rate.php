<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$startDate = '2026-01-01';
$endDate = '2026-01-15';

echo "=== VÉRIFICATION DONNÉES DIAGNOSTIC ===\n\n";

// Summary
$summary = DB::table('timwe_diagnostic_daily_summary')
    ->whereBetween('stat_date', [$startDate, $endDate])
    ->selectRaw('
        COALESCE(SUM(total_transactions), 0) as total_transactions,
        COALESCE(SUM(total_billed), 0) as total_billed,
        COALESCE(SUM(total_revenue_tnd), 0) as total_revenue_tnd
    ')
    ->first();

// Unique phones
$uniquePhones = DB::table('timwe_diagnostic_daily_phone')
    ->whereBetween('stat_date', [$startDate, $endDate])
    ->selectRaw('COUNT(DISTINCT client_telephone) as count')
    ->value('count');

$totalBilled = (int) ($summary->total_billed ?? 0);
$totalTransactions = (int) ($summary->total_transactions ?? 0);
$totalRevenue = (float) ($summary->total_revenue_tnd ?? 0);

$rate = $uniquePhones > 0 ? round(($totalBilled / $uniquePhones) * 100, 2) : 0;

echo "Période: {$startDate} → {$endDate}\n\n";
echo "Total transactions: {$totalTransactions}\n";
echo "Total facturé (billed): {$totalBilled}\n";
echo "Numéros uniques: {$uniquePhones}\n";
echo "Revenu TND: {$totalRevenue}\n\n";
echo "TAUX DE FACTURATION: {$rate}%\n";
echo "Formule: ({$totalBilled} / {$uniquePhones}) × 100 = {$rate}%\n";
