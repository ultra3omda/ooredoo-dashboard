<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Helper to create index if not exists (MySQL)
        $createIndex = function (string $table, string $indexName, string $columns) {
            try {
                $exists = DB::selectOne(
                    'SELECT COUNT(1) as cnt FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
                    [$table, $indexName]
                );
                if (!$exists || ((int)($exists->cnt ?? 0)) === 0) {
                    DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` ({$columns})");
                }
            } catch (\Throwable $th) {
                // Journaliser mais ne pas casser la migration
                // \Log::warning('Index creation skipped', [
                //     'table' => $table,
                //     'index' => $indexName,
                //     'error' => $th->getMessage(),
                // ]);
            }
        };

        // history
        $createIndex('history', 'idx_history_time', '`time`');
        $createIndex('history', 'idx_history_client_abonnement_id', '`client_abonnement_id`');
        $createIndex('history', 'idx_history_partner_location_id', '`partner_location_id`');

        // client_abonnement
        $createIndex('client_abonnement', 'idx_ca_creation', '`client_abonnement_creation`');
        $createIndex('client_abonnement', 'idx_ca_expiration', '`client_abonnement_expiration`');
        $createIndex('client_abonnement', 'idx_ca_client_id', '`client_id`');
        $createIndex('client_abonnement', 'idx_ca_cpm_id', '`country_payments_methods_id`');

        // country_payments_methods
        $createIndex('country_payments_methods', 'idx_cpm_name', '`country_payments_methods_name`');

        // promotion
        $createIndex('promotion', 'idx_promotion_partner_id', '`partner_id`');

        // partner_location
        $createIndex('partner_location', 'idx_partner_location_partner_id', '`partner_id`');

        // partner
        $createIndex('partner', 'idx_partner_category_id', '`partner_category_id`');
        $createIndex('partner', 'idx_partner_id', '`partner_id`');
    }

    public function down(): void
    {
        // Optionnel: supprimer les index
        $dropIndex = function (string $table, string $indexName) {
            try {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
            } catch (\Throwable $th) {
                // ignorer
            }
        };

        $dropIndex('history', 'idx_history_time');
        $dropIndex('history', 'idx_history_client_abonnement_id');
        $dropIndex('history', 'idx_history_partner_location_id');
        $dropIndex('client_abonnement', 'idx_ca_creation');
        $dropIndex('client_abonnement', 'idx_ca_expiration');
        $dropIndex('client_abonnement', 'idx_ca_client_id');
        $dropIndex('client_abonnement', 'idx_ca_cpm_id');
        $dropIndex('country_payments_methods', 'idx_cpm_name');
        $dropIndex('promotion', 'idx_promotion_partner_id');
        $dropIndex('partner_location', 'idx_partner_location_partner_id');
        $dropIndex('partner', 'idx_partner_category_id');
        $dropIndex('partner', 'idx_partner_id');
    }
};



