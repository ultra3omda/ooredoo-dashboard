<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->integer('operator_id')->nullable(); // NULL = ALL operators

            // Transaction counts
            $table->integer('transaction_count')->default(0);
            $table->integer('distinct_users')->default(0);

            // Cohort metrics (transactions from subs created on same day)
            $table->integer('cohort_transaction_count')->default(0);
            $table->integer('cohort_distinct_users')->default(0);

            // Merchant metrics
            $table->integer('active_merchants')->default(0);

            // Transaction breakdowns by operator (JSON for per-operator names)
            $table->json('by_operator')->nullable();
            // Transaction breakdowns by plan type
            $table->json('by_plan')->nullable();

            $table->timestamp('computed_at')->nullable();

            $table->unique(['stat_date', 'operator_id'], 'tds_date_operator_unique');
            $table->index('stat_date', 'tds_date_idx');
            $table->index('operator_id', 'tds_operator_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_daily_stats');
    }
};
