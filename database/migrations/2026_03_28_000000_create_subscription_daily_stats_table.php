<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->integer('operator_id')->nullable(); // NULL = ALL operators
            
            // Core counts
            $table->integer('activated_count')->default(0);
            $table->integer('deactivated_count')->default(0);
            $table->integer('active_snapshot')->default(0);
            
            // Activations by channel
            $table->integer('channel_cb')->default(0);
            $table->integer('channel_recharge')->default(0);
            $table->integer('channel_phone_balance')->default(0);
            $table->integer('channel_other')->default(0);
            
            // Plan distribution
            $table->integer('plan_daily')->default(0);
            $table->integer('plan_monthly')->default(0);
            $table->integer('plan_annual')->default(0);
            $table->integer('plan_other')->default(0);
            
            // Renewal/lifespan metrics
            $table->integer('expired_count')->default(0);
            $table->integer('renewed_count')->default(0);
            $table->bigInteger('total_lifespan_days')->default(0);
            $table->integer('lifespan_sub_count')->default(0);
            
            $table->timestamp('computed_at')->nullable();
            
            // Indexes
            $table->unique(['stat_date', 'operator_id'], 'sds_date_operator_unique');
            $table->index('stat_date', 'sds_date_idx');
            $table->index('operator_id', 'sds_operator_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_daily_stats');
    }
};
