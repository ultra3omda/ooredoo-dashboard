<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Analyse un grand échantillon de ml_client_features pour vérifier que l'extraction
 * multi-opérateur (ml:extract-multi) remplit correctement les colonnes succès/activité.
 */
class InspectMLFeaturesSampleCommand extends Command
{
    protected $signature = 'ml:inspect-sample
                            {--sample=50000 : Nombre de lignes à échantillonner (max)}
                            {--date= : Limiter à une date (YYYY-MM-DD)}
                            {--last-date : Utiliser uniquement la dernière date présente en base}';

    protected $description = 'Analyse un échantillon de ml_client_features : colonnes succès/activité et critère cible (extraction correcte ou non)';

    public function handle(): int
    {
        $this->info('══════════════════════════════════════════════════════════════════');
        $this->info('  INSPECTION ÉCHANTILLON ml_client_features (succès / activité)');
        $this->info('══════════════════════════════════════════════════════════════════');
        $this->newLine();

        $total = DB::table('ml_client_features')->count();
        if ($total === 0) {
            $this->warn('La table est vide. Lancez : php artisan ml:extract-multi --start-date=... --end-date=...');
            return Command::FAILURE;
        }

        $sampleSize = (int) $this->option('sample');
        $dateStr = $this->option('date');
        $lastDateOnly = $this->option('last-date');

        $cols = Schema::getColumnListing('ml_client_features');
        $keyCols = [
            'timwe_success_rate' => 'Taux succès Timwe',
            'timwe_total_attempts' => 'Tentatives Timwe',
            'timwe_total_successes' => 'Succès Timwe',
            'timwe_has_activity' => 'Activité Timwe (0/1)',
            'eklektik_success_rate' => 'Taux succès Eklektik',
            'eklektik_total_attempts' => 'Tentatives Eklektik',
            'eklektik_has_activity' => 'Activité Eklektik (0/1)',
            'ooredoo_success_rate' => 'Taux succès Ooredoo',
            'ooredoo_total_attempts' => 'Tentatives Ooredoo',
            'ooredoo_has_activity' => 'Activité Ooredoo (0/1)',
        ];

        $query = DB::table('ml_client_features');

        if ($dateStr) {
            $query->where('calculation_date', $dateStr);
            $this->info("Filtre : date = {$dateStr}");
        } elseif ($lastDateOnly) {
            $maxDate = DB::table('ml_client_features')->max('calculation_date');
            $query->where('calculation_date', $maxDate);
            $this->info("Filtre : dernière date = {$maxDate}");
        }

        $totalInScope = $query->count();
        if ($totalInScope === 0) {
            $this->warn('Aucune ligne dans le périmètre (date ou dernière date).');
            return Command::FAILURE;
        }

        $useSample = $totalInScope > $sampleSize;
        $limit = $useSample ? $sampleSize : min($totalInScope, 100000);
        $query->inRandomOrder()->limit($limit);
        if ($useSample) {
            $this->info("Échantillon aléatoire : " . number_format($limit) . " lignes (sur " . number_format($totalInScope) . " dans le périmètre).");
        } else {
            $this->info("Périmètre : " . number_format($totalInScope) . " lignes.");
        }
        $this->newLine();

        $rows = $query->get();
        $n = $rows->count();

        // 1) Stats par colonne clé (non-null, >0, min/max pour taux)
        $this->info('── Colonnes succès / activité (extraction multi-opérateur) ──');
        $tableRows = [];
        foreach ($keyCols as $col => $label) {
            if (! in_array($col, $cols, true)) {
                $tableRows[] = [$label, $col, 'N/A', 'Colonne absente'];
                continue;
            }
            $nonNull = $rows->whereNotNull($col)->count();
            $nonZero = $rows->where($col, '>', 0)->count();
            $pctNonNull = $n > 0 ? round(100 * $nonNull / $n, 1) : 0;
            $pctNonZero = $n > 0 ? round(100 * $nonZero / $n, 1) : 0;
            $extra = '';
            if (str_ends_with($col, '_rate')) {
                $vals = $rows->pluck($col)->filter(fn ($v) => $v !== null && $v !== '');
                $numeric = $vals->map(fn ($v) => (float) $v);
                if ($numeric->isNotEmpty()) {
                    $extra = 'min=' . round($numeric->min(), 4) . ' max=' . round($numeric->max(), 4) . ' avg=' . round($numeric->avg(), 4);
                }
            }
            $tableRows[] = [$label, $col, "{$nonZero}/{$n} (>0)", "{$pctNonZero}% >0 | {$extra}"];
        }
        $this->table(['Libellé', 'Colonne', 'Lignes >0', 'Détail'], $tableRows);
        $this->newLine();

        // 2) Critère cible ML : au moins un opérateur avec (has_activity ET success_rate > 0.2)
        $this->info('── Critère cible entraînement (au moins 1 opérateur : activité + taux > 0.2) ──');
        $targetOk = 0;
        $timweOk = 0;
        $eklektikOk = 0;
        $ooredooOk = 0;
        foreach ($rows as $r) {
            $t = $this->safeRate($r, 'timwe_success_rate') > 0.2 && $this->safeActivity($r, 'timwe_has_activity');
            $e = $this->safeRate($r, 'eklektik_success_rate') > 0.2 && $this->safeActivity($r, 'eklektik_has_activity');
            $o = $this->safeRate($r, 'ooredoo_success_rate') > 0.2 && $this->safeActivity($r, 'ooredoo_has_activity');
            if ($t) $timweOk++;
            if ($e) $eklektikOk++;
            if ($o) $ooredooOk++;
            if ($t || $e || $o) $targetOk++;
        }
        $pctTarget = $n > 0 ? round(100 * $targetOk / $n, 2) : 0;
        $this->table(['Métrique', 'Valeur'], [
            ['Lignes avec cible = 1 (succès)', number_format($targetOk) . ' / ' . number_format($n) . " ({$pctTarget}%)"],
            ['  dont Timwe (rate>0.2 + activité)', number_format($timweOk)],
            ['  dont Eklektik (rate>0.2 + activité)', number_format($eklektikOk)],
            ['  dont Ooredoo (rate>0.2 + activité)', number_format($ooredooOk)],
        ]);
        if ($targetOk === 0) {
            $this->warn("Aucune ligne ne satisfait le critère cible → modèle avec une seule classe (AUC non défini). Vérifiez l'extraction ou assouplissez le critère.");
        }
        $this->newLine();

        // 3) Distribution des taux de succès (bucket)
        $this->info('── Distribution des taux de succès (échantillon) ──');
        $rates = ['timwe_success_rate', 'eklektik_success_rate', 'ooredoo_success_rate'];
        $distRows = [];
        foreach ($rates as $col) {
            if (! in_array($col, $cols, true)) {
                continue;
            }
            $vals = $rows->pluck($col)->map(fn ($v) => $v === null || $v === '' ? null : (float) $v);
            $nullOrZero = $vals->filter(fn ($v) => $v === null || $v === 0)->count();
            $between0_02 = $vals->filter(fn ($v) => $v !== null && $v > 0 && $v <= 0.2)->count();
            $between02_05 = $vals->filter(fn ($v) => $v !== null && $v > 0.2 && $v <= 0.5)->count();
            $between05_1 = $vals->filter(fn ($v) => $v !== null && $v > 0.5 && $v <= 1)->count();
            $distRows[] = [
                $col,
                number_format($nullOrZero),
                number_format($between0_02),
                number_format($between02_05),
                number_format($between05_1),
            ];
        }
        if (! empty($distRows)) {
            $this->table(['Colonne', 'NULL/0', ']0, 0.2]', ']0.2, 0.5]', ']0.5, 1]'], $distRows);
        }
        $this->newLine();

        // 4) Quelques lignes exemples (avec au moins une activité)
        $this->info('── Exemples de lignes (avec au moins une tentative > 0) ──');
        $withActivity = $rows->filter(function ($r) use ($cols) {
            $t = in_array('timwe_total_attempts', $cols) ? (int) ($r->timwe_total_attempts ?? 0) : 0;
            $e = in_array('eklektik_total_attempts', $cols) ? (int) ($r->eklektik_total_attempts ?? 0) : 0;
            $o = in_array('ooredoo_total_attempts', $cols) ? (int) ($r->ooredoo_total_attempts ?? 0) : 0;
            return $t > 0 || $e > 0 || $o > 0;
        })->take(5);
        if ($withActivity->isEmpty()) {
            $this->line('Aucune ligne avec tentative > 0 dans l\'échantillon.');
        } else {
            $exRows = [];
            foreach ($withActivity as $r) {
                $exRows[] = [
                    $r->client_id ?? '-',
                    $r->calculation_date ?? '-',
                    $this->val($r->timwe_success_rate),
                    $this->val($r->timwe_has_activity),
                    $this->val($r->eklektik_success_rate),
                    $this->val($r->eklektik_has_activity),
                    $this->val($r->ooredoo_success_rate),
                    $this->val($r->ooredoo_has_activity),
                ];
            }
            $this->table(['client_id', 'date', 'timwe_rate', 'timwe_act', 'ekl_rate', 'ekl_act', 'oor_rate', 'oor_act'], $exRows);
        }

        $this->newLine();
        $this->info('Fin de l\'inspection. Si "Lignes avec cible = 1" = 0, l\'extraction ne remplit pas assez les succès/activité ou le critère (rate>0.2) est trop strict.');
        return Command::SUCCESS;
    }

    private function safeRate(object $row, string $col): float
    {
        $v = $row->{$col} ?? null;
        if ($v === null || $v === '') {
            return 0.0;
        }
        return (float) $v;
    }

    private function safeActivity(object $row, string $col): bool
    {
        $v = $row->{$col} ?? null;
        if ($v === null || $v === '') {
            return false;
        }
        return (int) $v === 1;
    }

    private function val(mixed $v): string
    {
        if ($v === null || $v === '') {
            return 'NULL';
        }
        return (string) $v;
    }
}
