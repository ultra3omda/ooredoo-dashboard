<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée une table séparée pour l'historique d'entraînement ML.
     * Stratégie : échantillonnage hebdomadaire + points clés.
     */
    public function up(): void
    {
        // Table pour l'entraînement ML (historique échantillonné)
        Schema::create('ml_client_features_training', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->index();
            $table->date('calculation_date')->index();
            
            // Même structure que ml_client_features mais optimisée
            $table->decimal('payment_success_rate', 8, 4)->default(0);
            $table->integer('consecutive_failures')->default(0);
            $table->integer('days_since_last_payment')->nullable();
            $table->decimal('avg_payment_amount', 10, 3)->default(0);
            $table->decimal('payment_frequency', 8, 4)->default(0);
            $table->integer('total_payments')->default(0);
            $table->integer('total_attempts')->default(0);
            
            // Features multi-opérateurs (Timwe, Eklektik, Ooredoo)
            $table->decimal('timwe_success_rate', 8, 4)->nullable();
            $table->integer('timwe_total_attempts')->nullable();
            $table->integer('timwe_has_activity')->default(0);
            
            $table->decimal('eklektik_success_rate', 8, 4)->nullable();
            $table->integer('eklektik_total_attempts')->nullable();
            $table->integer('eklektik_has_activity')->default(0);
            
            $table->decimal('ooredoo_success_rate', 8, 4)->nullable();
            $table->integer('ooredoo_total_attempts')->nullable();
            $table->integer('ooredoo_has_activity')->default(0);
            
            // Features agrégées 90j
            $table->integer('total_90d_count')->default(0);
            $table->decimal('total_90d_sum', 12, 3)->default(0);
            $table->decimal('total_90d_avg', 10, 3)->default(0);
            $table->dateTime('last_tx_90d_at')->nullable();
            
            // Features par opérateur 90j (seulement les principales)
            $table->integer('timwe_90d_count')->default(0);
            $table->decimal('timwe_90d_sum', 12, 3)->default(0);
            
            $table->integer('eklektik_90d_count')->default(0);
            $table->decimal('eklektik_90d_sum', 12, 3)->default(0);
            
            $table->integer('ooredoo_90d_count')->default(0);
            $table->decimal('ooredoo_90d_sum', 12, 3)->default(0);
            
            // Préférences et segments
            $table->string('best_performing_operator', 20)->nullable()->index();
            $table->integer('total_operators_used')->nullable();
            $table->string('price_preference', 20)->nullable();
            $table->string('preferred_frequency', 20)->nullable();
            $table->string('client_segment', 30)->default('unknown')->index();
            
            // Scores (pour segmentation)
            $table->decimal('payment_reliability_score', 8, 4)->default(0);
            $table->decimal('engagement_score', 8, 4)->default(0);
            $table->decimal('lifetime_value_score', 8, 4)->default(0);
            
            // Type d'échantillon
            $table->enum('sample_type', ['weekly', 'monthly', 'key_date'])->default('weekly')->index();
            
            $table->timestamps();
            
            // Index composites pour performance
            $table->unique(['client_id', 'calculation_date'], 'idx_client_date_unique');
            $table->index(['calculation_date', 'sample_type'], 'idx_date_sample');
            $table->index(['client_segment', 'calculation_date'], 'idx_segment_date');
        });
        
        // Créer une vue pour les features actuelles (dernière date par client)
        DB::statement("
            CREATE OR REPLACE VIEW ml_client_features_current AS
            SELECT f.*
            FROM ml_client_features f
            INNER JOIN (
                SELECT client_id, MAX(calculation_date) as max_date
                FROM ml_client_features
                GROUP BY client_id
            ) latest ON f.client_id = latest.client_id AND f.calculation_date = latest.max_date
        ");
    }

    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS ml_client_features_current");
        Schema::dropIfExists('ml_client_features_training');
    }
};
