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
        if (!Schema::hasTable('eklektik_stats_daily') || Schema::hasColumn('eklektik_stats_daily', 'montant_total_ht')) {
            return;
        }
        Schema::table('eklektik_stats_daily', function (Blueprint $table) {
            // Colonnes pour le partage des revenus
            $table->decimal('montant_total_ht', 15, 2)->default(0)->after('revenu_ttc_tnd'); // Montant Total HT
            $table->decimal('part_operateur', 5, 2)->default(0)->after('montant_total_ht'); // Part Opérateur (%)
            $table->decimal('part_agregateur', 5, 2)->default(0)->after('part_operateur'); // Part Agrégateur (%)
            $table->decimal('part_bigdeal', 5, 2)->default(0)->after('part_agregateur'); // Part BigDeal (%)
            
            // CA par partenaire
            $table->decimal('ca_operateur', 15, 2)->default(0)->after('part_bigdeal'); // CA Opérateur
            $table->decimal('ca_agregateur', 15, 2)->default(0)->after('ca_operateur'); // CA Agrégateur
            $table->decimal('ca_bigdeal', 15, 2)->default(0)->after('ca_agregateur'); // CA BigDeal
            
            // Index pour les nouvelles colonnes
            $table->index(['operator', 'date', 'ca_bigdeal']);
            $table->index('montant_total_ht');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eklektik_stats_daily', function (Blueprint $table) {
            $table->dropColumn([
                'montant_total_ht',
                'part_operateur',
                'part_agregateur', 
                'part_bigdeal',
                'ca_operateur',
                'ca_agregateur',
                'ca_bigdeal'
            ]);
        });
    }
};