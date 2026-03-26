<?php

namespace App\Services;

use App\Models\TransactionHistory;
use App\Services\TimweDiagnosticApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Agrégation du diagnostic Timwe par jour (comme timwe_daily_stats).
 * - Tables pré-remplies : lecture rapide pour 365 jours.
 * - Mise à jour incrémentale à chaque transaction Timwe (ou via backfill).
 */
class TimweDiagnosticAggregateService
{
    /**
     * Détection Timwe : alignée sur TimweStatsService et TransactionHistory (status).
     * Pas d'autre champ (provider, aggregator, etc.) dans transactions_history.
     */
    public static function isTimweTransaction($t): bool
    {
        $status = $t->status ?? '';
        return (stripos($status, 'TIMWE_RENEWED_NOTIF') !== false || stripos($status, 'TIMWE_CHARGE_DELIVERED') !== false)
            && $t->result !== null;
    }

    /**
     * Met à jour les tables d'agrégation pour une seule transaction (appelé à l'ajout d'une transaction Timwe).
     * @param TransactionHistory|object $transaction Modèle ou ligne join (client_telephone, client_nom, client_prenom, subscription_date)
     */
    public function processOneTransaction($transaction): void
    {
        if (!self::isTimweTransaction($transaction)) {
            return;
        }

        $result = is_array($transaction->result)
            ? $transaction->result
            : json_decode($transaction->result, true);
        if (!$result || !is_array($result)) {
            return;
        }

        $statDate = Carbon::parse($transaction->created_at)->format('Y-m-d');
        $mnoDeliveryCode = $result['mnoDeliveryCode'] ?? 'UNKNOWN';
        $totalCharged = isset($result['totalCharged']) ? (int) $result['totalCharged'] : 0;
        $totalChargedTnd = $totalCharged / 1000;
        $isBilled = ($mnoDeliveryCode === 'DELIVERED' && $totalCharged > 0);

        $phone = $transaction->client_telephone ?? ($transaction->client->client_telephone ?? 'N/A');
        $clientId = $transaction->client_id;
        $clientName = isset($transaction->client_nom)
            ? trim(($transaction->client_nom ?? '') . ' ' . ($transaction->client_prenom ?? ''))
            : (isset($transaction->client) ? trim(($transaction->client->client_nom ?? '') . ' ' . ($transaction->client->client_prenom ?? '')) : '');
        $subscriptionDate = $transaction->subscription_date ?? null;
        if ($subscriptionDate !== null && (is_object($subscriptionDate) || strpos((string) $subscriptionDate, ' ') !== false)) {
            $subscriptionDate = Carbon::parse($subscriptionDate)->format('Y-m-d');
        }
        if ($subscriptionDate === null && $clientId) {
            $sub = DB::table('client_abonnement')
                ->where('client_id', $clientId)
                ->orderBy('client_abonnement_id')
                ->value('client_abonnement_creation');
            if ($sub) {
                $subscriptionDate = Carbon::parse($sub)->format('Y-m-d');
            }
        }

        DB::transaction(function () use (
            $statDate, $phone, $clientId, $clientName, $subscriptionDate,
            $mnoDeliveryCode, $totalChargedTnd, $isBilled, $transaction
        ) {
            $this->upsertDailyPhone($statDate, $phone, $clientId, $clientName, $subscriptionDate, $mnoDeliveryCode, $totalChargedTnd, $isBilled, $transaction->created_at);
            $this->upsertDailyDelivery($statDate, $mnoDeliveryCode, $totalChargedTnd);
            $this->upsertDailySummary($statDate, $isBilled, $totalChargedTnd);
        });
    }

    private function upsertDailyPhone(
        string $statDate,
        string $phone,
        int $clientId,
        string $clientName,
        ?string $subscriptionDate,
        string $deliveryCode,
        float $totalChargedTnd,
        bool $isBilled,
        $createdAt
    ): void {
        $d = $deliveryCode;
        $incrementDelivered = $d === 'DELIVERED' ? 1 : 0;
        $incrementNoBalance = $d === 'NO_BALANCE' ? 1 : 0;
        $incrementNotDelivered = $d === 'NOT_DELIVERED' ? 1 : 0;
        $incrementOther = !in_array($d, ['DELIVERED', 'NO_BALANCE', 'NOT_DELIVERED']) ? 1 : 0;

        $existing = DB::table('timwe_diagnostic_daily_phone')
            ->where('stat_date', $statDate)
            ->where('client_telephone', $phone)
            ->first();

        if ($existing) {
            $deliveryCodes = json_decode($existing->delivery_codes ?? '[]', true);
            $deliveryCodes = is_array($deliveryCodes) ? $deliveryCodes : [];
            if (!in_array($deliveryCode, $deliveryCodes)) {
                $deliveryCodes[] = $deliveryCode;
            }
            $newLast = $createdAt;
            $keepLast = $existing->last_attempt_at && (strtotime($existing->last_attempt_at) >= strtotime($newLast)) ? $existing->last_attempt_at : $newLast;

            DB::table('timwe_diagnostic_daily_phone')
                ->where('stat_date', $statDate)
                ->where('client_telephone', $phone)
                ->update([
                    'total_attempts' => $existing->total_attempts + 1,
                    'delivered' => $existing->delivered + $incrementDelivered,
                    'no_balance' => $existing->no_balance + $incrementNoBalance,
                    'not_delivered' => $existing->not_delivered + $incrementNotDelivered,
                    'other' => $existing->other + $incrementOther,
                    'total_charged_tnd' => $existing->total_charged_tnd + $totalChargedTnd,
                    'last_attempt_at' => $keepLast,
                    'delivery_codes' => json_encode($deliveryCodes),
                    'updated_at' => now(),
                ]);
        } else {
            $delivered = $incrementDelivered;
            $noBalance = $incrementNoBalance;
            $notDelivered = $incrementNotDelivered;
            $other = $incrementOther;
            DB::table('timwe_diagnostic_daily_phone')->insert([
                'stat_date' => $statDate,
                'client_telephone' => $phone,
                'client_id' => $clientId,
                'client_name' => $clientName,
                'subscription_date' => $subscriptionDate,
                'total_attempts' => 1,
                'delivered' => $delivered,
                'no_balance' => $noBalance,
                'not_delivered' => $notDelivered,
                'other' => $other,
                'total_charged_tnd' => $totalChargedTnd,
                'last_attempt_at' => $createdAt,
                'delivery_codes' => json_encode([$deliveryCode]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function upsertDailyDelivery(string $statDate, string $deliveryCode, float $totalChargedTnd): void
    {
        $existing = DB::table('timwe_diagnostic_daily_delivery')
            ->where('stat_date', $statDate)
            ->where('delivery_code', $deliveryCode)
            ->first();

        if ($existing) {
            DB::table('timwe_diagnostic_daily_delivery')
                ->where('stat_date', $statDate)
                ->where('delivery_code', $deliveryCode)
                ->update([
                    'count' => $existing->count + 1,
                    'total_charged_tnd' => $existing->total_charged_tnd + $totalChargedTnd,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('timwe_diagnostic_daily_delivery')->insert([
                'stat_date' => $statDate,
                'delivery_code' => $deliveryCode,
                'count' => 1,
                'total_charged_tnd' => $totalChargedTnd,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function upsertDailySummary(string $statDate, bool $isBilled, float $totalChargedTnd): void
    {
        $existing = DB::table('timwe_diagnostic_daily_summary')
            ->where('stat_date', $statDate)
            ->first();

        if ($existing) {
            DB::table('timwe_diagnostic_daily_summary')
                ->where('stat_date', $statDate)
                ->update([
                    'total_transactions' => $existing->total_transactions + 1,
                    'total_billed' => $existing->total_billed + ($isBilled ? 1 : 0),
                    'total_revenue_tnd' => $existing->total_revenue_tnd + $totalChargedTnd,
                    'calculated_at' => now(),
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('timwe_diagnostic_daily_summary')->insert([
                'stat_date' => $statDate,
                'total_transactions' => 1,
                'total_billed' => $isBilled ? 1 : 0,
                'total_revenue_tnd' => $totalChargedTnd,
                'delivery_codes_count' => 0,
                'calculated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $codesCount = DB::table('timwe_diagnostic_daily_delivery')->where('stat_date', $statDate)->count();
        DB::table('timwe_diagnostic_daily_summary')->where('stat_date', $statDate)->update([
            'delivery_codes_count' => $codesCount,
            'updated_at' => now(),
        ]);
    }

    /**
     * Compte le nombre de transactions Timwe pour une date (même critères que le backfill).
     * Utile pour diagnostic (dry-run) sans écrire.
     */
    public function countTransactionsForDate(Carbon $date): int
    {
        $statDate = $date->format('Y-m-d');
        $dayStart = $statDate . ' 00:00:00';
        $dayEnd = $statDate . ' 23:59:59';
        return (int) $this->buildTimweQueryForDate($dayStart, $dayEnd)->count();
    }

    /**
     * Requête de base pour les transactions Timwe sur une plage de dates.
     * Pas de JOIN : uniquement transactions_history pour ne jamais exclure de ligne.
     * Critères alignés sur TimweStatsService (status TIMWE_RENEWED_NOTIF / TIMWE_CHARGE_DELIVERED).
     */
    private function buildTimweQueryForDate(string $dayStart, string $dayEnd)
    {
        return TransactionHistory::query()
            ->where(function ($q) {
                $q->where('transactions_history.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                    ->orWhere('transactions_history.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
            })
            ->whereNotNull('transactions_history.result')
            ->whereBetween('transactions_history.created_at', [$dayStart, $dayEnd]);
    }

    /**
     * Recalcule et remplit les tables pour une date (backfill).
     * Optimisé : agrégation en mémoire par chunks puis bulk insert (au lieu de N × processOneTransaction).
     */
    public function recalculateForDate(Carbon $date): int
    {
        $statDate = $date->format('Y-m-d');
        DB::table('timwe_diagnostic_daily_phone')->where('stat_date', $statDate)->delete();
        DB::table('timwe_diagnostic_daily_delivery')->where('stat_date', $statDate)->delete();
        DB::table('timwe_diagnostic_daily_summary')->where('stat_date', $statDate)->delete();

        $byPhone = [];
        $byDelivery = [];
        $totalTransactions = 0;
        $totalBilled = 0;
        $totalRevenueTnd = 0.0;
        $chunkSize = 1000;

        $dayStart = $statDate . ' 00:00:00';
        $dayEnd = $statDate . ' 23:59:59';

        $query = $this->buildTimweQueryForDate($dayStart, $dayEnd)
            ->select(
                'transactions_history.transaction_history_id',
                'transactions_history.client_id',
                'transactions_history.result',
                'transactions_history.status',
                'transactions_history.created_at',
                DB::raw('(SELECT c.client_telephone FROM client c WHERE c.client_id = transactions_history.client_id LIMIT 1) as client_telephone'),
                DB::raw('(SELECT c.client_nom FROM client c WHERE c.client_id = transactions_history.client_id LIMIT 1) as client_nom'),
                DB::raw('(SELECT c.client_prenom FROM client c WHERE c.client_id = transactions_history.client_id LIMIT 1) as client_prenom'),
                DB::raw('(SELECT ca.client_abonnement_creation FROM client_abonnement ca WHERE ca.client_id = transactions_history.client_id ORDER BY ca.client_abonnement_id ASC LIMIT 1) as subscription_date')
            )
            ->orderBy('transactions_history.created_at');

        $count = 0;
        $query->chunk($chunkSize, function ($transactions) use ($statDate, &$byPhone, &$byDelivery, &$totalTransactions, &$totalBilled, &$totalRevenueTnd, &$count) {
            foreach ($transactions as $t) {
                if (!self::isTimweTransaction($t)) {
                    continue;
                }
                $result = is_array($t->result) ? $t->result : json_decode($t->result, true);
                if (!$result || !is_array($result)) {
                    continue;
                }
                $mnoDeliveryCode = $result['mnoDeliveryCode'] ?? 'UNKNOWN';
                $totalCharged = isset($result['totalCharged']) ? (int) $result['totalCharged'] : 0;
                $totalChargedTnd = $totalCharged / 1000;
                $isBilled = ($mnoDeliveryCode === 'DELIVERED' && $totalCharged > 0);
                $phone = $t->client_telephone ?? 'N/A';
                $clientId = (int) ($t->client_id ?? 0);
                $clientName = trim(($t->client_nom ?? '') . ' ' . ($t->client_prenom ?? ''));
                $subscriptionDate = $t->subscription_date;
                if ($subscriptionDate !== null && (is_object($subscriptionDate) || strpos((string) $subscriptionDate, ' ') !== false)) {
                    $subscriptionDate = Carbon::parse($subscriptionDate)->format('Y-m-d');
                }
                $createdAt = $t->created_at;

                $totalTransactions++;
                if ($isBilled) {
                    $totalBilled++;
                    $totalRevenueTnd += $totalChargedTnd;
                }

                if (!isset($byPhone[$phone])) {
                    $byPhone[$phone] = [
                        'client_telephone' => $phone,
                        'client_id' => $clientId,
                        'client_name' => $clientName,
                        'subscription_date' => $subscriptionDate,
                        'total_attempts' => 0,
                        'delivered' => 0,
                        'no_balance' => 0,
                        'not_delivered' => 0,
                        'other' => 0,
                        'total_charged_tnd' => 0.0,
                        'last_attempt_at' => null,
                        'delivery_codes' => [],
                    ];
                }
                $byPhone[$phone]['total_attempts']++;
                $byPhone[$phone]['total_charged_tnd'] += $totalChargedTnd;
                if ($mnoDeliveryCode === 'DELIVERED') {
                    $byPhone[$phone]['delivered']++;
                } elseif ($mnoDeliveryCode === 'NO_BALANCE') {
                    $byPhone[$phone]['no_balance']++;
                } elseif ($mnoDeliveryCode === 'NOT_DELIVERED') {
                    $byPhone[$phone]['not_delivered']++;
                } else {
                    $byPhone[$phone]['other']++;
                }
                if (!in_array($mnoDeliveryCode, $byPhone[$phone]['delivery_codes'])) {
                    $byPhone[$phone]['delivery_codes'][] = $mnoDeliveryCode;
                }
                if (!$byPhone[$phone]['last_attempt_at'] || (strtotime($createdAt) > strtotime($byPhone[$phone]['last_attempt_at']))) {
                    $byPhone[$phone]['last_attempt_at'] = $createdAt;
                }

                if (!isset($byDelivery[$mnoDeliveryCode])) {
                    $byDelivery[$mnoDeliveryCode] = ['count' => 0, 'total_charged_tnd' => 0.0];
                }
                $byDelivery[$mnoDeliveryCode]['count']++;
                $byDelivery[$mnoDeliveryCode]['total_charged_tnd'] += $totalChargedTnd;
                $count++;
            }
        });

        Log::info("TimweDiagnosticAggregateService - Backfill {$statDate} - transactions trouvées: {$count}", [
            'numéros' => count($byPhone),
            'delivery_codes' => count($byDelivery),
        ]);

        if ($count === 0) {
            $debugQuery = $this->buildTimweQueryForDate($dayStart, $dayEnd);
            Log::warning("TimweDiagnosticAggregateService - Backfill {$statDate}: 0 transaction. SQL pour debug.", [
                'sql' => $debugQuery->toSql(),
                'bindings' => $debugQuery->getBindings(),
            ]);
        }

        $now = now();
        $numPhones = count($byPhone);
        $deliveryCodesCount = count($byDelivery);
        if ($numPhones > 0) {
            $insertChunkSize = 500;
            $chunks = array_chunk($byPhone, $insertChunkSize, true);
            foreach ($chunks as $chunk) {
                $phoneRows = [];
                foreach ($chunk as $row) {
                    $phoneRows[] = [
                        'stat_date' => $statDate,
                        'client_telephone' => $row['client_telephone'],
                        'client_id' => $row['client_id'],
                        'client_name' => $row['client_name'],
                        'subscription_date' => $row['subscription_date'],
                        'total_attempts' => $row['total_attempts'],
                        'delivered' => $row['delivered'],
                        'no_balance' => $row['no_balance'],
                        'not_delivered' => $row['not_delivered'],
                        'other' => $row['other'],
                        'total_charged_tnd' => round($row['total_charged_tnd'], 3),
                        'last_attempt_at' => $row['last_attempt_at'],
                        'delivery_codes' => json_encode($row['delivery_codes']),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DB::table('timwe_diagnostic_daily_phone')->insert($phoneRows);
                unset($phoneRows, $chunk);
            }
            unset($chunks, $byPhone);
        }
        if ($deliveryCodesCount > 0) {
            $deliveryRows = [];
            foreach ($byDelivery as $code => $row) {
                $deliveryRows[] = [
                    'stat_date' => $statDate,
                    'delivery_code' => $code,
                    'count' => $row['count'],
                    'total_charged_tnd' => round($row['total_charged_tnd'], 3),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('timwe_diagnostic_daily_delivery')->insert($deliveryRows);
            unset($deliveryRows, $byDelivery);
        }
        DB::table('timwe_diagnostic_daily_summary')->insert([
            'stat_date' => $statDate,
            'total_transactions' => $totalTransactions,
            'total_billed' => $totalBilled,
            'total_revenue_tnd' => round($totalRevenueTnd, 3),
            'delivery_codes_count' => $deliveryCodesCount,
            'calculated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Log::info("TimweDiagnosticAggregateService - Backfill {$statDate}: {$count} transactions, {$numPhones} numéros (résumé final).");
        TimweDiagnosticApiService::invalidateForDate($statDate);
        gc_collect_cycles();
        return $count;
    }

    /**
     * Retourne true si les tables agrégées ont des données pour toute la période.
     */
    public function hasAggregatesForPeriod(Carbon $start, Carbon $end): bool
    {
        $days = $start->diffInDays($end) + 1;
        $count = DB::table('timwe_diagnostic_daily_summary')
            ->whereBetween('stat_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->count();
        return $count >= $days;
    }
}
