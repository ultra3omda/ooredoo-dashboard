<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BuildHistoricalFeaturesCommand extends Command
{
    protected $signature = 'ml:build-historical-features
                            {--start-date= : Date de début (YYYY-MM-DD)}
                            {--end-date= : Date de fin (YYYY-MM-DD)}
                            {--days=90 : Fenêtre de lookback en jours}
                            {--chunk=1000 : Nombre de clients par chunk}
                            {--batch-dates=30 : Nombre de dates à traiter en parallèle}
                            {--dry-run : Simulation sans écriture}';

    protected $description = 'Construit les features ML historiques rapidement depuis tx_daily_agg';

    const SUPPORTED_STATUSES = ['TIMWE', 'ORANGE', 'TARAJI', 'TT', 'OOREDOO', 'DGV', 'EKLEKTIK', 'EKLECTIC', 'CLUB_PRIVILEGE'];

    public function handle(): int
    {
        // Augmenter memory limit
        @ini_set('memory_limit', '512M');
        
        // Configurer timeouts MySQL pour connexions longues
        config(['database.connections.mysql.options' => [
            \PDO::ATTR_TIMEOUT => 300,  // 5 minutes
            \PDO::ATTR_PERSISTENT => false,
            \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ]]);
        
        $startTime = microtime(true);
        $lookbackDays = (int) $this->option('days');
        $chunkSize = (int) $this->option('chunk');
        $batchDates = (int) $this->option('batch-dates');
        $dryRun = $this->option('dry-run');

        $startDate = $this->option('start-date') 
            ? Carbon::parse($this->option('start-date'))
            : Carbon::now()->subYears(2);
            
        $endDate = $this->option('end-date')
            ? Carbon::parse($this->option('end-date'))
            : Carbon::now();

        $this->info("🔧 Construction features historiques depuis tx_daily_agg");
        $this->info("   Période: {$startDate->toDateString()} → {$endDate->toDateString()}");
        $this->info("   Lookback: {$lookbackDays} jours");
        $this->info("   Chunk clients: {$chunkSize}");
        $this->info("   Batch dates: {$batchDates}");
        if ($dryRun) {
            $this->warn("   ⚠️  DRY RUN - Aucune écriture");
        }
        $this->newLine();

        // Vérifier que tx_daily_agg contient des données
        $aggCount = DB::table('tx_daily_agg')->count();
        if ($aggCount === 0) {
            $this->error("❌ tx_daily_agg est vide ! Lancez d'abord ml:tx-daily-ingest");
            return 1;
        }
        
        $this->info("✅ tx_daily_agg contient " . number_format($aggCount) . " agrégats");

        // Générer la liste des dates à traiter
        $dates = [];
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            // Vérifier si déjà traité (sauf si force)
            $exists = DB::table('ml_client_features')
                ->where('calculation_date', $currentDate->toDateString())
                ->exists();
                
            if (!$exists) {
                $dates[] = $currentDate->copy();
            }
            
            $currentDate->addDay();
        }

        $totalDates = count($dates);
        if ($totalDates === 0) {
            $this->info("✅ Toutes les dates sont déjà traitées !");
            return 0;
        }

        $this->info("📅 {$totalDates} dates à traiter");
        $this->newLine();

        // Traiter par batch de dates
        $processedDates = 0;
        $totalClients = 0;
        $failedDates = [];
        
        foreach (array_chunk($dates, $batchDates) as $batchIndex => $dateBatch) {
            $batchStartTime = microtime(true);
            
            $this->info("📦 Batch dates " . ($batchIndex + 1) . "/" . ceil($totalDates / $batchDates));
            $this->info("   Dates: " . $dateBatch[0]->toDateString() . " → " . end($dateBatch)->toDateString());
            
            foreach ($dateBatch as $calculationDate) {
                // Reconnexion DB avant chaque date (éviter timeouts)
                try {
                    DB::reconnect();
                } catch (\Exception $e) {
                    $this->warn("   ⚠️  Reconnexion DB échouée : " . $e->getMessage());
                    sleep(2);
                    try {
                        DB::reconnect();
                    } catch (\Exception $e2) {
                        $this->error("   ❌ DB inaccessible, skip date {$calculationDate->toDateString()}");
                        $failedDates[] = $calculationDate->toDateString();
                        continue;
                    }
                }
                $dateStartTime = microtime(true);
                
                $cutoffDate = $calculationDate->copy()->subDays($lookbackDays);
                
                // OPTIMISATION CRITIQUE : Ne traiter QUE les clients actifs sur cette période
                // Au lieu de scanner tous les 269k clients
                $this->line("   📊 {$calculationDate->toDateString()} : Finding active clients...");
                
                $activeClientIds = DB::table('tx_daily_agg')
                    ->select('client_id')
                    ->where('day', '>=', $cutoffDate->toDateString())
                    ->where('day', '<=', $calculationDate->toDateString())
                    ->distinct()
                    ->pluck('client_id')
                    ->toArray();
                
                if (empty($activeClientIds)) {
                    $this->line("      ⏭️  Aucun client actif, skip");
                    continue;
                }
                
                $activeCount = count($activeClientIds);
                $this->line("      ✓ {$activeCount} clients actifs");
                
                // Traiter par chunks de clients actifs uniquement
                $processedInDate = 0;
                foreach (array_chunk($activeClientIds, $chunkSize) as $clientChunk) {
                    $features = $this->calculateFeaturesForClients(
                        $clientChunk,
                        $calculationDate,
                        $lookbackDays
                    );

                    if (!$dryRun && !empty($features)) {
                        $this->insertOrUpdateFeatures($features, $calculationDate);
                        $processedInDate += count($features);
                    }
                    
                    // Libérer mémoire
                    unset($features);
                    gc_collect_cycles();
                }

                $dateTime = round((microtime(true) - $dateStartTime) * 1000, 2);
                $this->line("      ✅ {$processedInDate} clients en {$dateTime}ms");
                
                $totalClients += $processedInDate;
                $processedDates++;
            }
            
            $batchTime = round(microtime(true) - $batchStartTime, 2);
            $this->info("   ⏱️  Batch terminé en {$batchTime}s");
            $this->newLine();
        }

        $totalTime = round(microtime(true) - $startTime, 2);
        
        $this->newLine();
        $this->info("=" .str_repeat("=", 60));
        $this->info("✅ Features historiques construites !");
        $this->info("   📅 Dates traitées: {$processedDates}");
        $this->info("   👥 Clients traités: " . number_format($totalClients));
        $this->info("   ⏱️  Temps total: {$totalTime}s");
        if ($processedDates > 0) {
            $this->info("   📊 Vitesse: " . round($totalClients / $totalTime, 2) . " clients/s");
            $this->info("   📊 Temps/date: " . round($totalTime / $processedDates, 2) . "s");
        }
        
        if (!empty($failedDates)) {
            $this->newLine();
            $this->warn("⚠️  Dates échouées (" . count($failedDates) . ") :");
            foreach ($failedDates as $date) {
                $this->line("   - {$date}");
            }
            $this->info("\n💡 Relancez avec les mêmes paramètres pour réessayer les dates manquantes");
        }
        
        $this->info("=" .str_repeat("=", 60));

        return empty($failedDates) ? 0 : 1;
    }

    /**
     * Calcule les features pour un chunk de clients à une date donnée
     */
    private function calculateFeaturesForClients(array $clientIds, Carbon $calculationDate, int $lookbackDays): array
    {
        $cutoffDate = $calculationDate->copy()->subDays($lookbackDays);
        
        // Requête optimisée : une seule query pour tous les clients du chunk
        $aggregates = DB::table('tx_daily_agg')
            ->selectRaw('
                client_id,
                status,
                SUM(tx_count) as tx_count,
                SUM(amount_sum) as amount_sum,
                AVG(amount_avg) as amount_avg_daily,
                MAX(last_tx_at) as last_tx_at
            ')
            ->whereIn('client_id', $clientIds)
            ->where('day', '>=', $cutoffDate->toDateString())
            ->where('day', '<=', $calculationDate->toDateString())
            ->groupBy(['client_id', 'status'])
            ->get();

        // Organiser par client
        $clientFeatures = [];
        foreach ($aggregates as $agg) {
            $clientId = $agg->client_id;
            if (!isset($clientFeatures[$clientId])) {
                $clientFeatures[$clientId] = $this->initFeatures($clientId, $calculationDate);
            }
            
            $status = strtolower($agg->status);
            $prefix = $status . '_90d_';
            
            $clientFeatures[$clientId][$prefix . 'count'] = (int) $agg->tx_count;
            $clientFeatures[$clientId][$prefix . 'sum'] = (float) $agg->amount_sum;
            $clientFeatures[$clientId][$prefix . 'avg'] = (float) $agg->amount_avg_daily;
            
            // Totaux
            $clientFeatures[$clientId]['total_90d_count'] += (int) $agg->tx_count;
            $clientFeatures[$clientId]['total_90d_sum'] += (float) $agg->amount_sum;
            
            if (!$clientFeatures[$clientId]['last_tx_90d_at'] || 
                ($agg->last_tx_at && $agg->last_tx_at > $clientFeatures[$clientId]['last_tx_90d_at'])) {
                $clientFeatures[$clientId]['last_tx_90d_at'] = $agg->last_tx_at;
            }
        }

        // Calculer moyennes globales
        foreach ($clientFeatures as $clientId => $features) {
            if ($features['total_90d_count'] > 0) {
                $clientFeatures[$clientId]['total_90d_avg'] = 
                    round($features['total_90d_sum'] / $features['total_90d_count'], 3);
            }
        }

        return $clientFeatures;
    }

    /**
     * Initialise un tableau de features vide
     */
    private function initFeatures(int $clientId, Carbon $calculationDate): array
    {
        $features = [
            'client_id' => $clientId,
            'calculation_date' => $calculationDate->toDateString(),
            'total_90d_count' => 0,
            'total_90d_sum' => 0.0,
            'total_90d_avg' => 0.0,
            'last_tx_90d_at' => null,
        ];

        foreach (self::SUPPORTED_STATUSES as $status) {
            $prefix = strtolower($status) . '_90d_';
            $features[$prefix . 'count'] = 0;
            $features[$prefix . 'sum'] = 0.0;
            $features[$prefix . 'avg'] = 0.0;
        }

        return $features;
    }

    /**
     * Insert ou update les features
     */
    private function insertOrUpdateFeatures(array $clientFeatures, Carbon $calculationDate): int
    {
        if (empty($clientFeatures)) {
            return 0;
        }

        $inserted = 0;
        $now = now()->toDateTimeString();
        
        // Réduire à 100 lignes par batch pour éviter lock timeouts
        foreach (array_chunk($clientFeatures, 100) as $chunk) {
            $maxRetries = 3;
            $retry = 0;
            $success = false;
            
            while (!$success && $retry < $maxRetries) {
                try {
                    $values = [];
                    $bindings = [];

                    foreach ($chunk as $features) {
                        $placeholders = [];
                        // Ajouter les features
                        foreach ($features as $value) {
                            $placeholders[] = '?';
                            $bindings[] = $value;
                        }
                        // Ajouter created_at et updated_at
                        $placeholders[] = '?'; // created_at
                        $placeholders[] = '?'; // updated_at
                        $bindings[] = $now;
                        $bindings[] = $now;
                        
                        $values[] = '(' . implode(',', $placeholders) . ')';
                    }

                    $columns = array_keys($this->initFeatures(0, $calculationDate));
                    $columnsStr = implode(',', $columns) . ', created_at, updated_at';
                    $valuesStr = implode(',', $values);

                    // Construire les updates
                    $updates = [];
                    foreach ($columns as $col) {
                        if (!in_array($col, ['client_id', 'calculation_date'])) {
                            $updates[] = "{$col} = VALUES({$col})";
                        }
                    }
                    $updates[] = "updated_at = VALUES(updated_at)";
                    $updatesStr = implode(', ', $updates);

                    $sql = "
                        INSERT INTO ml_client_features ({$columnsStr})
                        VALUES {$valuesStr}
                        ON DUPLICATE KEY UPDATE {$updatesStr}
                    ";

                    DB::statement($sql, $bindings);
                    $inserted += count($chunk);
                    $success = true;
                    
                } catch (\Illuminate\Database\QueryException $e) {
                    $retry++;
                    if ($retry < $maxRetries && str_contains($e->getMessage(), 'Lock wait timeout')) {
                        // Attendre un peu avant de réessayer (délai exponentiel)
                        usleep(500000 * $retry); // 0.5s, 1s, 1.5s
                        continue;
                    }
                    // Si ce n'est pas un lock timeout ou max retries atteint, on relance
                    throw $e;
                }
            }
        }

        return $inserted;
    }
}
