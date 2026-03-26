<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existingColumns = $this->getExistingColumns('ml_client_features');

        Schema::table('ml_client_features', function (Blueprint $table) use ($existingColumns) {
            // Timwe (complément)
            if (!in_array('timwe_total_successes', $existingColumns)) {
                $table->integer('timwe_total_successes')->nullable()->comment('Succès facturation Timwe');
            }
            if (!in_array('timwe_avg_revenue_per_success', $existingColumns)) {
                $table->decimal('timwe_avg_revenue_per_success', 10, 3)->nullable()->comment('Revenu moyen par succès Timwe (TND)');
            }
            if (!in_array('timwe_no_balance_rate', $existingColumns)) {
                $table->decimal('timwe_no_balance_rate', 5, 4)->nullable()->comment('Taux NO_BALANCE Timwe');
            }
            if (!in_array('timwe_not_delivered_rate', $existingColumns)) {
                $table->decimal('timwe_not_delivered_rate', 5, 4)->nullable()->comment('Taux NOT_DELIVERED Timwe');
            }

            // Eklektik (complément)
            if (!in_array('eklektik_total_attempts', $existingColumns)) {
                $table->integer('eklektik_total_attempts')->nullable()->comment('Tentatives Eklektik');
            }
            if (!in_array('eklektik_total_subscriptions', $existingColumns)) {
                $table->integer('eklektik_total_subscriptions')->nullable()->comment('Abonnements Eklektik');
            }
            if (!in_array('eklektik_avg_daily_successes', $existingColumns)) {
                $table->decimal('eklektik_avg_daily_successes', 8, 2)->nullable()->comment('Succès quotidiens moyens Eklektik');
            }

            // Ooredoo/DGV (complément)
            if (!in_array('ooredoo_total_attempts', $existingColumns)) {
                $table->integer('ooredoo_total_attempts')->nullable()->comment('Tentatives Ooredoo/DGV');
            }
            if (!in_array('ooredoo_total_subscriptions', $existingColumns)) {
                $table->integer('ooredoo_total_subscriptions')->nullable()->comment('Abonnements Ooredoo/DGV');
            }
            if (!in_array('ooredoo_avg_monthly_successes', $existingColumns)) {
                $table->decimal('ooredoo_avg_monthly_successes', 8, 2)->nullable()->comment('Succès mensuels moyens Ooredoo');
            }

            // Cross-operator (complément)
            if (!in_array('unique_price_points', $existingColumns)) {
                $table->integer('unique_price_points')->nullable()->comment('Nombre de prix distincts (abonnement_tarifs)');
            }
            if (!in_array('is_multi_operator_user', $existingColumns)) {
                $table->boolean('is_multi_operator_user')->default(false)->comment('Utilise plusieurs opérateurs');
            }

            // Type d'offre (quotidien vs mensuel)
            if (!in_array('daily_offers_count', $existingColumns)) {
                $table->integer('daily_offers_count')->nullable()->comment('Nb offres quotidiennes');
            }
            if (!in_array('monthly_offers_count', $existingColumns)) {
                $table->integer('monthly_offers_count')->nullable()->comment('Nb offres mensuelles');
            }
            if (!in_array('total_offers_count', $existingColumns)) {
                $table->integer('total_offers_count')->nullable()->comment('Nb total offres');
            }
            if (!in_array('daily_engagement_rate', $existingColumns)) {
                $table->decimal('daily_engagement_rate', 5, 4)->nullable()->comment('Taux engagement quotidien');
            }
            if (!in_array('monthly_engagement_rate', $existingColumns)) {
                $table->decimal('monthly_engagement_rate', 5, 4)->nullable()->comment('Taux engagement mensuel');
            }
            if (!in_array('is_frequency_flexible', $existingColumns)) {
                $table->boolean('is_frequency_flexible')->default(false)->comment('Mix quotidien/mensuel');
            }
        });
    }

    public function down(): void
    {
        $existingColumns = $this->getExistingColumns('ml_client_features');
        $toRemove = [
            'timwe_total_successes', 'timwe_avg_revenue_per_success', 'timwe_no_balance_rate', 'timwe_not_delivered_rate',
            'eklektik_total_attempts', 'eklektik_total_subscriptions', 'eklektik_avg_daily_successes',
            'ooredoo_total_attempts', 'ooredoo_total_subscriptions', 'ooredoo_avg_monthly_successes',
            'unique_price_points', 'is_multi_operator_user',
            'daily_offers_count', 'monthly_offers_count', 'total_offers_count',
            'daily_engagement_rate', 'monthly_engagement_rate', 'is_frequency_flexible',
        ];
        $columns = array_intersect($toRemove, $existingColumns);
        if (empty($columns)) {
            return;
        }
        Schema::table('ml_client_features', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }

    private function getExistingColumns(string $table): array
    {
        $columns = DB::select("SHOW COLUMNS FROM {$table}");
        return array_column($columns, 'Field');
    }
};
