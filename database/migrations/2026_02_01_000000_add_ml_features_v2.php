<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('ml_client_features', function (Blueprint $table) {
            // Nouvelles features v2.0 pour améliorer la discrimination
            $table->decimal('morning_success_rate', 5, 4)->nullable()->comment('Taux succès 6h-12h');
            $table->decimal('afternoon_success_rate', 5, 4)->nullable()->comment('Taux succès 12h-18h');
            $table->decimal('evening_success_rate', 5, 4)->nullable()->comment('Taux succès 18h-22h');
            $table->decimal('recovery_after_failure_rate', 5, 4)->nullable()->comment('Taux récupération après échec');
            $table->integer('max_consecutive_successes')->nullable()->comment('Plus longue séquence succès');
            $table->decimal('payment_amount_std', 6, 4)->nullable()->comment('Écart-type montants');
            $table->decimal('amount_flexibility', 5, 4)->nullable()->comment('Flexibilité montants');
            $table->decimal('no_balance_failure_rate', 5, 4)->nullable()->comment('Taux échecs NO_BALANCE');
            $table->decimal('not_delivered_failure_rate', 5, 4)->nullable()->comment('Taux échecs NOT_DELIVERED');
            
            // Features multi-opérateur v2.1
            $table->decimal('timwe_success_rate', 5, 4)->nullable()->comment('Taux succès Timwe');
            $table->integer('timwe_total_attempts')->nullable()->comment('Tentatives Timwe');
            $table->boolean('timwe_has_activity')->default(false)->comment('Activité Timwe');
            $table->decimal('eklektik_success_rate', 5, 4)->nullable()->comment('Taux succès Eklektik');
            $table->decimal('eklektik_daily_consistency', 5, 4)->nullable()->comment('Consistance quotidienne Eklektik');
            $table->boolean('eklektik_has_activity')->default(false)->comment('Activité Eklektik');
            $table->decimal('ooredoo_success_rate', 5, 4)->nullable()->comment('Taux succès Ooredoo/DGV');
            $table->decimal('ooredoo_monthly_consistency', 5, 4)->nullable()->comment('Consistance mensuelle Ooredoo');
            $table->boolean('ooredoo_has_activity')->default(false)->comment('Activité Ooredoo');
            $table->integer('total_operators_used')->nullable()->comment('Nombre opérateurs utilisés');
            $table->decimal('operator_diversity_score', 5, 4)->nullable()->comment('Score diversité opérateurs');
            $table->string('price_preference', 10)->nullable()->comment('Préférence prix: low/high/mixed');
            $table->boolean('prefers_low_price')->default(false)->comment('Préfère prix bas (Eklektik)');
            $table->boolean('prefers_high_price')->default(false)->comment('Préfère prix élevé (Timwe/Ooredoo)');
            $table->string('preferred_frequency', 15)->nullable()->comment('Fréquence préférée: daily/monthly/mixed');
            $table->boolean('prefers_daily_offers')->default(false)->comment('Préfère offres quotidiennes');
            $table->boolean('prefers_monthly_offers')->default(false)->comment('Préfère offres mensuelles');
            $table->string('best_performing_operator', 15)->nullable()->comment('Meilleur opérateur: timwe/eklektik/ooredoo');
            
            // Index pour optimiser les requêtes ML
            $table->index(['payment_success_rate', 'client_segment']);
            $table->index(['calculation_date', 'client_segment']);
            $table->index(['consecutive_failures', 'recovery_after_failure_rate']);
        });
        
        Schema::table('ml_predictions', function (Blueprint $table) {
            // Nouvelles colonnes pour A/B testing et modèle v2
            $table->string('ab_test_group', 20)->nullable()->comment('Groupe A/B: control/treatment');
            $table->json('ml_explanation')->nullable()->comment('Explication SHAP du modèle ML');
            $table->decimal('prediction_threshold', 5, 4)->nullable()->comment('Seuil utilisé pour classification');
            
            $table->index(['prediction_date', 'ab_test_group']);
        });
        
        Schema::table('ml_ab_tests', function (Blueprint $table) {
            // Nouvelles métriques pour suivi A/B
            $table->integer('current_participants')->default(0)->comment('Participants actuels');
            $table->decimal('current_lift', 6, 4)->nullable()->comment('Lift actuel (décimal)');
            $table->boolean('is_significant')->default(false)->comment('Résultats significatifs');
            $table->text('end_reason')->nullable()->comment('Raison de fin de test');
        });

        Schema::table('ml_model_performance', function (Blueprint $table) {
            // Nouvelles métriques pour monitoring avancé
            $table->decimal('training_duration_minutes', 8, 2)->nullable();
            $table->decimal('model_size_mb', 8, 2)->nullable();
            $table->decimal('revenue_impact', 12, 2)->nullable()->comment('Impact revenus en TND');
            $table->decimal('success_rate_improvement', 6, 4)->nullable()->comment('Amélioration taux succès');
            $table->json('training_params')->nullable()->comment('Paramètres d\'entraînement');
            $table->json('feature_importance')->nullable()->comment('Importance des features');
        });
    }

    public function down()
    {
        Schema::table('ml_client_features', function (Blueprint $table) {
            $table->dropColumn([
                'morning_success_rate', 'afternoon_success_rate', 'evening_success_rate',
                'recovery_after_failure_rate', 'max_consecutive_successes',
                'payment_amount_std', 'amount_flexibility',
                'no_balance_failure_rate', 'not_delivered_failure_rate'
            ]);
            $table->dropIndex(['ml_client_features_payment_success_rate_client_segment_index']);
            $table->dropIndex(['ml_client_features_calculation_date_client_segment_index']);
            $table->dropIndex(['ml_client_features_consecutive_failures_recovery_after_failure_rate_index']);
        });
        
        Schema::table('ml_predictions', function (Blueprint $table) {
            $table->dropColumn(['ab_test_group', 'ml_explanation', 'prediction_threshold']);
            $table->dropIndex(['ml_predictions_prediction_date_ab_test_group_index']);
        });
        
        Schema::table('ml_ab_tests', function (Blueprint $table) {
            $table->dropColumn(['current_participants', 'current_lift', 'is_significant', 'end_reason']);
        });

        Schema::table('ml_model_performance', function (Blueprint $table) {
            $table->dropColumn([
                'training_duration_minutes', 'model_size_mb', 'revenue_impact',
                'success_rate_improvement', 'training_params', 'feature_importance'
            ]);
        });
    }
};