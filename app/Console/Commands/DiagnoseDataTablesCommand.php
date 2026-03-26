<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiagnoseDataTablesCommand extends Command
{
    protected $signature = 'diagnose:data-tables
                            {--json : Sortie JSON pour scripts}';

    protected $description = 'Vérifie l’existence et le contenu des tables Timwe, Ooredoo/DGV et Eklektik';

    /** Tables critiques pour le dashboard (source de données) */
    private const CRITICAL_TABLES = [
        'timwe_daily_stats' => [
            'date_column' => 'stat_date',
            'label' => 'Timwe (stats quotidiennes)',
            'populate' => 'php artisan timwe:calculate-daily [--date=YYYY-MM-DD] ou timwe:calculate-historical',
        ],
        'ooredoo_daily_stats' => [
            'date_column' => 'stat_date',
            'label' => 'Ooredoo/DGV (stats quotidiennes)',
            'populate' => 'php artisan ooredoo:update-daily-stats [--date=YYYY-MM-DD] ou ooredoo:reimport-all',
        ],
        'eklektik_stats_daily' => [
            'date_column' => 'date',
            'label' => 'Eklektik (stats quotidiennes)',
            'populate' => 'php artisan eklektik:sync-stats [--period=30] ou via interface cron',
        ],
    ];

    /** Tables Eklektik annexes (config / tracking) */
    private const EKLEKTIK_EXTRA = [
        'eklektik_kpis_cache',
        'eklektik_sync_tracking',
        'eklektik_cron_config',
        'eklektik_stats_dailies',
        'eklektik_transactions_tracking',
        'eklektik_notifications_tracking',
    ];

    public function handle(): int
    {
        $this->info('═══════════════════════════════════════════════════════════════════');
        $this->info('   DIAGNOSTIC – Tables Timwe / Ooredoo-DGV / Eklektik');
        $this->info('═══════════════════════════════════════════════════════════════════');
        $this->newLine();

        $results = [];
        $hasMissing = false;
        $hasEmpty = false;

        foreach (self::CRITICAL_TABLES as $table => $config) {
            $exists = Schema::hasTable($table);
            $result = [
                'table' => $table,
                'label' => $config['label'],
                'exists' => $exists,
                'count' => 0,
                'min_date' => null,
                'max_date' => null,
                'populate_cmd' => $config['populate'],
            ];

            if ($exists) {
                try {
                    $result['count'] = DB::table($table)->count();
                    if ($result['count'] > 0) {
                        $dateCol = $config['date_column'];
                        $result['min_date'] = DB::table($table)->min($dateCol);
                        $result['max_date'] = DB::table($table)->max($dateCol);
                    } else {
                        $hasEmpty = true;
                    }
                } catch (\Throwable $e) {
                    $result['error'] = $e->getMessage();
                }
            } else {
                $hasMissing = true;
            }

            $results[$table] = $result;
            $this->printTableResult($result);
        }

        $this->newLine();
        $this->info('--- Tables Eklektik annexes ---');
        foreach (self::EKLEKTIK_EXTRA as $table) {
            $exists = Schema::hasTable($table);
            $count = $exists ? DB::table($table)->count() : 0;
            $results[$table] = ['table' => $table, 'exists' => $exists, 'count' => $count];
            $this->line(sprintf(
                '  %s %s (%s)',
                $exists ? '✓' : '✗',
                $table,
                $exists ? $count . ' ligne(s)' : 'ABSENTE'
            ));
        }

        $this->newLine();
        if ($hasMissing || $hasEmpty) {
            $this->warn('RÉSUMÉ : Certaines tables sont manquantes ou vides.');
            $this->printRecoveryInstructions($results);
        } else {
            $this->info('RÉSUMÉ : Toutes les tables critiques existent et contiennent des données.');
        }

        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return 0;
    }

    private function printTableResult(array $result): void
    {
        $table = $result['table'];
        $label = $result['label'];

        if (!$result['exists']) {
            $this->error("  ✗ {$label} ({$table}) : TABLE ABSENTE");
            return;
        }

        $count = $result['count'];
        if ($count === 0) {
            $this->warn("  ○ {$label} ({$table}) : 0 ligne – table vide");
            return;
        }

        $min = $result['min_date'] ?? '-';
        $max = $result['max_date'] ?? '-';
        $this->info("  ✓ {$label} ({$table}) : " . number_format($count) . " ligne(s) | {$min} → {$max}");
    }

    private function printRecoveryInstructions(array $results): void
    {
        $this->newLine();
        $this->info('─── Comment récupérer ───');
        $this->line('1. Si une table est ABSENTE : les migrations ne l’ont pas créée ou elle a été supprimée (ex. migrate:fresh / rollback).');
        $this->line('   → Recréer les tables :');
        $this->line('     php artisan migrate');
        $this->line('   (Pour ne lancer que les migrations d’une source, ex. Timwe/Ooredoo/Eklektik, utilisez --path=database/migrations avec le fichier concerné.)');
        $this->newLine();
        $this->line('2. Si une table existe mais est VIDE : recalculer / réimporter les données :');
        foreach (self::CRITICAL_TABLES as $table => $config) {
            $r = $results[$table] ?? [];
            if (($r['exists'] ?? false) && ($r['count'] ?? 0) === 0) {
                $this->line('   • ' . $config['label'] . ' :');
                $this->line('     ' . $config['populate']);
            }
        }
        $this->newLine();
        $this->line('3. Les migrations ML (2026_01_30_200000_create_ml_tables.php) ne suppriment QUE les tables ml_* (ml_client_features, ml_predictions, etc.).');
        $this->line('   Elles ne touchent PAS à timwe_daily_stats, ooredoo_daily_stats ni eklektik_stats_daily.');
        $this->line('   Si ces tables ont disparu, la cause est ailleurs (migrate:fresh, rollback, ou autre migration).');
    }
}
