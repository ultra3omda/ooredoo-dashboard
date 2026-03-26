<?php

namespace App\Observers;

use App\Models\TransactionHistory;
use App\Services\TimweDiagnosticAggregateService;
use App\Services\TimweLifetimeAggregateService;
use Illuminate\Support\Facades\Log;

/**
 * Met à jour les tables d'agrégation du diagnostic Timwe à chaque création/mise à jour
 * d'une transaction Timwe : daily (TimweDiagnosticAggregateService) et lifetime (TimweLifetimeAggregateService).
 */
class TransactionHistoryObserver
{
    public function created(TransactionHistory $transaction): void
    {
        $this->processIfTimwe($transaction);
    }

    public function updated(TransactionHistory $transaction): void
    {
        if ($transaction->isDirty('result')) {
            $this->processIfTimwe($transaction);
        }
    }

    private function processIfTimwe(TransactionHistory $transaction): void
    {
        if (!TimweDiagnosticAggregateService::isTimweTransaction($transaction)) {
            return;
        }
        try {
            (new TimweDiagnosticAggregateService())->processOneTransaction($transaction);
            (new TimweLifetimeAggregateService())->updateForTransaction($transaction);
        } catch (\Throwable $e) {
            Log::warning('TransactionHistoryObserver - Erreur mise à jour diagnostic Timwe: ' . $e->getMessage(), [
                'transaction_id' => $transaction->transaction_history_id,
            ]);
        }
    }
}
