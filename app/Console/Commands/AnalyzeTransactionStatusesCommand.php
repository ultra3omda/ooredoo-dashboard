<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AnalyzeTransactionStatusesCommand extends Command
{
    protected $signature = 'transactions:analyze-statuses
                            {--with-count : Afficher le nombre de lignes par statut}
                            {--export= : Fichier CSV pour exporter (ex: statuses.csv)}';

    protected $description = 'Liste tous les statuts distincts de transactions_history pour différencier les 3 agrégateurs (Timwe, Eklektik, Ooredoo/DGV)';

    public function handle()
    {
        $withCount = $this->option('with-count');
        $exportPath = $this->option('export');

        if ($withCount) {
            $rows = DB::table('transactions_history')
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->orderBy('status')
                ->get();
            $countByStatus = $rows->pluck('cnt', 'status')->toArray();
            $statusList = array_keys($countByStatus);
        } else {
            $statusList = DB::table('transactions_history')->distinct()->orderBy('status')->pluck('status')->filter()->values()->toArray();
            $countByStatus = [];
        }

        $this->info('═══════════════════════════════════════════════════════════════════');
        $this->info('  STATUTS DISTINCTS — transactions_history');
        $this->info('═══════════════════════════════════════════════════════════════════');
        $this->newLine();

        $byPrefix = [];
        foreach ($statusList as $s) {
            $s = $s === null ? '(NULL)' : (string) $s;
            if ($s === '' || $s === '(NULL)') {
                $byPrefix['(vide/NULL)'] = $byPrefix['(vide/NULL)'] ?? [];
                $byPrefix['(vide/NULL)'][] = $s;
                continue;
            }
            $prefix = 'AUTRE';
            if (stripos($s, 'TT_') === 0) $prefix = 'TT_';
            elseif (stripos($s, 'ORANGE_') === 0) $prefix = 'ORANGE_';
            elseif (stripos($s, 'TIMWE_') === 0) $prefix = 'TIMWE_';
            elseif (stripos($s, 'OOREDOO_') === 0 || stripos($s, 'OORE') !== false) $prefix = 'OOREDOO_';
            elseif (stripos($s, 'TARAJI_') === 0) $prefix = 'TARAJI_';
            elseif (stripos($s, 'DELAYED_') === 0) $prefix = 'DELAYED_';
            elseif (stripos($s, 'EKLEKTIK') !== false || stripos($s, 'CLUB_') !== false) $prefix = 'EKLEKTIK/CLUB';
            elseif (stripos($s, 'DGV') !== false) $prefix = 'DGV';
            $byPrefix[$prefix] = $byPrefix[$prefix] ?? [];
            $byPrefix[$prefix][] = $s;
        }

        $tableRows = [];
        foreach ($byPrefix as $prefix => $list) {
            $this->info("► Préfixe / groupe : {$prefix}");
            $list = array_unique($list);
            sort($list);
            foreach ($list as $s) {
                $cnt = $withCount && isset($countByStatus[$s]) ? number_format($countByStatus[$s]) : '-';
                $tableRows[] = [$prefix, $s, $cnt];
                $this->line('   ' . $s . ($withCount ? "  ({$cnt} lignes)" : ''));
            }
            $this->newLine();
        }

        $this->info('Résumé : ' . count($statusList) . ' statut(s) distinct(s).');
        $this->newLine();
        $this->info('Référence : voir docs/TRANSACTIONS_HISTORY_STATUS_ANALYSIS.md pour le mapping agrégateurs (Timwe, Eklektik, Ooredoo/DGV).');

        if ($exportPath) {
            $fp = fopen($exportPath, 'w');
            if ($fp) {
                fputcsv($fp, ['prefix', 'status', $withCount ? 'count' : '']);
                foreach ($tableRows as $row) {
                    fputcsv($fp, $row);
                }
                fclose($fp);
                $this->info("Export écrit : {$exportPath}");
            }
        }

        return 0;
    }
}
