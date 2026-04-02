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
        if (Schema::hasTable('eklektik_stats_dailies')) {
            return;
        }
        Schema::create('eklektik_stats_dailies', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('operator');
            $table->decimal('total_revenue_ttc', 15, 2)->default(0);
            $table->decimal('total_revenue_ht', 15, 2)->default(0);
            $table->decimal('ca_operateur', 15, 2)->default(0);
            $table->decimal('ca_agregateur', 15, 2)->default(0);
            $table->decimal('ca_bigdeal', 15, 2)->default(0);
            $table->integer('active_subscribers')->default(0);
            $table->decimal('billing_rate', 5, 2)->default(0);
            $table->decimal('bigdeal_share', 5, 2)->default(0);
            $table->integer('total_transactions')->default(0);
            $table->integer('new_subscriptions')->default(0);
            $table->integer('unsubscriptions')->default(0);
            $table->integer('renewals')->default(0);
            $table->integer('charges')->default(0);
            $table->timestamps();
            
            $table->index(['date', 'operator']);
            $table->unique(['date', 'operator']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eklektik_stats_dailies');
    }
};
