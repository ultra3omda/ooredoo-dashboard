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
        if (Schema::hasTable('eklektik_stats_daily')) {
            return;
        }
        Schema::create('eklektik_stats_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('operator', 50); // TT, Orange, Taraji
            $table->integer('offre_id'); // 11, 82, 26
            $table->string('service_name', 255); // "Club privilège by TT_DAILY_SMS_300"
            
            // KPIs Eklektik
            $table->integer('new_subscriptions')->default(0); // Nouveaux abonnements
            $table->integer('renewals')->default(0); // Renouvellements
            $table->integer('charges')->default(0); // Facturations
            $table->integer('unsubscriptions')->default(0); // Désabonnements
            $table->integer('active_subscribers')->default(0); // Abonnés actifs
            $table->bigInteger('revenue_cents')->default(0); // Revenus en centimes
            $table->decimal('billing_rate', 5, 2)->default(0); // Taux de facturation (%)
            $table->decimal('total_revenue', 15, 2)->default(0); // Chiffre d'affaires total (TND)
            $table->decimal('average_price', 10, 3)->default(0); // Prix moyen
            $table->decimal('total_amount', 15, 3)->default(0); // Montant total
            
            // Métadonnées
            $table->string('source', 50)->default('eklektik_api'); // Source des données
            $table->timestamp('synced_at')->nullable(); // Date de synchronisation
            $table->timestamps();
            
            // Index pour les performances
            $table->index(['date', 'operator']);
            $table->index(['operator', 'offre_id']);
            $table->index('date');
            $table->unique(['date', 'operator', 'offre_id']); // Éviter les doublons
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eklektik_stats_daily');
    }
};