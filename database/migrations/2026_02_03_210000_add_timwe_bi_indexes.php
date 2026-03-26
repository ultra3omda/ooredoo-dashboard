<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Index BI obligatoires pour diagnostic Timwe.
     * Cible: summary < 20 ms, delivery < 20 ms, phones < 150 ms, lifetime < 50 ms.
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
        if (Schema::hasTable('timwe_diagnostic_daily_summary') && !$this->indexExists('timwe_diagnostic_daily_summary', 'idx_summary_date')) {
            Schema::table('timwe_diagnostic_daily_summary', function (Blueprint $table) {
                $table->index('stat_date', 'idx_summary_date');
            });
        }
        if (Schema::hasTable('timwe_diagnostic_daily_delivery') && !$this->indexExists('timwe_diagnostic_daily_delivery', 'idx_delivery_date')) {
            Schema::table('timwe_diagnostic_daily_delivery', function (Blueprint $table) {
                $table->index(['stat_date', 'delivery_code'], 'idx_delivery_date');
            });
        }
        if (Schema::hasTable('timwe_diagnostic_daily_phone') && !$this->indexExists('timwe_diagnostic_daily_phone', 'idx_phone_date')) {
            Schema::table('timwe_diagnostic_daily_phone', function (Blueprint $table) {
                $table->index(['stat_date', 'client_telephone'], 'idx_phone_date');
            });
        }
        // timwe_phone_lifetime_stats: client_telephone est déjà PRIMARY KEY (index existant)
    }

    public function down(): void
    {
        if (Schema::hasTable('timwe_diagnostic_daily_summary') && $this->indexExists('timwe_diagnostic_daily_summary', 'idx_summary_date')) {
            Schema::table('timwe_diagnostic_daily_summary', fn (Blueprint $t) => $t->dropIndex('idx_summary_date'));
        }
        if (Schema::hasTable('timwe_diagnostic_daily_delivery') && $this->indexExists('timwe_diagnostic_daily_delivery', 'idx_delivery_date')) {
            Schema::table('timwe_diagnostic_daily_delivery', fn (Blueprint $t) => $t->dropIndex('idx_delivery_date'));
        }
        if (Schema::hasTable('timwe_diagnostic_daily_phone') && $this->indexExists('timwe_diagnostic_daily_phone', 'idx_phone_date')) {
            Schema::table('timwe_diagnostic_daily_phone', fn (Blueprint $t) => $t->dropIndex('idx_phone_date'));
        }
    }
};
