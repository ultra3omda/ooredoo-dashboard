<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Build90dFeaturesCommand extends Command
{
    protected $signature = 'ml:build-90d-features
                            {--days=90 : Fenêtre de lookback en jours}
                            {--chunk=1000 : Nombre de clients par chunk}
                            {--dry-run : Simulation sans écriture}';

    protected $description = 'Construit les features ML 90 jours depuis tx_daily_agg';

    const SUPPORTED_STATUSES = ['TIMWE', 'ORANGE', 'TARAJI', 'TT', 'OOREDOO', 'DGV', 'EKLEKTIK', 'EKLECTIC', 'CLUB_PRIVILEGE'];

    public function handle(): int
    {
        $startTime = microtime(true);
        $lookbackDays = (int) $this->option('days');
        $chunkSize = (int) $this->option('chunk');
        $dryRun = $this->option('dry-run');

        $cutoffDate = Carbon::now()->subDays($lookbackDays)->format('Y-m-d');

        $this->info("🔧 Construction des features {$lookbackDays} jours");
        $this->info("   Cutoff date: {$cutoffDate}");
        $this->info("   Chunk size: {$chunkSize} clients");
        if ($dryRun) {
            $this->warn("   ⚠️  DRY RUN - Aucune écriture");
        }
        $this->newLine();

        // 1) COMPTER LES CLIENTS ACTIFS
        $this->info("📋 Récupération des clients actifs...");
        
        $totalClients = DB::table('client')
            ->whereNotNull('client_id')
            ->where('client_id', '>', 0)
            ->count();

        $this->info("   ✅ {$totalClients} clients trouvés");

        if ($totalClients === 0) {
            $this->warn("Aucun client à traiter !");
            return 0;
        }

        // 2) TRAITER PAR CHUNKS (streaming pour éviter memory overflow)
        $totalChunks = (int) ceil($totalClients / $chunkSize);
        $processedClients = 0;
        $updatedClients = 0;
        $chunkIndex = 0;

        $this->info("\n🔄 Traitement en ~{$totalChunks} chunks...\n");

        DB::table('client')
            ->whereNotNull('client_id')
            ->where('client_id', '>', 0)
            ->orderBy('client_id')
            ->chunkById($chunkSize, function ($clients) use (&$chunkIndex, &$processedClients, &$updatedClients, $totalChunks, $cutoffDate, $dryRun) {
                $chunkIndex++;
                $clientChunk = $clients->pluck('client_id')->toArray();
            $chunkStartTime = microtime(true);

            $this->info("📦 Chunk {$chunkIndex}/{$totalChunks} (" . count($clientChunk) . " clients)");

            // 3) CHARGER LES AGRÉGATS 90 JOURS POUR CE CHUNK
            $queryStartTime = microtime(true);
            
            $aggregates = DB::table('tx_daily_agg')
                ->whereIn('client_id', $clientChunk)
                ->where('day', '>=', $cutoffDate)
                ->select('client_id', 'status', 
                    DB::raw('SUM(tx_count) as tx_count'),
                    DB::raw('SUM(amount_sum) as amount_sum'),
                    DB::raw('AVG(amount_avg) as amount_avg_daily'),
                    DB::raw('MAX(last_tx_at) as last_tx_at'))
                ->groupBy('client_id', 'status')
                ->get();

            $queryTime = round((microtime(true) - $queryStartTime) * 1000, 2);
            $this->info("   ⚡ Query: {$queryTime}ms ({$aggregates->count()} agrégats)");

            // 4) ORGANISER PAR CLIENT
            $clientFeatures = [];
            foreach ($aggregates as $agg) {
                $clientId = $agg->client_id;
                if (!isset($clientFeatures[$clientId])) {
                    $clientFeatures[$clientId] = $this->initFeatures();
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

            // 5) CALCULER MOYENNES GLOBALES
            foreach ($clientFeatures as $clientId => $features) {
                if ($features['total_90d_count'] > 0) {
                    $clientFeatures[$clientId]['total_90d_avg'] = 
                        round($features['total_90d_sum'] / $features['total_90d_count'], 3);
                }
            }

            // 6) METTRE À JOUR ml_client_features
            if (!$dryRun) {
                $updateStartTime = microtime(true);
                $updated = $this->updateClientFeatures($clientChunk, $clientFeatures);
                $updateTime = round((microtime(true) - $updateStartTime) * 1000, 2);
                
                $this->info("   💾 Update: {$updateTime}ms ({$updated} clients)");
                $updatedClients += $updated;
            } else {
                $this->info("   ⚠️  DRY RUN - Skip update");
            }

            $chunkTime = round((microtime(true) - $chunkStartTime) * 1000, 2);
            $this->info("   ⏱️  Total chunk: {$chunkTime}ms\n");

            $processedClients += count($clientChunk);
            }, 'client_id'); // Fin du chunkById

        $totalTime = round(microtime(true) - $startTime, 2);
        
        $this->newLine();
        $this->info("=" .str_repeat("=", 60));
        $this->info("✅ Features {$lookbackDays}d construites !");
        $this->info("   👥 Clients traités: {$processedClients}");
        if (!$dryRun) {
            $this->info("   💾 Clients mis à jour: {$updatedClients}");
        }
        $this->info("   ⏱️  Temps total: {$totalTime}s");
        $this->info("   📊 Vitesse: " . round($processedClients / $totalTime, 2) . " clients/s");
        $this->info("=" .str_repeat("=", 60));

        return 0;
    }

    /**
     * Initialise un tableau de features vide
     */
    private function initFeatures(): array
    {
        $features = [
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
     * Met à jour ml_client_features pour un chunk de clients
     * 
     * Utilise CASE WHEN pour update massif en une seule requête
     */
    private function updateClientFeatures(array $clientIds, array $clientFeatures): int
    {
        if (empty($clientFeatures)) {
            // Mettre à zéro les clients sans transactions
            return $this->resetClientsToZero($clientIds);
        }

        // Construire une requête UPDATE avec CASE WHEN pour chaque colonne
        $columns = array_keys($this->initFeatures());
        $caseClauses = [];

        foreach ($columns as $col) {
            $cases = [];
            foreach ($clientFeatures as $clientId => $features) {
                // Pour les colonnes datetime, utiliser null par défaut au lieu de 0
                $value = $features[$col] ?? ($col === 'last_tx_90d_at' ? null : 0);
                
                if ($value === null) {
                    $cases[] = "WHEN client_id = {$clientId} THEN NULL";
                } elseif (is_string($value)) {
                    $value = DB::connection()->getPdo()->quote($value);
                    $cases[] = "WHEN client_id = {$clientId} THEN {$value}";
                } else {
                    $cases[] = "WHEN client_id = {$clientId} THEN {$value}";
                }
            }
            
            if (!empty($cases)) {
                $caseClause = "CASE " . implode(' ', $cases) . " ELSE {$col} END";
                $caseClauses[] = "{$col} = {$caseClause}";
            }
        }

        if (empty($caseClauses)) {
            return 0;
        }

        // Mettre à jour les clients qui ont des features
        $updateSql = "
            UPDATE ml_client_features
            SET " . implode(', ', $caseClauses) . "
            WHERE client_id IN (" . implode(',', array_keys($clientFeatures)) . ")
        ";

        DB::statement($updateSql);

        // Réinitialiser les clients du chunk qui n'ont pas de features
        $clientsWithoutFeatures = array_diff($clientIds, array_keys($clientFeatures));
        if (!empty($clientsWithoutFeatures)) {
            $this->resetClientsToZero($clientsWithoutFeatures);
        }

        return count($clientFeatures);
    }

    /**
     * Réinitialise les features à zéro pour les clients sans transactions
     */
    private function resetClientsToZero(array $clientIds): int
    {
        if (empty($clientIds)) {
            return 0;
        }

        $columns = array_keys($this->initFeatures());
        $sets = [];
        
        foreach ($columns as $col) {
            if ($col === 'last_tx_90d_at') {
                $sets[] = "{$col} = NULL";
            } else {
                $sets[] = "{$col} = 0";
            }
        }

        $sql = "
            UPDATE ml_client_features
            SET " . implode(', ', $sets) . "
            WHERE client_id IN (" . implode(',', $clientIds) . ")
        ";

        DB::statement($sql);
        
        return count($clientIds);
    }
}
