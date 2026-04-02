<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = [
            ['history', 'idx_history_client_id', 'client_id'],
            ['client', 'idx_client_sub_store', 'sub_store'],
            ['client', 'idx_client_active', 'client_active'],
            ['promotion', 'idx_promotion_active', 'promotion_active'],
            ['partner', 'idx_partner_active', 'partener_active'],
            ['carte_recharge', 'idx_cr_stores', 'stores'],
            ['carte_recharge', 'idx_cr_campain_name', 'campain_name'],
            ['stores', 'idx_stores_name', 'store_name'],
            ['stores', 'idx_stores_active', 'store_active'],
        ];

        foreach ($indexes as [$table, $indexName, $column]) {
            try {
                $exists = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
                if (empty($exists)) {
                    DB::statement("CREATE INDEX `{$indexName}` ON `{$table}` (`{$column}`)");
                    Log::info("Created index {$indexName} on {$table}.{$column}");
                }
            } catch (\Exception $e) {
                Log::warning("Index {$indexName} creation failed: " . $e->getMessage());
            }
        }

        // Composite indexes for common query patterns
        $composites = [
            ['history', 'idx_history_client_time', ['client_id', 'time']],
            ['history', 'idx_history_promo_client', ['promotion_id', 'client_id']],
            ['client', 'idx_client_substore_active', ['sub_store', 'client_active']],
        ];

        foreach ($composites as [$table, $indexName, $columns]) {
            try {
                $exists = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
                if (empty($exists)) {
                    $colList = implode('`, `', $columns);
                    DB::statement("CREATE INDEX `{$indexName}` ON `{$table}` (`{$colList}`)");
                    Log::info("Created composite index {$indexName} on {$table}");
                }
            } catch (\Exception $e) {
                Log::warning("Composite index {$indexName} creation failed: " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        $indexes = [
            ['history', 'idx_history_client_id'],
            ['history', 'idx_history_client_time'],
            ['history', 'idx_history_promo_client'],
            ['client', 'idx_client_sub_store'],
            ['client', 'idx_client_active'],
            ['client', 'idx_client_substore_active'],
            ['promotion', 'idx_promotion_active'],
            ['partner', 'idx_partner_active'],
            ['carte_recharge', 'idx_cr_stores'],
            ['carte_recharge', 'idx_cr_campain_name'],
            ['stores', 'idx_stores_name'],
            ['stores', 'idx_stores_active'],
        ];

        foreach ($indexes as [$table, $indexName]) {
            try {
                DB::statement("DROP INDEX `{$indexName}` ON `{$table}`");
            } catch (\Exception $e) {
                // index may not exist
            }
        }
    }
};
