<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Index pour requêtes GROUP BY / WHERE sur les tables diagnostic Timwe.
     * Objectif: summary < 50 ms, delivery < 50 ms, phones page < 200 ms.
     */
    private function indexExists(string $table, string $name): bool
    {
        $db = Schema::getConnection()->getDatabaseName();
        $r = DB::selectOne(
            "SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$db, $table, $name]
        );
        return $r !== null;
    }

    public function up(): void
    {
        if (Schema::hasTable('timwe_diagnostic_daily_delivery')
            && !$this->indexExists('timwe_diagnostic_daily_delivery', 'idx_delivery_stat_code')) {
            Schema::table('timwe_diagnostic_daily_delivery', function (Blueprint $table) {
                $table->index(['stat_date', 'delivery_code'], 'idx_delivery_stat_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('timwe_diagnostic_daily_delivery')
            && $this->indexExists('timwe_diagnostic_daily_delivery', 'idx_delivery_stat_code')) {
            Schema::table('timwe_diagnostic_daily_delivery', function (Blueprint $table) {
                $table->dropIndex('idx_delivery_stat_code');
            });
        }
    }
};
