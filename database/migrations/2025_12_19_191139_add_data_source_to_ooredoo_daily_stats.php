<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('ooredoo_daily_stats') || Schema::hasColumn('ooredoo_daily_stats', 'notes')) {
            return;
        }
        Schema::table('ooredoo_daily_stats', function (Blueprint $table) {
            if (!Schema::hasColumn('ooredoo_daily_stats', 'data_source')) {
                $table->enum('data_source', ['officiel_dgv', 'calculé'])->default('calculé')->after('billing_rate');
            }
            $table->text('notes')->nullable()->after('data_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ooredoo_daily_stats', function (Blueprint $table) {
            $table->dropColumn(['data_source', 'notes']);
        });
    }
};
