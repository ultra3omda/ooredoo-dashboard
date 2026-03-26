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
        // 1. Table des features clients (historique et calculées)
        Schema::create('ml_client_features', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->date('calculation_date');
            
            // === Historique de Paiement ===
            $table->decimal('payment_success_rate', 5, 4)->default(0); // 0.0000 à 1.0000
            $table->integer('consecutive_failures')->default(0);
            $table->integer('days_since_last_payment')->nullable();
            $table->decimal('avg_payment_amount', 10, 3)->default(0);
            $table->decimal('payment_frequency', 8, 4)->default(0); // paiements par jour
            $table->integer('total_payments')->default(0);
            $table->integer('total_attempts')->default(0);
            
            // === Patterns de Solde ===
            $table->decimal('avg_balance', 10, 3)->nullable();
            $table->decimal('balance_volatility', 10, 6)->default(0); // variance
            $table->decimal('recharge_frequency', 8, 4)->default(0); // recharges par jour
            $table->decimal('recharge_amount_avg', 10, 3)->default(0);
            $table->integer('days_since_recharge')->nullable();
            $table->enum('balance_trend', ['increasing', 'decreasing', 'stable', 'unknown'])->default('unknown');
            
            // === Patterns Temporels ===
            $table->tinyInteger('best_billing_day_week')->nullable(); // 1-7
            $table->tinyInteger('best_billing_hour')->nullable(); // 0-23
            $table->json('seasonal_pattern')->nullable(); // JSON des stats par mois
            $table->decimal('end_month_success_rate', 5, 4)->default(0);
            $table->decimal('beginning_month_success_rate', 5, 4)->default(0);
            
            // === Comportement Usage ===
            $table->bigInteger('total_transactions')->default(0);
            $table->decimal('avg_transactions_per_day', 8, 4)->default(0);
            $table->integer('unique_statuses_count')->default(0);
            $table->json('status_distribution')->nullable(); // répartition des statuts
            
            // === Démographiques ===
            $table->integer('subscription_age_days')->default(0);
            $table->string('region', 50)->nullable();
            $table->string('operator_type', 50)->nullable();
            $table->timestamp('first_transaction')->nullable();
            $table->timestamp('last_transaction')->nullable();
            
            // === Risk Indicators ===
            $table->decimal('churn_probability', 5, 4)->default(0);
            $table->boolean('has_recent_failures')->default(false);
            $table->integer('failure_streak')->default(0);
            $table->boolean('is_high_value_client')->default(false);
            
            // === Computed Scores ===
            $table->decimal('payment_reliability_score', 5, 4)->default(0); // 0-1
            $table->decimal('engagement_score', 5, 4)->default(0); // 0-1
            $table->decimal('lifetime_value_score', 5, 4)->default(0); // 0-1
            $table->string('client_segment', 30)->default('unknown');
            
            $table->timestamps();
            
            // Index pour performance
            $table->index(['client_id', 'calculation_date']);
            $table->index(['calculation_date']);
            $table->index(['client_segment']);
            $table->index(['payment_success_rate']);
        });

        // 2. Table des prédictions ML
        Schema::create('ml_predictions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->date('prediction_date');
            
            // Prédictions des différents modèles
            $table->decimal('payment_success_probability', 5, 4)->default(0);
            $table->decimal('churn_probability', 5, 4)->default(0);
            $table->decimal('optimal_price', 8, 3)->default(3.000);
            $table->enum('optimal_frequency', ['daily', 'weekly', 'bi_weekly', 'monthly'])->default('monthly');
            $table->datetime('optimal_billing_time')->nullable();
            $table->integer('optimal_billing_day_of_week')->nullable(); // 1-7
            $table->integer('optimal_billing_hour')->nullable(); // 0-23
            
            // Scores de confiance
            $table->decimal('success_confidence', 5, 4)->default(0);
            $table->decimal('timing_confidence', 5, 4)->default(0);
            $table->decimal('price_confidence', 5, 4)->default(0);
            
            // Métadonnées des modèles
            $table->string('model_version', 20)->default('v1.0');
            $table->json('model_features_used')->nullable();
            $table->text('prediction_explanation')->nullable();
            
            $table->timestamps();
            
            $table->index(['client_id', 'prediction_date']);
            $table->index(['prediction_date']);
            $table->index(['payment_success_probability']);
        });

        // 3. Table des recommandations
        Schema::create('ml_recommendations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable(); // null = recommandation globale
            $table->enum('recommendation_type', [
                'pricing', 'timing', 'frequency', 'segmentation', 
                'retry_strategy', 'churn_prevention', 'global_strategy'
            ]);
            
            // Recommandation
            $table->string('current_value', 100)->nullable();
            $table->string('recommended_value', 100);
            $table->text('recommendation_reason');
            $table->decimal('expected_improvement_percentage', 8, 4)->default(0); // %
            $table->decimal('expected_revenue_impact', 12, 3)->default(0); // TND
            $table->decimal('confidence_score', 5, 4)->default(0);
            
            // Priorité et statut
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['pending', 'approved', 'implemented', 'rejected', 'expired'])->default('pending');
            $table->datetime('valid_until')->nullable();
            
            // A/B Testing
            $table->string('ab_test_group', 50)->nullable();
            $table->boolean('is_test_recommendation')->default(false);
            
            $table->timestamps();
            
            $table->index(['client_id', 'status']);
            $table->index(['recommendation_type', 'priority']);
            $table->index(['status', 'created_at']);
        });

        // 4. Table des performances des modèles
        Schema::create('ml_model_performance', function (Blueprint $table) {
            $table->id();
            $table->string('model_name', 50);
            $table->string('model_version', 20);
            $table->date('evaluation_date');
            
            // Métriques de performance
            $table->decimal('accuracy', 5, 4)->default(0);
            $table->decimal('precision', 5, 4)->default(0);
            $table->decimal('recall', 5, 4)->default(0);
            $table->decimal('f1_score', 5, 4)->default(0);
            $table->decimal('auc_roc', 5, 4)->default(0);
            
            // Métriques métier
            $table->decimal('revenue_impact', 12, 3)->default(0);
            $table->decimal('success_rate_improvement', 8, 4)->default(0);
            $table->integer('total_predictions')->default(0);
            $table->integer('correct_predictions')->default(0);
            
            // Données de test
            $table->date('test_period_start');
            $table->date('test_period_end');
            $table->integer('test_sample_size')->default(0);
            
            $table->json('detailed_metrics')->nullable(); // JSON avec métriques détaillées
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            $table->index(['model_name', 'evaluation_date']);
            $table->index(['evaluation_date']);
        });

        // 5. Table des tests A/B
        Schema::create('ml_ab_tests', function (Blueprint $table) {
            $table->id();
            $table->string('test_id', 100)->unique();
            $table->string('test_name', 200);
            $table->text('test_description');
            
            // Configuration du test
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['planned', 'running', 'completed', 'stopped'])->default('planned');
            $table->integer('total_participants')->default(0);
            $table->decimal('traffic_allocation', 5, 4)->default(0.1); // 10% par défaut
            
            // Stratégies testées
            $table->json('control_strategy'); // Stratégie de contrôle
            $table->json('treatment_strategy'); // Stratégie de test
            
            // Métriques de succès
            $table->string('primary_metric', 100)->default('success_rate');
            $table->json('secondary_metrics')->nullable();
            $table->decimal('minimum_detectable_effect', 8, 4)->default(0.05); // 5%
            $table->decimal('significance_level', 5, 4)->default(0.05); // α = 5%
            
            $table->timestamps();
            
            $table->index(['status', 'start_date']);
        });

        // 6. Table des participants aux tests A/B
        Schema::create('ml_ab_test_participants', function (Blueprint $table) {
            $table->id();
            $table->string('test_id', 100);
            $table->unsignedBigInteger('client_id');
            $table->enum('test_group', ['control', 'treatment']);
            $table->datetime('assigned_at');
            
            // Résultats du participant
            $table->boolean('outcome_success')->nullable();
            $table->decimal('outcome_amount', 8, 3)->nullable();
            $table->datetime('outcome_date')->nullable();
            $table->json('outcome_details')->nullable();
            
            $table->timestamps();
            
            $table->index(['test_id', 'test_group']);
            $table->index(['client_id', 'test_id']);
            $table->unique(['test_id', 'client_id']);
        });

        // 7. Table de cache des segments clients
        Schema::create('ml_client_segments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('segment_name', 50);
            $table->decimal('segment_confidence', 5, 4)->default(0);
            $table->date('assigned_date');
            $table->date('valid_until')->nullable();
            
            // Configuration du segment
            $table->json('segment_rules'); // Règles qui ont mené à ce segment
            $table->json('recommended_strategy'); // Stratégie recommandée pour ce segment
            
            // Historique
            $table->string('previous_segment', 50)->nullable();
            $table->datetime('segment_changed_at')->nullable();
            $table->text('change_reason')->nullable();
            
            $table->timestamps();
            
            $table->index(['client_id', 'assigned_date']);
            $table->index(['segment_name']);
            $table->index(['assigned_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ml_client_segments');
        Schema::dropIfExists('ml_ab_test_participants');
        Schema::dropIfExists('ml_ab_tests');
        Schema::dropIfExists('ml_model_performance');
        Schema::dropIfExists('ml_recommendations');
        Schema::dropIfExists('ml_predictions');
        Schema::dropIfExists('ml_client_features');
    }
};