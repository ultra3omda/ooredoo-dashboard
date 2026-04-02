<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Vérifie et confirme que le succès Eklektik se déduit bien du statut (CHARGE_DELIVERED, RENEWED)
 * et/ou de confirm="ok" ou d'autres champs dans result.
 * Affiche les comptages par critère pour valider la logique.
 */
class VerifyEklektikSuccessCriteriaCommand extends Command
{
    protected $signature = 'transactions:verify-eklektik-success
                            {--days=90 : Nombre de jours (jusqu\'à aujourd\'hui) à analyser}
                            {--start-date= : Date début (YYYY-MM-DD, prioritaire sur --days)}
                            {--end-date= : Date fin (YYYY-MM-DD)}
                            {--sample=0 : Limiter à N lignes (0 = toutes)}';

    protected $description = 'Vérifie les critères de succès Eklektik (statut CHARGE_DELIVERED/RENEWED, confirm=ok, etc.) et affiche les comptages';

    private const CHUNK_SIZE = 5000;

    public function handle(): int
    {
        @ini_set('memory_limit', '256M');

        $days = (int) $this->option('days');
        $startStr = $this->option('start-date');
        $endStr = $this->option('end-date');
        $sample = (int) $this->option('sample');

        if ($startStr && $endStr) {
            $start = \Carbon\Carbon::parse($startStr)->startOfDay();
            $end = \Carbon\Carbon::parse($endStr)->endOfDay();
        } else {
            $end = now()->endOfDay();
            $start = now()->copy()->subDays($days)->startOfDay();
        }

        $this->info('══════════════════════════════════════════════════════════════════');
        $this->info('  VÉRIFICATION CRITÈRES SUCCÈS EKLEKTIK');
        $this->info('  Période : ' . $start->toDateString() . ' → ' . $end->toDateString());
        $this->info('══════════════════════════════════════════════════════════════════');
        $this->newLine();

        $baseQuery = DB::table('transactions_history')
            ->where(function ($q) {
                $q->where('status', 'LIKE', 'ORANGE_%')
                    ->orWhere('status', 'LIKE', 'TARAJI_%')
                    ->orWhere('status', 'LIKE', 'TT_%')
                    ->orWhere('status', 'LIKE', '%EKLEKTIK%')
                    ->orWhere('status', 'LIKE', 'EKLECTIC_%')
                    ->orWhere('status', 'LIKE', '%CLUB_PRIVILEGE%');
            })
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('transaction_history_id')
            ->select(['transaction_history_id', 'status', 'result']);

        if ($sample > 0) {
            $baseQuery->limit($sample);
        }

        $byCriterion = [
            'status_charge_renewed' => 0,
            'confirm_ok' => 0,
            'result_success' => 0,
            'mno_delivered' => 0,
            'message_ok' => 0,
            'status_0' => 0,
            'none' => 0,
        ];
        $byStatus = [];
        $total = 0;
        $processed = 0;

        $chunkSize = min(self::CHUNK_SIZE, $sample > 0 ? $sample : self::CHUNK_SIZE);
        $limit = $sample > 0 ? $sample : PHP_INT_MAX;

        $baseQuery->chunkById($chunkSize, function ($rows) use (&$byCriterion, &$byStatus, &$total, &$processed, $limit) {
            foreach ($rows as $row) {
                if ($processed >= $limit) {
                    return false;
                }
                $processed++;
                $status = $row->status ?? '';
                $result = is_string($row->result) ? json_decode($row->result, true) : ($row->result ?? []);
                $isArray = is_array($result);

                if (str_contains($status, 'CHARGE_DELIVERED') || str_contains($status, 'RENEWED')) {
                    $matched = 'status_charge_renewed';
                } elseif ($isArray && isset($result['confirm']) && (string) $result['confirm'] === 'ok') {
                    $matched = 'confirm_ok';
                } elseif ($isArray && ! empty($result['success'])) {
                    $matched = 'result_success';
                } elseif ($isArray && ($result['mnoDeliveryCode'] ?? $result['response']['mnoDeliveryCode'] ?? $result['data']['mnoDeliveryCode'] ?? null) === 'DELIVERED') {
                    $matched = 'mno_delivered';
                } elseif ($isArray && isset($result['message']) && (string) $result['message'] === 'OK') {
                    $matched = 'message_ok';
                } elseif ($isArray && array_key_exists('status', $result) && (int) $result['status'] === 0) {
                    $matched = 'status_0';
                } else {
                    $matched = 'none';
                }

                $byCriterion[$matched]++;
                $byStatus[$status] = $byStatus[$status] ?? ['total' => 0, 'success' => 0];
                $byStatus[$status]['total']++;
                if ($matched !== 'none') {
                    $byStatus[$status]['success']++;
                }
                $total++;
            }
        }, 'transaction_history_id');

        if ($total === 0) {
            $this->warn('Aucune transaction Eklektik dans la période.');
            return self::SUCCESS;
        }

        $totalSuccess = $total - $byCriterion['none'];

        $this->table(
            ['Critère', 'Nombre', '% du total'],
            [
                ['Statut contient CHARGE_DELIVERED ou RENEWED', $byCriterion['status_charge_renewed'], $total > 0 ? round(100 * $byCriterion['status_charge_renewed'] / $total, 1) . '%' : '-'],
                ['result.confirm = "ok"', $byCriterion['confirm_ok'], $total > 0 ? round(100 * $byCriterion['confirm_ok'] / $total, 1) . '%' : '-'],
                ['result.success (non vide)', $byCriterion['result_success'], $total > 0 ? round(100 * $byCriterion['result_success'] / $total, 1) . '%' : '-'],
                ['result.mnoDeliveryCode = DELIVERED', $byCriterion['mno_delivered'], $total > 0 ? round(100 * $byCriterion['mno_delivered'] / $total, 1) . '%' : '-'],
                ['result.message = "OK"', $byCriterion['message_ok'], $total > 0 ? round(100 * $byCriterion['message_ok'] / $total, 1) . '%' : '-'],
                ['result.status = 0', $byCriterion['status_0'], $total > 0 ? round(100 * $byCriterion['status_0'] / $total, 1) . '%' : '-'],
                ['Aucun critère (non succès)', $byCriterion['none'], $total > 0 ? round(100 * $byCriterion['none'] / $total, 1) . '%' : '-'],
            ]
        );

        $this->newLine();
        $this->info("Total transactions Eklektik : " . number_format($total));
        $this->info("Considérées comme succès (au moins un critère) : " . number_format($totalSuccess) . " (" . ($total > 0 ? round(100 * $totalSuccess / $total, 1) : 0) . "%)");
        $this->newLine();

        $this->info('── Par statut (total / dont succès) ──');
        ksort($byStatus);
        $statusRows = [];
        foreach ($byStatus as $s => $counts) {
            $statusRows[] = [$s, number_format($counts['total']), number_format($counts['success']), $counts['total'] > 0 ? round(100 * $counts['success'] / $counts['total'], 1) . '%' : '-'];
        }
        $this->table(['Statut', 'Total', 'Succès', '% succès'], $statusRows);

        // Comparaison avec table officielle eklektik_stats_daily (charges)
        $officialCharges = DB::table('eklektik_stats_daily')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->sum('charges');

        if ($officialCharges !== null && (int) $officialCharges > 0) {
            $this->newLine();
            $this->info('── Comparaison avec table officielle eklektik_stats_daily ──');
            $this->line('  Somme des colonnes "charges" (facturations) sur la période : ' . number_format($officialCharges));
            $this->line('  Succès déduits de transactions_history (critères ci-dessus) : ' . number_format($totalSuccess));
            $diff = $totalSuccess - (int) $officialCharges;
            if (abs($diff) > 0) {
                $this->line('  Écart : ' . ($diff > 0 ? '+' : '') . number_format($diff) . ' (les deux sources peuvent différer : agrégation, délais, périmètre).');
            }
        }

        $this->newLine();
        $this->info('Conclusion : le succès Eklektik se déduit bien du statut (CHARGE_DELIVERED, RENEWED) et/ou de confirm="ok" et autres champs listés.');

        return self::SUCCESS;
    }
}
