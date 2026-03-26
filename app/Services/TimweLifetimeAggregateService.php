<?php

namespace App\Services;

use App\Models\TransactionHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Agrégation lifetime par numéro pour le diagnostic Timwe.
 * Table timwe_phone_lifetime_stats : mise à jour incrémentale via observer (updateForTransaction),
 * ou recalcul via recalcPhone / recalcAll. Aucun scan transactions_history au runtime lecture.
 */
class TimweLifetimeAggregateService
{
    public static function isTimweTransaction($t): bool
    {
        return TimweDiagnosticAggregateService::isTimweTransaction($t);
    }

    /**
     * Mise à jour incrémentale pour une transaction (observer). Alias: updateForTransaction.
     */
    public function updateForTransaction(TransactionHistory $transaction): void
    {
        $this->incrementFromTransaction($transaction);
    }

    /**
     * Met à jour la table lifetime pour une transaction (appelé par l'observer).
     */
    public function incrementFromTransaction(TransactionHistory $transaction): void
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
        $phone = null;
        if ($transaction->relationLoaded('client') && $transaction->client) {
            $phone = $transaction->client->client_telephone ?? null;
        }
        if ($phone === null && $transaction->client_id) {
            $phone = DB::table('client')->where('client_id', $transaction->client_id)->value('client_telephone');
        }
        if ($phone === null || $phone === '') {
            return;
        }
        $mno = $result['mnoDeliveryCode'] ?? 'UNKNOWN';
        $charged = isset($result['totalCharged']) ? (int) $result['totalCharged'] : 0;
        $chargedTnd = round($charged / 1000, 3);
        $createdAt = $transaction->created_at;

        $delivered = $mno === 'DELIVERED' ? 1 : 0;
        $noBalance = $mno === 'NO_BALANCE' ? 1 : 0;
        $notDelivered = $mno === 'NOT_DELIVERED' ? 1 : 0;
        $other = in_array($mno, ['DELIVERED', 'NO_BALANCE', 'NOT_DELIVERED']) ? 0 : 1;

        if (!Schema::hasTable('timwe_phone_lifetime_stats')) {
            return;
        }
        DB::transaction(function () use ($phone, $delivered, $noBalance, $notDelivered, $other, $chargedTnd, $createdAt) {
            $row = DB::table('timwe_phone_lifetime_stats')->where('client_telephone', $phone)->first();
            if ($row) {
                $newLast = $row->lifetime_last_attempt_at && (strtotime((string) $row->lifetime_last_attempt_at) >= strtotime($createdAt))
                    ? $row->lifetime_last_attempt_at
                    : $createdAt;
                DB::table('timwe_phone_lifetime_stats')->where('client_telephone', $phone)->update([
                    'lifetime_attempts' => $row->lifetime_attempts + 1,
                    'lifetime_delivered' => $row->lifetime_delivered + $delivered,
                    'lifetime_no_balance' => $row->lifetime_no_balance + $noBalance,
                    'lifetime_not_delivered' => $row->lifetime_not_delivered + $notDelivered,
                    'lifetime_other' => $row->lifetime_other + $other,
                    'lifetime_total_charged_tnd' => round($row->lifetime_total_charged_tnd + $chargedTnd, 3),
                    'lifetime_last_attempt_at' => $newLast,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('timwe_phone_lifetime_stats')->insert([
                    'client_telephone' => $phone,
                    'lifetime_attempts' => 1,
                    'lifetime_delivered' => $delivered,
                    'lifetime_no_balance' => $noBalance,
                    'lifetime_not_delivered' => $notDelivered,
                    'lifetime_other' => $other,
                    'lifetime_total_charged_tnd' => $chargedTnd,
                    'lifetime_last_attempt_at' => $createdAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /**
     * Recalcule les stats lifetime pour un numéro (hors flux lecture, backfill uniquement).
     */
    public function recalcPhone(string $phone): void
    {
        $this->recalculateForPhone($phone);
    }

    /**
     * Recalcule les stats lifetime pour un numéro à partir de transactions_history.
     */
    public function recalculateForPhone(string $phone): void
    {
        if (!Schema::hasTable('timwe_phone_lifetime_stats')) {
            return;
        }
        $rows = DB::table('transactions_history as th')
            ->join('client as c', 'th.client_id', '=', 'c.client_id')
            ->where('c.client_telephone', $phone)
            ->where(function ($q) {
                $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
            })
            ->whereNotNull('th.result')
            ->select('th.result', 'th.created_at')
            ->get();

        $attempts = 0;
        $delivered = 0;
        $noBalance = 0;
        $notDelivered = 0;
        $other = 0;
        $totalChargedTnd = 0.0;
        $lastAttemptAt = null;
        foreach ($rows as $t) {
            $result = is_array($t->result) ? $t->result : json_decode($t->result, true);
            if (!$result || !is_array($result)) {
                continue;
            }
            $attempts++;
            $mno = $result['mnoDeliveryCode'] ?? 'UNKNOWN';
            $charged = isset($result['totalCharged']) ? (int) $result['totalCharged'] : 0;
            $totalChargedTnd += $charged / 1000;
            switch ($mno) {
                case 'DELIVERED': $delivered++; break;
                case 'NO_BALANCE': $noBalance++; break;
                case 'NOT_DELIVERED': $notDelivered++; break;
                default: $other++;
            }
            if (!$lastAttemptAt || (strtotime($t->created_at) > strtotime($lastAttemptAt))) {
                $lastAttemptAt = $t->created_at;
            }
        }
        $totalChargedTnd = round($totalChargedTnd, 3);
        DB::table('timwe_phone_lifetime_stats')->updateOrInsert(
            ['client_telephone' => $phone],
            [
                'lifetime_attempts' => $attempts,
                'lifetime_delivered' => $delivered,
                'lifetime_no_balance' => $noBalance,
                'lifetime_not_delivered' => $notDelivered,
                'lifetime_other' => $other,
                'lifetime_total_charged_tnd' => $totalChargedTnd,
                'lifetime_last_attempt_at' => $lastAttemptAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Recalcule toute la table lifetime via GROUP BY client_telephone (backfill).
     * N'utilise pas de foreach sur > 5000 lignes : requête agrégée + upsert par lots.
     */
    public function recalcAll(): void
    {
        if (!Schema::hasTable('timwe_phone_lifetime_stats')) {
            return;
        }
        $chunkSize = 1000;
        $offset = 0;
        while (true) {
            $rows = DB::table('transactions_history as th')
                ->join('client as c', 'th.client_id', '=', 'c.client_id')
                ->where(function ($q) {
                    $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                        ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
                })
                ->whereNotNull('th.result')
                ->selectRaw("
                    c.client_telephone,
                    COUNT(*) as lifetime_attempts,
                    SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(th.result,'$.mnoDeliveryCode')) = 'DELIVERED' THEN 1 ELSE 0 END) as lifetime_delivered,
                    SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(th.result,'$.mnoDeliveryCode')) = 'NO_BALANCE' THEN 1 ELSE 0 END) as lifetime_no_balance,
                    SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(th.result,'$.mnoDeliveryCode')) = 'NOT_DELIVERED' THEN 1 ELSE 0 END) as lifetime_not_delivered,
                    SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(th.result,'$.mnoDeliveryCode')) NOT IN ('DELIVERED','NO_BALANCE','NOT_DELIVERED') THEN 1 ELSE 0 END) as lifetime_other,
                    COALESCE(SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(th.result,'$.totalCharged')) AS UNSIGNED)) / 1000, 0) as lifetime_total_charged_tnd,
                    MAX(th.created_at) as lifetime_last_attempt_at
                ")
                ->groupBy('c.client_telephone')
                ->orderBy('c.client_telephone')
                ->offset($offset)
                ->limit($chunkSize)
                ->get();
            if ($rows->isEmpty()) {
                break;
            }
            $now = now();
            foreach ($rows as $r) {
                DB::table('timwe_phone_lifetime_stats')->updateOrInsert(
                    ['client_telephone' => $r->client_telephone],
                    [
                        'lifetime_attempts' => (int) $r->lifetime_attempts,
                        'lifetime_delivered' => (int) $r->lifetime_delivered,
                        'lifetime_no_balance' => (int) $r->lifetime_no_balance,
                        'lifetime_not_delivered' => (int) $r->lifetime_not_delivered,
                        'lifetime_other' => (int) $r->lifetime_other,
                        'lifetime_total_charged_tnd' => round((float) $r->lifetime_total_charged_tnd, 3),
                        'lifetime_last_attempt_at' => $r->lifetime_last_attempt_at,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
            if ($rows->count() < $chunkSize) {
                break;
            }
            $offset += $chunkSize;
        }
    }
}
