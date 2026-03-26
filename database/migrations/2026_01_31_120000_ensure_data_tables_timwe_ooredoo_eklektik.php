<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recrée les tables Timwe / Ooredoo-DGV / Eklektik si elles ont été supprimées
 * (ex. après migrate:fresh ou rollback). Ne fait rien si les tables existent déjà.
 * Les migrations ML (ml_*) ne suppriment pas ces tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('timwe_daily_stats')) {
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
                $table->json('offers_breakdown')->nullable();
                $table->timestamp('calculated_at');
                $table->timestamps();
                $table->index('stat_date');
            });
        }

        if (!Schema::hasTable('ooredoo_daily_stats')) {
            Schema::create('ooredoo_daily_stats', function (Blueprint $table) {
                $table->id();
                $table->date('stat_date')->unique();
                $table->integer('new_subscriptions')->default(0);
                $table->integer('unsubscriptions')->default(0);
                $table->integer('active_subscriptions')->default(0);
                $table->integer('total_billings')->default(0);
                $table->decimal('billing_rate', 5, 2)->default(0);
                $table->decimal('revenue_tnd', 15, 2)->default(0);
                $table->integer('total_clients')->default(0);
                $table->json('offers_breakdown')->nullable();
                $table->string('data_source', 50)->default('calculé');
                $table->timestamps();
                $table->index('stat_date');
            });
        }

        if (!Schema::hasTable('eklektik_stats_daily')) {
            Schema::create('eklektik_stats_daily', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('operator', 50);
                $table->integer('offre_id');
                $table->string('service_name', 255);
                $table->string('offer_name', 255)->nullable();
                $table->string('offer_type', 100)->nullable();
                $table->integer('new_subscriptions')->default(0);
                $table->integer('renewals')->default(0);
                $table->integer('charges')->default(0);
                $table->integer('unsubscriptions')->default(0);
                $table->integer('simchurn')->default(0);
                $table->bigInteger('rev_simchurn_cents')->default(0);
                $table->decimal('rev_simchurn_tnd', 15, 2)->default(0);
                $table->integer('nb_facturation')->default(0);
                $table->decimal('revenu_ttc_local', 15, 2)->default(0);
                $table->decimal('revenu_ttc_usd', 15, 2)->default(0);
                $table->decimal('revenu_ttc_tnd', 15, 2)->default(0);
                $table->decimal('montant_total_ht', 15, 2)->default(0);
                $table->decimal('part_operateur', 5, 2)->default(0);
                $table->decimal('part_agregateur', 5, 2)->default(0);
                $table->decimal('part_bigdeal', 5, 2)->default(0);
                $table->decimal('ca_operateur', 15, 2)->default(0);
                $table->decimal('ca_agregateur', 15, 2)->default(0);
                $table->decimal('ca_bigdeal', 15, 2)->default(0);
                $table->integer('active_subscribers')->default(0);
                $table->bigInteger('revenue_cents')->default(0);
                $table->decimal('billing_rate', 5, 2)->default(0);
                $table->decimal('total_revenue', 15, 2)->default(0);
                $table->decimal('average_price', 10, 3)->default(0);
                $table->decimal('total_amount', 15, 3)->default(0);
                $table->string('source', 50)->default('eklektik_api');
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();
                $table->index(['date', 'operator']);
                $table->index(['operator', 'offre_id']);
                $table->index('date');
                $table->unique(['date', 'operator', 'offre_id']);
            });
        }

        if (!Schema::hasTable('eklektik_cron_config')) {
            Schema::create('eklektik_cron_config', function (Blueprint $table) {
                $table->id();
                $table->string('config_key', 100)->unique();
                $table->text('config_value');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->index(['config_key', 'is_active']);
            });
        }

        if (!Schema::hasTable('eklektik_sync_tracking')) {
            Schema::create('eklektik_sync_tracking', function (Blueprint $table) {
                $table->id();
                $table->string('sync_id', 50)->unique();
                $table->date('sync_date');
                $table->string('operator', 50)->default('ALL');
                $table->string('sync_type', 20)->default('cron');
                $table->string('status', 20)->default('running');
                $table->timestamp('started_at');
                $table->timestamp('completed_at')->nullable();
                $table->integer('duration_seconds')->nullable();
                $table->integer('records_processed')->default(0);
                $table->integer('records_created')->default(0);
                $table->integer('records_updated')->default(0);
                $table->integer('records_skipped')->default(0);
                $table->json('operators_results')->nullable();
                $table->text('error_message')->nullable();
                $table->json('sync_metadata')->nullable();
                $table->string('source', 50)->default('eklektik_api');
                $table->timestamps();
                $table->index(['sync_date', 'operator']);
                $table->index(['status', 'started_at']);
            });
        }

        if (!Schema::hasTable('eklektik_kpis_cache')) {
            Schema::create('eklektik_kpis_cache', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('operator', 50)->default('ALL');
                $table->string('kpi_type', 50);
                $table->decimal('total_value', 15, 2)->default(0);
                $table->decimal('daily_value', 15, 2)->default(0);
                $table->integer('notifications_count')->default(0);
                $table->timestamp('last_updated')->useCurrent();
                $table->timestamps();
                $table->unique(['date', 'operator', 'kpi_type']);
                $table->index(['date', 'operator']);
                $table->index(['kpi_type', 'date']);
            });
        }
    }

    public function down(): void
    {
        // Ne pas supprimer : cette migration est une sauvegarde. Utiliser rollback d’autres migrations si besoin.
    }
};
