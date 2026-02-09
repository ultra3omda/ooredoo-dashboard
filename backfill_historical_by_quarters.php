#!/usr/bin/env php
<?php
/**
 * Script pour backfill historique ML par trimestres
 * Évite les timeouts MySQL sur connexion distante
 */

$quarters = [
    // 2021
    ['2021-04-08', '2021-06-30'],
    ['2021-07-01', '2021-09-30'],
    ['2021-10-01', '2021-12-31'],
    
    // 2022
    ['2022-01-01', '2022-03-31'],
    ['2022-04-01', '2022-06-30'],
    ['2022-07-01', '2022-09-30'],
    ['2022-10-01', '2022-12-31'],
    
    // 2023
    ['2023-01-01', '2023-03-31'],
    ['2023-04-01', '2023-06-30'],
    ['2023-07-01', '2023-09-30'],
    ['2023-10-01', '2023-12-31'],
    
    // 2024
    ['2024-01-01', '2024-03-31'],
    ['2024-04-01', '2024-06-30'],
    ['2024-07-01', '2024-09-30'],
    ['2024-10-01', '2024-12-31'],
    
    // 2025
    ['2025-01-01', '2025-03-31'],
    ['2025-04-01', '2025-06-30'],
    ['2025-07-01', '2025-09-30'],
    ['2025-10-01', '2025-12-31'],
    
    // 2026 (jusqu'au 05-02)
    ['2026-01-01', '2026-02-05'],
];

$totalStart = microtime(true);
$totalProcessed = 0;
$failed = [];

foreach ($quarters as $index => $period) {
    [$start, $end] = $period;
    $quarter = $index + 1;
    
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "📅 Trimestre {$quarter}/19 : {$start} → {$end}\n";
    echo str_repeat("=", 70) . "\n\n";
    
    $cmd = "php artisan ml:build-historical-features " .
           "--start-date={$start} --end-date={$end} " .
           "--chunk=500 --batch-dates=30";
    
    $startTime = microtime(true);
    $returnCode = 0;
    
    passthru($cmd, $returnCode);
    
    $elapsed = round(microtime(true) - $startTime, 2);
    
    if ($returnCode === 0) {
        echo "\n✅ Trimestre {$quarter} terminé en {$elapsed}s\n";
        $totalProcessed++;
    } else {
        echo "\n❌ Trimestre {$quarter} échoué (code: {$returnCode})\n";
        $failed[] = "{$start} → {$end}";
    }
    
    // Pause entre chaque trimestre (laisser la connexion se reposer)
    if ($quarter < 19) {
        echo "⏸️  Pause 5 secondes...\n";
        sleep(5);
    }
}

$totalTime = round(microtime(true) - $totalStart, 2);

echo "\n" . str_repeat("=", 70) . "\n";
echo "🎉 BACKFILL HISTORIQUE TERMINÉ !\n";
echo str_repeat("=", 70) . "\n";
echo "📊 Trimestres traités : {$totalProcessed}/19\n";
echo "⏱️  Temps total : {$totalTime}s (" . round($totalTime / 60, 2) . " min)\n";

if (!empty($failed)) {
    echo "\n⚠️  Trimestres échoués (" . count($failed) . ") :\n";
    foreach ($failed as $period) {
        echo "   - {$period}\n";
    }
    echo "\n💡 Relancez manuellement les trimestres échoués\n";
}

echo "\n✅ Pour vérifier : php artisan tinker\n";
echo "   >>> DB::table('ml_client_features')->distinct('calculation_date')->count('calculation_date')\n";
echo "\n";
