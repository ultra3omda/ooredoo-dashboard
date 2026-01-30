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
        Schema::create('ooredoo_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date')->unique()->comment('Date des statistiques');
            
            // Abonnements
            $table->integer('new_subscriptions')->default(0)->comment('Nouveaux abonnements');
            $table->integer('unsubscriptions')->default(0)->comment('Désabonnements');
            $table->integer('active_subscriptions')->default(0)->comment('Abonnements actifs');
            
            // Facturations
            $table->integer('total_billings')->default(0)->comment('Nombre total de facturations');
            $table->decimal('billing_rate', 5, 2)->default(0)->comment('Taux de facturation en %');
            
            // Revenus
            $table->decimal('revenue_tnd', 15, 2)->default(0)->comment('Revenu total en TND');
            
            // Clients
            $table->integer('total_clients')->default(0)->comment('Total clients actifs');
            
            // Détails par offre (JSON)
            $table->json('offers_breakdown')->nullable()->comment('Répartition par offre');
            
            $table->timestamps();
            
            // Index pour optimiser les requêtes
            $table->index('stat_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ooredoo_daily_stats');
    }
};

