<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les colonnes manquantes à eklektik_sync_tracking si la table
 * a été créée par la migration de secours (sans server_info, memory_usage, execution_user).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('eklektik_sync_tracking')) {
            return;
        }

        Schema::table('eklektik_sync_tracking', function (Blueprint $table) {
            if (!Schema::hasColumn('eklektik_sync_tracking', 'server_info')) {
                $table->string('server_info', 100)->nullable()->after('source');
            }
            if (!Schema::hasColumn('eklektik_sync_tracking', 'memory_usage')) {
                $table->string('memory_usage', 20)->nullable()->after('server_info');
            }
            if (!Schema::hasColumn('eklektik_sync_tracking', 'execution_user')) {
                $table->string('execution_user', 50)->nullable()->after('memory_usage');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('eklektik_sync_tracking')) {
            return;
        }
        Schema::table('eklektik_sync_tracking', function (Blueprint $table) {
            $columns = ['server_info', 'memory_usage', 'execution_user'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('eklektik_sync_tracking', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
