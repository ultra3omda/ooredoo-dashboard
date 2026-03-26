<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Analyse les structures de la colonne `result` (JSON) pour Eklektik et Ooredoo/DGV
 * afin de déterminer comment identifier une transaction réussie (facturation).
 * Timwe utilise uniquement mnoDeliveryCode/DELIVERED ; Eklektik et DGV ont d'autres formats.
 * Référence : tables officielles eklektik_stats_daily (charges, CA) et ooredoo_daily_stats (total_billings, revenue_tnd).
 */
class AnalyzeTransactionResultStructuresCommand extends Command
{
    protected $signature = 'transactions:analyze-result-structures
                            {--operator=both : eklektik | ooredoo | both}
                            {--sample=3000 : Nombre max de lignes à échantillonner par opérateur}
                            {--export= : Fichier Markdown pour exporter le rapport}';

    protected $description = 'Analyse les clés et valeurs de result (JSON) pour Eklektik et Ooredoo afin de calculer correctement une transaction réussie';

    public function handle(): int
    {
        $operator = strtolower($this->option('operator'));
        $sampleSize = (int) $this->option('sample');
        $exportPath = $this->option('export');

        $this->info('══════════════════════════════════════════════════════════════════════════');
        $this->info('  ANALYSE DES STRUCTURES result — Eklektik & Ooredoo/DGV');
        $this->info('  (Timwe : uniquement mnoDeliveryCode / DELIVERED)');
        $this->info('══════════════════════════════════════════════════════════════════════════');
        $this->newLine();

        $report = [];

        if (in_array($operator, ['eklektik', 'both'], true)) {
            $report[] = $this->analyzeEklektik($sampleSize);
        }
        if (in_array($operator, ['ooredoo', 'both'], true)) {
            $report[] = $this->analyzeOoredoo($sampleSize);
        }

        $fullReport = implode("\n\n", $report);

        $this->line($fullReport);

        $this->newLine();
        $this->info('Référence officielle :');
        $this->line('  - Eklektik : eklektik_stats_daily (charges = facturations, total_revenue / revenu_ttc_tnd = CA)');
        $this->line('  - DGV/Ooredoo : ooredoo_daily_stats (total_billings = facturations, revenue_tnd = CA)');
        $this->line('  - Timwe : transactions_history result.mnoDeliveryCode = DELIVERED + totalCharged > 0');

        if ($exportPath) {
            $header = "# Analyse des structures result (Eklektik & Ooredoo)\n\nGénéré par `php artisan transactions:analyze-result-structures --export=" . basename($exportPath) . "`.\n\n";
            file_put_contents($exportPath, $header . $fullReport);
            $this->info("Rapport exporté : {$exportPath}");
        }

        return self::SUCCESS;
    }

    private function analyzeEklektik(int $sampleSize): string
    {
        $this->info('── EKLEKTIK (ORANGE_*, TARAJI_*, TT_*, EKLEKTIK, EKLECTIC_*, CLUB_PRIVILEGE) ──');

        $query = DB::table('transactions_history')
            ->where(function ($q) {
                $q->where('status', 'LIKE', 'ORANGE_%')
                    ->orWhere('status', 'LIKE', 'TARAJI_%')
                    ->orWhere('status', 'LIKE', 'TT_%')
                    ->orWhere('status', 'LIKE', '%EKLEKTIK%')
                    ->orWhere('status', 'LIKE', 'EKLECTIC_%')
                    ->orWhere('status', 'LIKE', '%CLUB_PRIVILEGE%');
            })
            ->whereNotNull('result')
            ->where('result', '!=', '')
            ->inRandomOrder()
            ->limit($sampleSize)
            ->select(['status', 'result', 'created_at']);

        $rows = $query->get();
        $byStatus = [];
        $keyCounts = [];
        $samplesBySignature = [];

        foreach ($rows as $row) {
            $status = $row->status ?? '(null)';
            $decoded = json_decode($row->result, true);
            if (! is_array($decoded)) {
                $byStatus[$status]['_invalid_json'] = ($byStatus[$status]['_invalid_json'] ?? 0) + 1;
                continue;
            }
            $keys = array_keys($decoded);
            sort($keys);
            $signature = implode(',', $keys);
            $byStatus[$status] = $byStatus[$status] ?? [];
            $byStatus[$status][$signature] = ($byStatus[$status][$signature] ?? 0) + 1;
            foreach ($keys as $k) {
                $keyCounts[$k] = ($keyCounts[$k] ?? 0) + 1;
            }
            if (! isset($samplesBySignature[$signature]) || count($samplesBySignature[$signature]) < 2) {
                $samplesBySignature[$signature] = $samplesBySignature[$signature] ?? [];
                $samplesBySignature[$signature][] = [
                    'status' => $status,
                    'result' => $decoded,
                    'created_at' => $row->created_at,
                ];
            }
        }

        $out = "## Eklektik\n\n";
        $out .= "### Clés rencontrées (racine)\n";
        arsort($keyCounts);
        foreach ($keyCounts as $key => $count) {
            $out .= "- `{$key}` : " . number_format($count) . " occurrences\n";
        }
        $out .= "\n### Par statut puis structure (clés racine)\n";
        ksort($byStatus);
        foreach ($byStatus as $status => $sigs) {
            $out .= "- **{$status}**\n";
            arsort($sigs);
            foreach ($sigs as $sig => $cnt) {
                $out .= "  - `{$sig}` : " . number_format($cnt) . "\n";
            }
        }
        $out .= "\n### Exemples de result (par structure)\n";
        foreach (array_slice($samplesBySignature, 0, 15) as $signature => $samples) {
            $out .= "- Structure `{$signature}` :\n";
            foreach (array_slice($samples, 0, 2) as $i => $s) {
                $preview = json_encode($s['result'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                if (strlen($preview) > 600) {
                    $preview = substr($preview, 0, 600) . "\n...";
                }
                $out .= "  Exemple " . ($i + 1) . " (status={$s['status']}, created_at={$s['created_at']}) :\n  ```json\n  " . trim($preview) . "\n  ```\n";
            }
        }

        $this->line("  Statuts vus : " . count($byStatus) . " | Clés racine : " . count($keyCounts) . " | Lignes analysées : " . $rows->count());

        return $out;
    }

    private function analyzeOoredoo(int $sampleSize): string
    {
        $this->info('── OOREDOO / DGV (statuts contenant OOREDOO ou DGV) ──');

        $query = DB::table('transactions_history')
            ->where(function ($q) {
                $q->where('status', 'LIKE', '%OOREDOO%')->orWhere('status', 'LIKE', '%DGV%');
            })
            ->inRandomOrder()
            ->limit($sampleSize)
            ->select(['status', 'result', 'created_at']);

        $rows = $query->get();
        $byStatus = [];
        $keyCounts = [];
        $keyCountsNonNull = [];
        $samplesBySignature = [];

        foreach ($rows as $row) {
            $status = $row->status ?? '(null)';
            $raw = $row->result;
            if ($raw === null || $raw === '') {
                $byStatus[$status]['_null_empty'] = ($byStatus[$status]['_null_empty'] ?? 0) + 1;
                continue;
            }
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                $byStatus[$status]['_invalid_json'] = ($byStatus[$status]['_invalid_json'] ?? 0) + 1;
                continue;
            }
            $keys = array_keys($decoded);
            sort($keys);
            $signature = implode(',', $keys);
            $byStatus[$status] = $byStatus[$status] ?? [];
            $byStatus[$status][$signature] = ($byStatus[$status][$signature] ?? 0) + 1;
            foreach ($keys as $k) {
                $keyCounts[$k] = ($keyCounts[$k] ?? 0) + 1;
                $keyCountsNonNull[$k] = ($keyCountsNonNull[$k] ?? 0) + 1;
            }
            if (! isset($samplesBySignature[$signature]) || count($samplesBySignature[$signature]) < 2) {
                $samplesBySignature[$signature] = $samplesBySignature[$signature] ?? [];
                $samplesBySignature[$signature][] = [
                    'status' => $status,
                    'result' => $decoded,
                    'created_at' => $row->created_at,
                ];
            }
        }

        $out = "## Ooredoo / DGV\n\n";
        $out .= "### Clés rencontrées (racine, result non vide)\n";
        arsort($keyCountsNonNull);
        foreach ($keyCountsNonNull as $key => $count) {
            $out .= "- `{$key}` : " . number_format($count) . " occurrences\n";
        }
        $out .= "\n### Par statut puis structure (clés racine)\n";
        ksort($byStatus);
        foreach ($byStatus as $status => $sigs) {
            $out .= "- **{$status}**\n";
            arsort($sigs);
            foreach ($sigs as $sig => $cnt) {
                $out .= "  - `{$sig}` : " . number_format($cnt) . "\n";
            }
        }
        $out .= "\n### Exemples de result (par structure)\n";
        foreach (array_slice($samplesBySignature, 0, 15) as $signature => $samples) {
            $out .= "- Structure `{$signature}` :\n";
            foreach (array_slice($samples, 0, 2) as $i => $s) {
                $preview = json_encode($s['result'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                if (strlen($preview) > 600) {
                    $preview = substr($preview, 0, 600) . "\n...";
                }
                $out .= "  Exemple " . ($i + 1) . " (status={$s['status']}, created_at={$s['created_at']}) :\n  ```json\n  " . trim($preview) . "\n  ```\n";
            }
        }
        $out .= "\n### Référence OoredooStatsService (facturation réussie)\n";
        $out .= "- Avant 01/09/2025 : status = `OOREDOO_PAYMENT_OFFLINE` (result peut être null).\n";
        $out .= "- Après 01/09/2025 : status = `OOREDOO_PAYMENT_OFFLINE_INIT` et result.`type` = 'INVOICE' et result.`status` = 'SUCCESS'.\n";
        $out .= "- Nouvel abonnement : status = `OOREDOO_PAYMENT_SUCCESS` ; result peut avoir `status` = 'SUCCESS' (nouveau) ou `event` = 'Subscription' (ancien).\n";

        $this->line("  Statuts vus : " . count($byStatus) . " | Clés racine : " . count($keyCountsNonNull) . " | Lignes analysées : " . $rows->count());

        return $out;
    }
}
