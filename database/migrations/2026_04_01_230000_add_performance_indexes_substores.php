<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Performance indexes for Sub-Stores dashboard queries.
 * 
 * These indexes target the most expensive query patterns:
 * - Campaign filter: carte_recharge(campain_name, carte_recharge_used, client_id)
 * - Subscription lookups: client_abonnement(client_id, client_abonnement_expiration)
 * - Transaction lookups: history(client_id, time)
 * - Client store joins: client(sub_store, client_id)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Index 1: Campaign client ID resolution (biggest impact)
        // Note: client_id is TEXT type, requires prefix length
        $this->addIndexIfNotExists('carte_recharge', 'idx_cr_campaign_used_client', null,
            '`campain_name`(100), `carte_recharge_used`, `client_id`(50)');

        // Index 2: Subscription expiration lookups
        // Covers: WHERE client_id IN (...) AND client_abonnement_expiration > NOW()
        $this->addIndexIfNotExists('client_abonnement', 'idx_ca_client_expiration', ['client_id', 'client_abonnement_expiration']);

        // Index 3: Subscription creation date (cohorte queries)
        // Covers: WHERE client_abonnement_creation BETWEEN ? AND ?
        $this->addIndexIfNotExists('client_abonnement', 'idx_ca_client_creation', ['client_id', 'client_abonnement_creation']);

        // Index 4: Transaction client lookups
        // Covers: WHERE client_id IN (...) + history.time filtering
        $this->addIndexIfNotExists('history', 'idx_history_client_time', ['client_id', 'time']);

        // Index 5: Client sub_store join optimization
        // Covers: JOIN stores ON client.sub_store = stores.store_id
        $this->addIndexIfNotExists('client', 'idx_client_substore', ['sub_store', 'client_id']);

        // Index 6: History abonnement join
        // Covers: JOIN client_abonnement ON history.client_abonnement_id = ...
        $this->addIndexIfNotExists('history', 'idx_history_abonnement', ['client_abonnement_id']);

        // Index 7: carte_recharge_client client lookups
        $this->addIndexIfNotExists('carte_recharge_client', 'idx_crc_client', ['client_id']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('carte_recharge', 'idx_cr_campaign_used_client');
        $this->dropIndexIfExists('client_abonnement', 'idx_ca_client_expiration');
        $this->dropIndexIfExists('client_abonnement', 'idx_ca_client_creation');
        $this->dropIndexIfExists('history', 'idx_history_client_time');
        $this->dropIndexIfExists('client', 'idx_client_substore');
        $this->dropIndexIfExists('history', 'idx_history_abonnement');
        $this->dropIndexIfExists('carte_recharge_client', 'idx_crc_client');
    }

    private function addIndexIfNotExists(string $table, string $indexName, ?array $columns, ?string $rawCols = null): void
    {
        try {
            $exists = DB::select("SHOW INDEX FROM `$table` WHERE Key_name = ?", [$indexName]);
            if (empty($exists)) {
                if ($rawCols) {
                    $cols = $rawCols;
                } else {
                    $cols = implode(', ', array_map(fn($c) => "`$c`", $columns));
                }
                DB::statement("ALTER TABLE `$table` ADD INDEX `$indexName` ($cols)");
            }
        } catch (\Exception $e) {
            \Log::warning("Index creation skipped for $table.$indexName: " . $e->getMessage());
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        try {
            $exists = DB::select("SHOW INDEX FROM `$table` WHERE Key_name = ?", [$indexName]);
            if (!empty($exists)) {
                DB::statement("ALTER TABLE `$table` DROP INDEX `$indexName`");
            }
        } catch (\Exception $e) {
            \Log::warning("Index drop skipped for $table.$indexName: " . $e->getMessage());
        }
    }
};
