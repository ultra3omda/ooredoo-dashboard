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
        if (!Schema::hasTable('eklektik_stats_daily') || Schema::hasColumn('eklektik_stats_daily', 'offer_name')) {
            return;
        }
        Schema::table('eklektik_stats_daily', function (Blueprint $table) {
            // Nouvelles colonnes basées sur l'interface Eklektik
            $table->integer('simchurn')->default(0)->after('unsubscriptions'); // Simchurn
            $table->bigInteger('rev_simchurn_cents')->default(0)->after('simchurn'); // Rev Simchurn en centimes
            $table->decimal('rev_simchurn_tnd', 15, 2)->default(0)->after('rev_simchurn_cents'); // Rev Simchurn TND
            $table->integer('nb_facturation')->default(0)->after('rev_simchurn_tnd'); // NB facturation
            $table->decimal('revenu_ttc_local', 15, 2)->default(0)->after('nb_facturation'); // Revenu TTC local
            $table->decimal('revenu_ttc_usd', 15, 2)->default(0)->after('revenu_ttc_local'); // Revenu TTC USD
            $table->decimal('revenu_ttc_tnd', 15, 2)->default(0)->after('revenu_ttc_usd'); // Revenu TTC TND
            
            // Colonnes pour identifier l'offre spécifique
            $table->string('offer_name', 255)->nullable()->after('service_name'); // Nom complet de l'offre
            $table->string('offer_type', 100)->nullable()->after('offer_name'); // Type d'offre (DAILY_WEB, etc.)
            
            // Index pour les nouvelles colonnes
            $table->index(['offer_name', 'date']);
            $table->index('offer_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eklektik_stats_daily', function (Blueprint $table) {
            $table->dropColumn([
                'simchurn',
                'rev_simchurn_cents', 
                'rev_simchurn_tnd',
                'nb_facturation',
                'revenu_ttc_local',
                'revenu_ttc_usd',
                'revenu_ttc_tnd',
                'offer_name',
                'offer_type'
            ]);
        });
    }
};