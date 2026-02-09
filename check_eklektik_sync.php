<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== VÉRIFICATION SYNCHRONISATION EKLEKTIK ===\n\n";

// Dernières syncs
echo "📅 Dernières synchronisations :\n\n";
$recent = DB::select("
    SELECT sync_id, started_at, status, records_processed, error_message
    FROM eklektik_sync_tracking
    ORDER BY started_at DESC
    LIMIT 10
");

if (empty($recent)) {
    echo "⚠️  Aucune synchronisation trouvée\n";
} else {
    foreach ($recent as $r) {
        $emoji = $r->status === 'success' ? '✅' : '❌';
        $processed = $r->records_processed ?? 0;
        $error = $r->error_message ? " (Erreur: " . substr($r->error_message, 0, 50) . "...)" : "";
        echo "{$emoji} {$r->started_at} | {$r->status} | {$processed} enr.{$error}\n";
    }
}

// Stats Eklektik
echo "\n📊 Données Eklektik en base :\n\n";
$stats = DB::selectOne("
    SELECT 
        COUNT(*) as total_rows,
        MIN(date) as min_date,
        MAX(date) as max_date,
        COUNT(DISTINCT date) as unique_dates
    FROM eklektik_stats_daily
");

echo "Total lignes: " . number_format($stats->total_rows) . "\n";
echo "Période: {$stats->min_date} → {$stats->max_date}\n";
echo "Dates uniques: {$stats->unique_dates}\n";

// Données récentes
echo "\n🕐 Données des 7 derniers jours :\n\n";
$recentData = DB::selectOne("
    SELECT 
        COUNT(*) as count, 
        COUNT(DISTINCT date) as dates
    FROM eklektik_stats_daily
    WHERE date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");

echo "Lignes: {$recentData->count}\n";
echo "Dates: {$recentData->dates}\n";

if ($recentData->count == 0) {
    echo "\n⚠️  ATTENTION: Aucune donnée récente ! La synchronisation ne fonctionne peut-être pas.\n";
} else {
    echo "\n✅ Des données récentes existent\n";
}

// Config du cron
echo "\n⚙️  Configuration du CRON :\n\n";
$config = DB::table('eklektik_cron_configs')->where('config_key', 'cron_enabled')->first();
if ($config) {
    echo "Statut: " . ($config->config_value === '1' ? '✅ ACTIVÉ' : '❌ DÉSACTIVÉ') . "\n";
} else {
    echo "⚠️  Pas de configuration trouvée\n";
}

$schedule = DB::table('eklektik_cron_configs')->where('config_key', 'cron_schedule')->first();
if ($schedule) {
    echo "Planification: {$schedule->config_value}\n";
}
