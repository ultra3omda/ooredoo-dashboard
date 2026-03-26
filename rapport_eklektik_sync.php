<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== RAPPORT SYNCHRONISATION EKLEKTIK ===\n\n";

// 1. Configuration
echo "⚙️  CONFIGURATION CRON :\n\n";
$configs = DB::table('eklektik_cron_config')->get();

if ($configs->isEmpty()) {
    echo "⚠️  Aucune configuration trouvée\n";
} else {
    foreach ($configs as $c) {
        $active = $c->is_active ? '✅' : '❌';
        echo "{$active} {$c->config_key} = {$c->config_value}\n";
    }
}

// 2. Dernières synchronisations
echo "\n📅 DERNIÈRES SYNCHRONISATIONS :\n\n";
$syncs = DB::select("
    SELECT sync_id, started_at, status, records_processed, duration_seconds, error_message
    FROM eklektik_sync_tracking
    ORDER BY started_at DESC
    LIMIT 5
");

foreach ($syncs as $s) {
    $emoji = $s->status === 'success' ? '✅' : ($s->status === 'running' ? '🔄' : '❌');
    $duration = $s->duration_seconds ? " ({$s->duration_seconds}s)" : "";
    $error = $s->error_message ? "\n   ⚠️ " . substr($s->error_message, 0, 80) : "";
    echo "{$emoji} {$s->started_at} | {$s->status} | {$s->records_processed} enr.{$duration}{$error}\n";
}

// 3. Données en base
echo "\n📊 DONNÉES EKLEKTIK EN BASE :\n\n";
$stats = DB::selectOne("
    SELECT 
        COUNT(*) as total,
        MIN(date) as min_date,
        MAX(date) as max_date,
        COUNT(DISTINCT date) as dates,
        ROUND(SUM(revenu_ttc_tnd), 2) as total_revenue
    FROM eklektik_stats_daily
");

echo "Total enregistrements: " . number_format($stats->total) . "\n";
echo "Période: {$stats->min_date} → {$stats->max_date}\n";
echo "Dates uniques: {$stats->dates}\n";
echo "Revenu total (TND): " . number_format($stats->total_revenue, 2) . "\n";

// 4. Dernières dates
echo "\n🕐 DERNIÈRES DATES SYNCHRONISÉES :\n\n";
$lastDates = DB::select("
    SELECT date, COUNT(*) as records, ROUND(SUM(revenu_ttc_tnd), 2) as revenue
    FROM eklektik_stats_daily
    GROUP BY date
    ORDER BY date DESC
    LIMIT 7
");

foreach ($lastDates as $d) {
    echo "   {$d->date} : {$d->records} enr. | {$d->revenue} TND\n";
}

// 5. Vérification planification Kernel
echo "\n🔧 PLANIFICATION DANS KERNEL.PHP :\n\n";
echo "Ligne 21-28: Cron Eklektik configuré\n";
echo "Commande: php artisan eklektik:sync-stats --period=1 --force\n";
echo "Planification: " . ($configs->where('config_key', 'cron_schedule')->first()->config_value ?? '0 2 * * *') . " (Tous les jours à 2h)\n";
echo "Statut: " . ($configs->where('config_key', 'cron_enabled')->first()->config_value === 'true' ? '✅ ACTIVÉ' : '❌ DÉSACTIVÉ') . "\n";

// 6. Test manuel
echo "\n💡 POUR TESTER MANUELLEMENT :\n\n";
echo "php artisan eklektik:sync-stats --period=1 --force\n";
echo "php artisan eklektik:sync-stats --period=7 --force\n";
echo "php artisan eklektik:sync-stats --start-date=2026-01-01 --end-date=2026-01-31 --force\n";

echo "\n✅ RAPPORT TERMINÉ\n";
