<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recrée les tables Eklektik tracking si elles ont été supprimées.
 * Évite l’erreur "Table eklektik_transactions_tracking doesn't exist" (EklektikCronController, EklektikKPIOptimizer).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('eklektik_transactions_tracking')) {
            Schema::create('eklektik_transactions_tracking', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('transaction_id')->index();
                $table->timestamp('processed_at')->useCurrent();
                $table->boolean('kpi_updated')->default(false);
                $table->string('processing_batch_id', 50)->nullable()->index();
                $table->json('processing_metadata')->nullable();
                $table->timestamps();
                $table->index(['processed_at', 'kpi_updated'], 'idx_processed_kpi');
                $table->index(['processing_batch_id', 'processed_at'], 'idx_batch_processed');
            });
            // FK optionnelle : décommenter si transactions_history existe et colonne = transaction_history_id
            // $table->foreign('transaction_id')->references('transaction_history_id')->on('transactions_history');
        }

        if (!Schema::hasTable('eklektik_notifications_tracking')) {
            Schema::create('eklektik_notifications_tracking', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('notification_id')->index();
                $table->timestamp('processed_at')->useCurrent();
                $table->boolean('kpi_updated')->default(false);
                $table->string('processing_batch_id', 50)->nullable()->index();
                $table->json('processing_metadata')->nullable();
                $table->timestamps();
                $table->index(['processed_at', 'kpi_updated'], 'idx_processed_kpi');
                $table->index(['processing_batch_id', 'processed_at'], 'idx_batch_processed');
            });
        }

        if (!Schema::hasTable('eklektik_stats_dailies')) {
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
    }

    public function down(): void
    {
        // Ne pas supprimer par défaut.
    }
};
