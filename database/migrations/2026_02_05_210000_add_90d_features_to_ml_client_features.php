<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Ajoute les colonnes pour les features 90 jours par opérateur
     */
    public function up(): void
    {
        Schema::table('ml_client_features', function (Blueprint $table) {
            // Features globales 90 jours
            $table->integer('total_90d_count')->default(0)->after('best_performing_operator');
            $table->decimal('total_90d_sum', 18, 3)->default(0)->after('total_90d_count');
            $table->decimal('total_90d_avg', 18, 3)->default(0)->after('total_90d_sum');
            $table->dateTime('last_tx_90d_at')->nullable()->after('total_90d_avg');
            
            // TIMWE 90 jours
            $table->integer('timwe_90d_count')->default(0)->after('last_tx_90d_at');
            $table->decimal('timwe_90d_sum', 18, 3)->default(0)->after('timwe_90d_count');
            $table->decimal('timwe_90d_avg', 18, 3)->default(0)->after('timwe_90d_sum');
            
            // ORANGE 90 jours
            $table->integer('orange_90d_count')->default(0)->after('timwe_90d_avg');
            $table->decimal('orange_90d_sum', 18, 3)->default(0)->after('orange_90d_count');
            $table->decimal('orange_90d_avg', 18, 3)->default(0)->after('orange_90d_sum');
            
            // TARAJI 90 jours
            $table->integer('taraji_90d_count')->default(0)->after('orange_90d_avg');
            $table->decimal('taraji_90d_sum', 18, 3)->default(0)->after('taraji_90d_count');
            $table->decimal('taraji_90d_avg', 18, 3)->default(0)->after('taraji_90d_sum');
            
            // TT 90 jours
            $table->integer('tt_90d_count')->default(0)->after('taraji_90d_avg');
            $table->decimal('tt_90d_sum', 18, 3)->default(0)->after('tt_90d_count');
            $table->decimal('tt_90d_avg', 18, 3)->default(0)->after('tt_90d_sum');
            
            // OOREDOO 90 jours
            $table->integer('ooredoo_90d_count')->default(0)->after('tt_90d_avg');
            $table->decimal('ooredoo_90d_sum', 18, 3)->default(0)->after('ooredoo_90d_count');
            $table->decimal('ooredoo_90d_avg', 18, 3)->default(0)->after('ooredoo_90d_sum');
            
            // DGV 90 jours
            $table->integer('dgv_90d_count')->default(0)->after('ooredoo_90d_avg');
            $table->decimal('dgv_90d_sum', 18, 3)->default(0)->after('dgv_90d_count');
            $table->decimal('dgv_90d_avg', 18, 3)->default(0)->after('dgv_90d_sum');
            
            // EKLEKTIK 90 jours
            $table->integer('eklektik_90d_count')->default(0)->after('dgv_90d_avg');
            $table->decimal('eklektik_90d_sum', 18, 3)->default(0)->after('eklektik_90d_count');
            $table->decimal('eklektik_90d_avg', 18, 3)->default(0)->after('eklektik_90d_sum');
            
            // EKLECTIC 90 jours (variante orthographique)
            $table->integer('eklectic_90d_count')->default(0)->after('eklektik_90d_avg');
            $table->decimal('eklectic_90d_sum', 18, 3)->default(0)->after('eklectic_90d_count');
            $table->decimal('eklectic_90d_avg', 18, 3)->default(0)->after('eklectic_90d_sum');
            
            // CLUB_PRIVILEGE 90 jours
            $table->integer('club_privilege_90d_count')->default(0)->after('eklectic_90d_avg');
            $table->decimal('club_privilege_90d_sum', 18, 3)->default(0)->after('club_privilege_90d_count');
            $table->decimal('club_privilege_90d_avg', 18, 3)->default(0)->after('club_privilege_90d_sum');
        });
        
        // Ajouter index pour améliorer les requêtes sur les features 90d
        Schema::table('ml_client_features', function (Blueprint $table) {
            $table->index('total_90d_count', 'idx_ml_90d_count');
            $table->index(['client_id', 'total_90d_count'], 'idx_ml_client_90d');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ml_client_features', function (Blueprint $table) {
            $table->dropIndex('idx_ml_90d_count');
            $table->dropIndex('idx_ml_client_90d');
            
            $table->dropColumn([
                'total_90d_count', 'total_90d_sum', 'total_90d_avg', 'last_tx_90d_at',
                'timwe_90d_count', 'timwe_90d_sum', 'timwe_90d_avg',
                'orange_90d_count', 'orange_90d_sum', 'orange_90d_avg',
                'taraji_90d_count', 'taraji_90d_sum', 'taraji_90d_avg',
                'tt_90d_count', 'tt_90d_sum', 'tt_90d_avg',
                'ooredoo_90d_count', 'ooredoo_90d_sum', 'ooredoo_90d_avg',
                'dgv_90d_count', 'dgv_90d_sum', 'dgv_90d_avg',
                'eklektik_90d_count', 'eklektik_90d_sum', 'eklektik_90d_avg',
                'eklectic_90d_count', 'eklectic_90d_sum', 'eklectic_90d_avg',
                'club_privilege_90d_count', 'club_privilege_90d_sum', 'club_privilege_90d_avg',
            ]);
        });
    }
};
