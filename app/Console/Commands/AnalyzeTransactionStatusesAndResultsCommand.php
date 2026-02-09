<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Analyse les statuts distincts de transactions_history et les result associés
 * pour extraire comment identifier une transaction facturée (réussie) pour DGV/Ooredoo et Eklektik.
 */
class AnalyzeTransactionStatusesAndResultsCommand extends Command
{
    protected $signature = 'transactions:analyze-statuses-and-results
                            {--samples-per-status=3 : Nombre d\'exemples result par statut}
                            {--export= : Fichier Markdown pour exporter le rapport}';

    protected $description = 'Analyse les statuts distincts et les result pour déduire transaction facturée (DGV vs Eklektik)';

    public function handle(): int
    {
        $samplesPerStatus = (int) $this->option('samples-per-status');
        $exportPath = $this->option('export');

        $this->info('══════════════════════════════════════════════════════════════════════════');
        $this->info('  ANALYSE STATUTS + RESULT — Identifier transaction facturée (réussie)');
        $this->info('══════════════════════════════════════════════════════════════════════════');
        $this->newLine();

        $statuses = DB::table('transactions_history')
            ->selectRaw('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->filter(fn ($s) => $s !== null && $s !== '')
            ->values()
            ->toArray();

        $totalStatuses = count($statuses);
        $this->info("Nombre de statuts distincts : {$totalStatuses}");
        $this->newLine();

        $tableRows = [];
        foreach ($statuses as $status) {
            $tableRows[] = [$status, $this->groupStatus($status)];
        }
        $this->table(['Status', 'Groupe (Ooredoo / Eklektik / Timwe / Autre)'], $tableRows);
        $this->newLine();

        $byGroup = [
            'Ooredoo/DGV' => [],
            'Eklektik (Orange, TT, Taraji)' => [],
            'Timwe' => [],
            'Autre' => [],
        ];

        foreach ($statuses as $status) {
            $group = $this->groupStatus($status);
            $byGroup[$group][] = $status;
        }

        $report = "# Analyse des statuts et result — transactions_history\n\n";
        $report .= "Statuts distincts : **{$totalStatuses}**\n\n";

        $successHints = [];
        foreach ($byGroup as $groupName => $list) {
            if (empty($list)) {
                continue;
            }
            $this->info("── {$groupName} (" . count($list) . " statuts) ──");
            $report .= "## {$groupName} (" . count($list) . " statuts)\n\n";
            sort($list);
            foreach ($list as $status) {
                $report .= "### " . $this->escapeMd($status) . "\n\n";
                $analysis = $this->analyzeStatusResult($status, $samplesPerStatus);
                $report .= $analysis['markdown'];
                $report .= "\n";
                $successHints[] = [$status, $analysis['success_hint'] ?? '-'];
                if (! empty($analysis['success_hint'])) {
                    $this->line("  " . $status . " → " . $analysis['success_hint']);
                } else {
                    $this->line("  " . $status);
                }
            }
            $this->newLine();
        }

        $report .= "## Synthèse : transaction facturée (réussie)\n\n";
        $report .= "- **Timwe** : `result.mnoDeliveryCode` = 'DELIVERED' et `result.totalCharged` > 0 (et pricepointId facturation).\n";
        $report .= "- **Ooredoo/DGV** : statut = OOREDOO_PAYMENT_OFFLINE (facturation ancienne), ou OOREDOO_PAYMENT_OFFLINE_INIT + result.type='INVOICE' + result.status='SUCCESS', ou OOREDOO_PAYMENT_SUCCESS (abonnement).\n";
        $report .= "- **Eklektik** : statut contient CHARGE_DELIVERED ou RENEWED, ou result.confirm='ok' (CONFIRM_SUBSCRIBE, UNSUBSCRIBE), ou result.message='OK' / result.status=0.\n";

        $this->newLine();
        $this->table(['Statut', 'Critère succès / facturée'], $successHints);

        if ($exportPath) {
            file_put_contents($exportPath, $report);
            $this->info("Rapport détaillé exporté : {$exportPath}");
        }

        return self::SUCCESS;
    }

    private function groupStatus(?string $status): string
    {
        if ($status === null || $status === '') {
            return 'Autre';
        }
        if (str_contains($status, 'OOREDOO') || str_contains($status, 'DGV') || str_contains($status, 'DELAYED_OOREDOO')) {
            return 'Ooredoo/DGV';
        }
        if (str_starts_with($status, 'ORANGE_') || str_starts_with($status, 'TT_') || str_starts_with($status, 'TARAJI_')
            || str_contains($status, 'EKLEKTIK') || str_starts_with($status, 'EKLECTIC_') || str_contains($status, 'CLUB_PRIVILEGE')) {
            return 'Eklektik (Orange, TT, Taraji)';
        }
        if (str_starts_with($status, 'TIMWE_')) {
            return 'Timwe';
        }
        return 'Autre';
    }

    private function analyzeStatusResult(string $status, int $samplesPerStatus): array
    {
        $samples = DB::table('transactions_history')
            ->where('status', $status)
            ->whereNotNull('result')
            ->where('result', '!=', '')
            ->select('result')
            ->limit($samplesPerStatus)
            ->get();

        $nullCount = DB::table('transactions_history')
            ->where('status', $status)
            ->where(function ($q) {
                $q->whereNull('result')->orWhere('result', '');
            })
            ->count();

        $totalWithResult = DB::table('transactions_history')->where('status', $status)->whereNotNull('result')->where('result', '!=', '')->count();

        $keysSeen = [];
        $examples = [];
        foreach ($samples as $row) {
            $decoded = json_decode($row->result, true);
            if (is_array($decoded)) {
                $keysSeen = array_unique(array_merge($keysSeen, array_keys($decoded)));
                $examples[] = $this->summarizeResult($decoded);
            }
        }

        $successHint = $this->inferSuccessHint($status, $keysSeen, $examples, $nullCount);

        $md = "";
        $md .= "| Échantillons result (clés racine) | " . implode(', ', array_slice($keysSeen, 0, 25)) . (count($keysSeen) > 25 ? '...' : '') . " |\n";
        $md .= "| Lignes avec result non vide | " . number_format($totalWithResult) . " |\n";
        if ($nullCount > 0) {
            $md .= "| Lignes result null/vide | " . number_format($nullCount) . " |\n";
        }
        $md .= "\n";
        foreach (array_slice($examples, 0, 3) as $i => $ex) {
            $md .= "**Exemple " . ($i + 1) . "** : " . $ex . "\n\n";
        }
        if (! empty($successHint)) {
            $md .= "**Critère succès / facturée** : " . $successHint . "\n\n";
        }

        return ['markdown' => $md, 'success_hint' => $successHint];
    }

    private function summarizeResult(array $result, int $maxLen = 200): string
    {
        $parts = [];
        foreach (['type', 'status', 'event', 'confirm', 'message', 'success', 'mnoDeliveryCode', 'totalCharged', 'invoice'] as $k) {
            if (array_key_exists($k, $result)) {
                $v = $result[$k];
                if (is_array($v)) {
                    $v = json_encode($v);
                }
                $parts[] = "{$k}=" . substr((string) $v, 0, 50);
            }
        }
        if (isset($result['invoice']['price'])) {
            $parts[] = "invoice.price=" . $result['invoice']['price'];
        }
        if (isset($result['response']['mnoDeliveryCode'])) {
            $parts[] = "response.mnoDeliveryCode=" . $result['response']['mnoDeliveryCode'];
        }
        $s = implode(', ', $parts);
        return strlen($s) > $maxLen ? substr($s, 0, $maxLen) . '...' : $s;
    }

    private function inferSuccessHint(string $status, array $keysSeen, array $examples, int $nullCount): string
    {
        $statusUpper = strtoupper($status);

        if (str_contains($status, 'OOREDOO') || str_contains($status, 'DGV')) {
            if ($status === 'OOREDOO_PAYMENT_OFFLINE') {
                return "Facturation réussie (ancienne période, result souvent null).";
            }
            if ($status === 'OOREDOO_PAYMENT_OFFLINE_INIT') {
                return "Facturation si result.type='INVOICE' et result.status='SUCCESS'.";
            }
            if ($status === 'OOREDOO_PAYMENT_SUCCESS') {
                return "Abonnement réussi ; result peut avoir status='SUCCESS' ou event='Subscription'.";
            }
            if (in_array($status, ['OOREDOO_CHARGE_DELIVERED', 'OOREDOO_RENEWED'], true)) {
                return "Facturation / renouvellement réussi (statut suffit ou result.status='SUCCESS').";
            }
            return "Vérifier result.status='SUCCESS' ou result.type si présent.";
        }

        if (str_starts_with($status, 'ORANGE_') || str_starts_with($status, 'TT_') || str_starts_with($status, 'TARAJI_')
            || str_contains($status, 'EKLEKTIK') || str_starts_with($status, 'EKLECTIC_')) {
            if (str_contains($status, 'CHARGE_DELIVERED') || str_contains($status, 'RENEWED')) {
                return "Facturation / renouvellement réussi (statut suffit).";
            }
            if (str_contains($status, 'CONFIRM_SUBSCRIBE') || str_contains($status, 'UNSUBSCRIBE')) {
                return "Succès si result.confirm='ok'.";
            }
            if (in_array('message', $keysSeen)) {
                return "Succès possible si result.message='OK'.";
            }
            if (in_array('status', $keysSeen)) {
                return "Succès possible si result.status=0 (entier).";
            }
            return "Pas de facturation directe (CHECK_USER, GET_SUBSCRIPTION, etc.) ; succès = confirm='ok' pour CONFIRM/UNSUB.";
        }

        if (str_starts_with($status, 'TIMWE_')) {
            if (str_contains($status, 'CHARGE_DELIVERED') || str_contains($status, 'RENEWED')) {
                return "Facturation si result.mnoDeliveryCode='DELIVERED' et result.totalCharged>0.";
            }
            return "Timwe : succès = mnoDeliveryCode=DELIVERED + totalCharged>0.";
        }

        return "";
    }

    private function escapeMd(string $s): string
    {
        return str_replace(['_', '[', ']'], ['\_', '\[', '\]'], $s);
    }
}
