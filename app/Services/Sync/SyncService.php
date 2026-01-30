<?php

namespace App\Services\Sync;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Client\RequestException;

class SyncService
{
    public function pullTable(string $table): array
    {
        $pk = Config::get("sync.tables.$table");
        if (!$pk) {
            throw new \InvalidArgumentException("Unknown table $table");
        }

        $batchSize = (int) Config::get('sync.batch_size', 5000);
        $url = Config::get('sync.url');
        $token = Config::get('sync.token');
        $timeout = (int) Config::get('sync.timeout', 30);
        $retryTimes = (int) Config::get('sync.retry.times', 3);
        $retrySleep = (int) Config::get('sync.retry.sleep_ms', 2000); // Augmenté à 2s
        $requestDelay = (int) Config::get('sync.request_delay_ms', 500); // Délai entre requêtes

        $checkpoint = DB::table('sync_checkpoints')->where('table_name', $table)->first();
        $lastId = $checkpoint->last_id ?? 0;

        $totalReceived = 0;
        $totalUpserted = 0;
        $start = microtime(true);

        while (true) {
            $payload = [
                'tables' => [
                    $table => [
                        'colonne_id_name' => $pk,
                        'last_inserted_id' => (int)$lastId,
                    ]
                ]
            ];

            try {
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'Authorization' => $token,
                    ])
                    ->retry($retryTimes, $retrySleep)
                    ->post($url, $payload);
            } catch (RequestException $e) {
                $this->markError($table, $start, $e->getMessage());
                return [
                    'received' => $totalReceived,
                    'upserted' => $totalUpserted,
                    'ms' => (int) round((microtime(true) - $start) * 1000),
                ];
            } catch (\Throwable $e) {
                $this->markError($table, $start, $e->getMessage());
                return [
                    'received' => $totalReceived,
                    'upserted' => $totalUpserted,
                    'ms' => (int) round((microtime(true) - $start) * 1000),
                ];
            }

            if (!$response->ok()) {
                $this->markError($table, $start, $response->status().' '.$response->body());
                throw new \RuntimeException("Sync API error for $table: ".$response->status());
            }

            $data = $response->json();
            $rows = $data[$table] ?? [];
            if (empty($rows)) {
                break; // terminé
            }

            // Upsert par chunks
            foreach (array_chunk($rows, 1000) as $chunk) {
                $totalReceived += count($chunk);
                $totalUpserted += $this->upsertChunk($table, $pk, $chunk);
                $lastId = max($lastId, $this->maxPk($pk, $chunk));
            }

            // Mettre à jour le checkpoint après chaque lot
            DB::table('sync_checkpoints')->updateOrInsert(
                ['table_name' => $table],
                [
                    'last_id' => $lastId,
                    'status' => 'running',
                    'last_ts' => now(),
                    'updated_at' => now(),
                ]
            );
            
            // Délai entre les requêtes pour éviter le rate limiting
            if ($requestDelay > 0) {
                usleep($requestDelay * 1000); // Convertir ms en microsecondes
            }
        }

        $elapsed = (int) round((microtime(true) - $start) * 1000);
        DB::table('sync_checkpoints')->updateOrInsert(
            ['table_name' => $table],
            [
                'last_id' => $lastId,
                'status' => 'idle',
                'last_run_ms' => $elapsed,
                'last_ts' => now(),
                'error' => null,
                'updated_at' => now(),
            ]
        );

        Log::info("Sync table $table done", ['received' => $totalReceived, 'upserted' => $totalUpserted, 'ms' => $elapsed]);

        return ['received' => $totalReceived, 'upserted' => $totalUpserted, 'ms' => $elapsed];
    }

    private function upsertChunk(string $table, string $pk, array $rows): int
    {
        if (empty($rows)) return 0;
        // Nettoyage des clés non présentes
        $columns = array_unique(array_merge(...array_map('array_keys', $rows))); 
        $updateCols = array_values(array_diff($columns, [$pk]));
        return DB::table($table)->upsert($rows, [$pk], $updateCols);
    }

    private function maxPk(string $pk, array $rows): int
    {
        $m = 0;
        foreach ($rows as $r) {
            $id = (int) ($r[$pk] ?? 0);
            if ($id > $m) $m = $id;
        }
        return $m;
    }

    private function markError(string $table, float $start, string $message): void
    {
        $elapsed = (int) round((microtime(true) - $start) * 1000);
        DB::table('sync_checkpoints')->updateOrInsert(
            ['table_name' => $table],
            [
                'status' => 'error',
                'last_run_ms' => $elapsed,
                'error' => $message,
                'updated_at' => now(),
            ]
        );
        Log::error("Sync error $table: $message");
    }
}



