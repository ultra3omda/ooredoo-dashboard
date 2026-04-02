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
        if (Schema::hasTable('eklektik_kpis_cache')) {
            return;
        }
        Schema::create('eklektik_kpis_cache', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('operator', 50)->default('ALL')->index();
            $table->string('kpi_type', 50)->index(); // 'billing_rate', 'revenue', 'active_subscriptions', etc.
            $table->decimal('total_value', 15, 2)->default(0);
            $table->decimal('daily_value', 15, 2)->default(0);
            $table->integer('notifications_count')->default(0);
            $table->timestamp('last_updated')->useCurrent();
            $table->timestamps();
            
            // Index composite pour les requêtes fréquentes
            $table->unique(['date', 'operator', 'kpi_type'], 'unique_kpi_per_date_operator');
            $table->index(['date', 'operator'], 'idx_date_operator');
            $table->index(['kpi_type', 'date'], 'idx_kpi_type_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eklektik_kpis_cache');
    }
};