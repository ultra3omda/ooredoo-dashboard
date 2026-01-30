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
        Schema::create('timwe_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date')->unique();
            $table->integer('new_subscriptions')->default(0);
            $table->integer('unsubscriptions')->default(0);
            $table->integer('simchurn')->default(0);
            $table->decimal('simchurn_revenue', 15, 3)->default(0);
            $table->integer('active_subscriptions')->default(0);
            $table->integer('total_billings')->default(0);
            $table->decimal('billing_rate', 8, 2)->default(0);
            $table->decimal('revenue_tnd', 15, 3)->default(0);
            $table->decimal('revenue_usd', 15, 3)->default(0);
            $table->integer('total_clients')->default(0);
            $table->json('offers_breakdown')->nullable(); // Détail par offre
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->index('stat_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timwe_daily_stats');
    }
};

