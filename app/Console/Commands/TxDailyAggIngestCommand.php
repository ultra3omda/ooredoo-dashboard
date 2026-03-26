<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TxDailyAggIngestCommand extends Command
{
    protected $signature = 'ml:tx-daily-ingest
                            {--batch-size=200000 : Nombre de transactions par batch}
                            {--max-batches=0 : Nombre max de batches (0=infini)}
                            {--force : Force l\'ingestion même si checkpoint récent}';

    protected $description = 'Ingestion incrémentale des transactions vers tx_daily_agg';

    const CHUNK_SIZE = 10000; // ChunkById pour streaming
    const SUPPORTED_STATUSES = ['TIMWE', 'ORANGE', 'TARAJI', 'TT', 'OOREDOO', 'DGV', 'EKLEKTIK', 'EKLECTIC', 'CLUB_PRIVILEGE'];

    public function handle(): int
    {
        // Augmenter la limite mémoire pour les gros volumes
        @ini_set('memory_limit', '512M');
        
        $startTime = microtime(true);
        $batchSize = (int) $this->option('batch-size');
        $maxBatches = (int) $this->option('max-batches');
        $force = $this->option('force');

        $this->info("🔄 Début ingestion incrémentale des transactions");
        $this->info("   Batch size: {$batchSize}, Max batches: " . ($maxBatches ?: '∞'));

        // 1) LIRE LE CHECKPOINT
        $checkpoint = DB::table('ml_job_state')
            ->where('job_name', 'tx_daily_ingest')
            ->first();

        if (!$checkpoint) {
            // Créer le checkpoint s'il n'existe pas
            DB::table('ml_job_state')->insert([
                'job_name' => 'tx_daily_ingest',
                'last_processed_id' => 0,
                'last_processed_at' => null,
            ]);
            $lastProcessedId = 0;
        } else {
            $lastProcessedId = $checkpoint->last_processed_id;
            
            if (!$force && $checkpoint->last_processed_at) {
                $lastProcessedTime = Carbon::parse($checkpoint->last_processed_at);
                if ($lastProcessedTime->diffInMinutes(now()) < 5) {
                    $this->warn("⏭️  Dernier traitement il y a < 5 min. Utilisez --force pour forcer.");
                    return 0;
                }
            }
        }

        $this->info("📍 Checkpoint: ID {$lastProcessedId}");

        // 2) INGESTION PAR BATCHES
        $batchCount = 0;
        $totalRowsProcessed = 0;
        $totalAggregated = 0;

        while (true) {
            $batchCount++;
            
            if ($maxBatches > 0 && $batchCount > $maxBatches) {
                $this->warn("⏸️  Limite de batches atteinte ({$maxBatches})");
                break;
            }

            $this->info("\n📦 Batch #{$batchCount}");
            
            $batchStartTime = microtime(true);
            $aggregates = []; // [day][client_id][status] => [tx_count, amount_sum, last_tx_id, last_tx_at]
            $rowsInBatch = 0;
            $maxIdInBatch = $lastProcessedId;

            // 3) CHARGER LES NOUVELLES TRANSACTIONS (streaming avec chunkById)
            DB::table('transactions_history')
                ->leftJoin('abonnement_tarifs', 'transactions_history.tarif_id', '=', 'abonnement_tarifs.abonnement_tarifs_id')
                ->where('transaction_history_id', '>', $lastProcessedId)
                ->where(function ($query) {
                    foreach (self::SUPPORTED_STATUSES as $status) {
                        $query->orWhere('status', 'LIKE', "{$status}%");
                    }
                })
                ->select('transaction_history_id', 'transactions_history.client_id', 'transactions_history.created_at', 'transactions_history.status', 'abonnement_tarifs.abonnement_tarifs_prix as amount')
                ->orderBy('transaction_history_id')
                ->chunkById(self::CHUNK_SIZE, function ($transactions) use (&$aggregates, &$rowsInBatch, &$maxIdInBatch, $batchSize) {
                    foreach ($transactions as $tx) {
                        $rowsInBatch++;
                        $maxIdInBatch = max($maxIdInBatch, $tx->transaction_history_id);

                        // Extraire le jour et le status simplifié
                        $day = Carbon::parse($tx->created_at)->format('Y-m-d');
                        $clientId = (int) $tx->client_id;
                        $status = $this->normalizeStatus($tx->status);
                        $amount = (float) ($tx->amount ?? 0);
                        
                        if (!$status || $clientId <= 0) {
                            continue;
                        }

                        // Agréger en mémoire
                        if (!isset($aggregates[$day])) {
                            $aggregates[$day] = [];
                        }
                        if (!isset($aggregates[$day][$clientId])) {
                            $aggregates[$day][$clientId] = [];
                        }
                        if (!isset($aggregates[$day][$clientId][$status])) {
                            $aggregates[$day][$clientId][$status] = [
                                'tx_count' => 0,
                                'amount_sum' => 0,
                                'last_tx_id' => 0,
                                'last_tx_at' => null,
                            ];
                        }

                        $agg = &$aggregates[$day][$clientId][$status];
                        $agg['tx_count']++;
                        $agg['amount_sum'] += $amount;
                        $agg['last_tx_id'] = max($agg['last_tx_id'], $tx->transaction_history_id);
                        
                        if (!$agg['last_tx_at'] || $tx->created_at > $agg['last_tx_at']) {
                            $agg['last_tx_at'] = $tx->created_at;
                        }

                        // Arrêter si on atteint la taille du batch
                        if ($rowsInBatch >= $batchSize) {
                            return false; // Stop chunking
                        }
                    }
                }, 'transaction_history_id');

            $loadTime = round((microtime(true) - $batchStartTime) * 1000, 2);

            if ($rowsInBatch === 0) {
                $this->info("✅ Aucune nouvelle transaction. Ingestion terminée.");
                break;
            }

            $this->info("   📊 Chargé: {$rowsInBatch} tx en {$loadTime}ms");
            $this->info("   🔢 Max ID: {$maxIdInBatch}");
            $this->info("   💾 Mémoire: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB");

            // 4) UPSERT DANS tx_daily_agg
            $upsertStartTime = microtime(true);
            $upsertCount = $this->upsertAggregates($aggregates);
            $upsertTime = round((microtime(true) - $upsertStartTime) * 1000, 2);

            $this->info("   💾 Upserted: {$upsertCount} lignes en {$upsertTime}ms");
            
            // Libérer la mémoire
            unset($aggregates);
            gc_collect_cycles();

            // 5) METTRE À JOUR LE CHECKPOINT
            DB::table('ml_job_state')
                ->where('job_name', 'tx_daily_ingest')
                ->update([
                    'last_processed_id' => $maxIdInBatch,
                    'last_processed_at' => now(),
                ]);

            $totalRowsProcessed += $rowsInBatch;
            $totalAggregated += $upsertCount;
            $lastProcessedId = $maxIdInBatch;

            // Si on a traité moins que batch_size, on a fini
            if ($rowsInBatch < $batchSize) {
                $this->info("✅ Batch incomplet, fin de l'ingestion.");
                break;
            }
        }

        $totalTime = round(microtime(true) - $startTime, 2);
        
        $this->newLine();
        $this->info("=" .str_repeat("=", 60));
        $this->info("✅ Ingestion terminée !");
        $this->info("   📊 Transactions traitées: {$totalRowsProcessed}");
        $this->info("   💾 Agrégats upsertés: {$totalAggregated}");
        $this->info("   📦 Batches: {$batchCount}");
        $this->info("   ⏱️  Temps total: {$totalTime}s");
        $this->info("   📍 Checkpoint final: ID {$lastProcessedId}");
        $this->info("=" .str_repeat("=", 60));

        return 0;
    }

    /**
     * Normalise le status pour obtenir l'opérateur principal
     */
    private function normalizeStatus(string $status): ?string
    {
        $status = strtoupper($status);
        
        foreach (self::SUPPORTED_STATUSES as $op) {
            if (str_starts_with($status, $op) || str_contains($status, $op)) {
                return $op;
            }
        }
        
        return null;
    }

    /**
     * UPSERT des agrégats dans tx_daily_agg
     * 
     * Utilise INSERT ... ON DUPLICATE KEY UPDATE pour performance maximale
     */
    private function upsertAggregates(array $aggregates): int
    {
        if (empty($aggregates)) {
            return 0;
        }

        $rows = [];
        $now = now();

        foreach ($aggregates as $day => $clients) {
            foreach ($clients as $clientId => $statuses) {
                foreach ($statuses as $status => $agg) {
                    $rows[] = [
                        'day' => $day,
                        'client_id' => $clientId,
                        'status' => $status,
                        'tx_count' => $agg['tx_count'],
                        'amount_sum' => $agg['amount_sum'],
                        'amount_avg' => $agg['tx_count'] > 0 
                            ? round($agg['amount_sum'] / $agg['tx_count'], 3)
                            : 0,
                        'last_tx_id' => $agg['last_tx_id'],
                        'last_tx_at' => $agg['last_tx_at'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        if (empty($rows)) {
            return 0;
        }

        // Chunker les inserts pour éviter "too many placeholders"
        $inserted = 0;
        foreach (array_chunk($rows, 1000) as $chunk) {
            $placeholders = [];
            $values = [];

            foreach ($chunk as $row) {
                $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                $values[] = $row['day'];
                $values[] = $row['client_id'];
                $values[] = $row['status'];
                $values[] = $row['tx_count'];
                $values[] = $row['amount_sum'];
                $values[] = $row['amount_avg'];
                $values[] = $row['last_tx_id'];
                $values[] = $row['last_tx_at'];
                $values[] = $row['created_at'];
                $values[] = $row['updated_at'];
            }

            $sql = "
                INSERT INTO tx_daily_agg 
                (day, client_id, status, tx_count, amount_sum, amount_avg, last_tx_id, last_tx_at, created_at, updated_at)
                VALUES " . implode(', ', $placeholders) . "
                ON DUPLICATE KEY UPDATE
                    tx_count = tx_count + VALUES(tx_count),
                    amount_sum = amount_sum + VALUES(amount_sum),
                    amount_avg = (amount_sum / tx_count),
                    last_tx_id = GREATEST(last_tx_id, VALUES(last_tx_id)),
                    last_tx_at = GREATEST(last_tx_at, VALUES(last_tx_at)),
                    updated_at = VALUES(updated_at)
            ";

            DB::statement($sql, $values);
            $inserted += count($chunk);
        }

        return $inserted;
    }
}
