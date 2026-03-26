<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table pour l'entraînement ML sans data leakage.
     * Principe : Features au temps T, Label au temps T+30j.
     */
    public function up(): void
    {
        Schema::create('ml_training_samples', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->index();
            $table->date('feature_date')->comment('Date des features (J)');
            $table->date('target_date')->comment('Date du label (J+30)');
            
            // === FEATURES HISTORIQUES (J-90 à J) - Ne révèlent PAS le futur ===
            
            // Timwe - Comportement passé
            $table->integer('timwe_past_attempts')->default(0);
            $table->integer('timwe_past_successes')->default(0);
            $table->integer('timwe_past_failures')->default(0);
            $table->decimal('timwe_past_avg_success_rate', 8, 4)->nullable();
            $table->integer('timwe_days_since_last_success')->nullable();
            
            // Eklektik - Comportement passé
            $table->integer('eklektik_past_attempts')->default(0);
            $table->integer('eklektik_past_successes')->default(0);
            $table->integer('eklektik_past_failures')->default(0);
            $table->decimal('eklektik_past_avg_success_rate', 8, 4)->nullable();
            $table->integer('eklektik_days_since_last_success')->nullable();
            
            // Ooredoo - Comportement passé
            $table->integer('ooredoo_past_attempts')->default(0);
            $table->integer('ooredoo_past_successes')->default(0);
            $table->integer('ooredoo_past_failures')->default(0);
            $table->decimal('ooredoo_past_avg_success_rate', 8, 4)->nullable();
            $table->integer('ooredoo_days_since_last_success')->nullable();
            
            // Métriques générales passées
            $table->integer('total_past_attempts')->default(0);
            $table->integer('total_past_successes')->default(0);
            $table->decimal('total_past_revenue', 12, 3)->default(0);
            $table->integer('consecutive_failures_before')->default(0);
            $table->integer('days_since_any_success')->nullable();
            
            // Patterns et tendances
            $table->integer('operators_used_count')->default(0);
            $table->string('dominant_operator', 20)->nullable();
            $table->string('engagement_trend', 20)->nullable(); // increasing, stable, decreasing
            $table->boolean('had_recent_activity_7d')->default(false);
            $table->boolean('had_recent_success_7d')->default(false);
            
            // === LABEL FUTUR (J à J+30) - Ce qu'on veut prédire ===
            $table->boolean('had_success_next_30d')->index()->comment('1 si au moins 1 succès dans les 30j après');
            $table->integer('success_count_next_30d')->nullable()->comment('Nombre de succès dans les 30j (pour analyse)');
            $table->string('best_operator_next_30d', 20)->nullable()->comment('Meilleur opérateur dans les 30j (pour analyse)');
            
            $table->timestamps();
            
            // Index pour performance
            $table->unique(['client_id', 'feature_date'], 'idx_client_feature_unique');
            $table->index(['feature_date', 'target_date'], 'idx_dates');
            $table->index('had_success_next_30d', 'idx_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_training_samples');
    }
};
