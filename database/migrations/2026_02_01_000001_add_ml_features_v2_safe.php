<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Migration conditionnelle pour éviter les erreurs "column already exists"
        
        Schema::table('ml_client_features', function (Blueprint $table) {
            // Vérifier et ajouter seulement les colonnes manquantes
            $existingColumns = $this->getExistingColumns('ml_client_features');
            
            // Features v2.0 (temporelles)
            if (!in_array('morning_success_rate', $existingColumns)) {
                $table->decimal('morning_success_rate', 5, 4)->nullable()->comment('Taux succès 6h-12h');
            }
            if (!in_array('afternoon_success_rate', $existingColumns)) {
                $table->decimal('afternoon_success_rate', 5, 4)->nullable()->comment('Taux succès 12h-18h');
            }
            if (!in_array('evening_success_rate', $existingColumns)) {
                $table->decimal('evening_success_rate', 5, 4)->nullable()->comment('Taux succès 18h-22h');
            }
            if (!in_array('recovery_after_failure_rate', $existingColumns)) {
                $table->decimal('recovery_after_failure_rate', 5, 4)->nullable()->comment('Taux récupération après échec');
            }
            if (!in_array('max_consecutive_successes', $existingColumns)) {
                $table->integer('max_consecutive_successes')->nullable()->comment('Plus longue séquence succès');
            }
            if (!in_array('payment_amount_std', $existingColumns)) {
                $table->decimal('payment_amount_std', 6, 4)->nullable()->comment('Écart-type montants');
            }
            if (!in_array('amount_flexibility', $existingColumns)) {
                $table->decimal('amount_flexibility', 5, 4)->nullable()->comment('Flexibilité montants');
            }
            if (!in_array('no_balance_failure_rate', $existingColumns)) {
                $table->decimal('no_balance_failure_rate', 5, 4)->nullable()->comment('Taux échecs NO_BALANCE');
            }
            if (!in_array('not_delivered_failure_rate', $existingColumns)) {
                $table->decimal('not_delivered_failure_rate', 5, 4)->nullable()->comment('Taux échecs NOT_DELIVERED');
            }
            
            // Features multi-opérateur v2.1
            if (!in_array('timwe_success_rate', $existingColumns)) {
                $table->decimal('timwe_success_rate', 5, 4)->nullable()->comment('Taux succès Timwe');
            }
            if (!in_array('timwe_total_attempts', $existingColumns)) {
                $table->integer('timwe_total_attempts')->nullable()->comment('Tentatives Timwe');
            }
            if (!in_array('timwe_has_activity', $existingColumns)) {
                $table->boolean('timwe_has_activity')->default(false)->comment('Activité Timwe');
            }
            if (!in_array('eklektik_success_rate', $existingColumns)) {
                $table->decimal('eklektik_success_rate', 5, 4)->nullable()->comment('Taux succès Eklektik');
            }
            if (!in_array('eklektik_daily_consistency', $existingColumns)) {
                $table->decimal('eklektik_daily_consistency', 5, 4)->nullable()->comment('Consistance quotidienne Eklektik');
            }
            if (!in_array('eklektik_has_activity', $existingColumns)) {
                $table->boolean('eklektik_has_activity')->default(false)->comment('Activité Eklektik');
            }
            if (!in_array('ooredoo_success_rate', $existingColumns)) {
                $table->decimal('ooredoo_success_rate', 5, 4)->nullable()->comment('Taux succès Ooredoo/DGV');
            }
            if (!in_array('ooredoo_monthly_consistency', $existingColumns)) {
                $table->decimal('ooredoo_monthly_consistency', 5, 4)->nullable()->comment('Consistance mensuelle Ooredoo');
            }
            if (!in_array('ooredoo_has_activity', $existingColumns)) {
                $table->boolean('ooredoo_has_activity')->default(false)->comment('Activité Ooredoo');
            }
            if (!in_array('total_operators_used', $existingColumns)) {
                $table->integer('total_operators_used')->nullable()->comment('Nombre opérateurs utilisés');
            }
            if (!in_array('operator_diversity_score', $existingColumns)) {
                $table->decimal('operator_diversity_score', 5, 4)->nullable()->comment('Score diversité opérateurs');
            }
            if (!in_array('price_preference', $existingColumns)) {
                $table->string('price_preference', 10)->nullable()->comment('Préférence prix: low/high/mixed');
            }
            if (!in_array('prefers_low_price', $existingColumns)) {
                $table->boolean('prefers_low_price')->default(false)->comment('Préfère prix bas (Eklektik)');
            }
            if (!in_array('prefers_high_price', $existingColumns)) {
                $table->boolean('prefers_high_price')->default(false)->comment('Préfère prix élevé (Timwe/Ooredoo)');
            }
            if (!in_array('preferred_frequency', $existingColumns)) {
                $table->string('preferred_frequency', 15)->nullable()->comment('Fréquence préférée: daily/monthly/mixed');
            }
            if (!in_array('prefers_daily_offers', $existingColumns)) {
                $table->boolean('prefers_daily_offers')->default(false)->comment('Préfère offres quotidiennes');
            }
            if (!in_array('prefers_monthly_offers', $existingColumns)) {
                $table->boolean('prefers_monthly_offers')->default(false)->comment('Préfère offres mensuelles');
            }
            if (!in_array('best_performing_operator', $existingColumns)) {
                $table->string('best_performing_operator', 15)->nullable()->comment('Meilleur opérateur: timwe/eklektik/ooredoo');
            }
        });
        
        // Ajouter les index seulement s'ils n'existent pas
        $this->addIndexIfNotExists('ml_client_features', ['payment_success_rate', 'client_segment']);
        $this->addIndexIfNotExists('ml_client_features', ['calculation_date', 'client_segment']);
        
        // Autres tables (ml_predictions, ml_ab_tests, etc.)
        $this->updateMLPredictionsTable();
        $this->updateMLABTestsTable();
        $this->updateMLPerformanceTable();
    }

    public function down()
    {
        // Rollback sécurisé
        Schema::table('ml_client_features', function (Blueprint $table) {
            $existingColumns = $this->getExistingColumns('ml_client_features');
            $columnsToRemove = [
                'morning_success_rate', 'afternoon_success_rate', 'evening_success_rate',
                'recovery_after_failure_rate', 'max_consecutive_successes',
                'payment_amount_std', 'amount_flexibility',
                'no_balance_failure_rate', 'not_delivered_failure_rate',
                'timwe_success_rate', 'timwe_total_attempts', 'timwe_has_activity',
                'eklektik_success_rate', 'eklektik_daily_consistency', 'eklektik_has_activity',
                'ooredoo_success_rate', 'ooredoo_monthly_consistency', 'ooredoo_has_activity',
                'total_operators_used', 'operator_diversity_score', 'price_preference',
                'prefers_low_price', 'prefers_high_price', 'preferred_frequency',
                'prefers_daily_offers', 'prefers_monthly_offers', 'best_performing_operator'
            ];
            
            $toRemove = array_intersect($columnsToRemove, $existingColumns);
            if (!empty($toRemove)) {
                $table->dropColumn($toRemove);
            }
        });
    }
    
    private function getExistingColumns($tableName)
    {
        $columns = DB::select("SHOW COLUMNS FROM $tableName");
        return array_column($columns, 'Field');
    }
    
    private function addIndexIfNotExists($table, $columns)
    {
        $indexName = $table . '_' . implode('_', $columns) . '_index';
        
        $exists = DB::select("SHOW INDEX FROM $table WHERE Key_name = ?", [$indexName]);
        
        if (empty($exists)) {
            DB::statement("CREATE INDEX $indexName ON $table (" . implode(', ', $columns) . ")");
        }
    }
    
    private function updateMLPredictionsTable()
    {
        $existingColumns = $this->getExistingColumns('ml_predictions');
        
        Schema::table('ml_predictions', function (Blueprint $table) use ($existingColumns) {
            if (!in_array('ab_test_group', $existingColumns)) {
                $table->string('ab_test_group', 20)->nullable()->comment('Groupe A/B: control/treatment');
            }
            if (!in_array('ml_explanation', $existingColumns)) {
                $table->json('ml_explanation')->nullable()->comment('Explication SHAP du modèle ML');
            }
            if (!in_array('prediction_threshold', $existingColumns)) {
                $table->decimal('prediction_threshold', 5, 4)->nullable()->comment('Seuil utilisé pour classification');
            }
        });
        
        $this->addIndexIfNotExists('ml_predictions', ['prediction_date', 'ab_test_group']);
    }
    
    private function updateMLABTestsTable()
    {
        $existingColumns = $this->getExistingColumns('ml_ab_tests');
        
        Schema::table('ml_ab_tests', function (Blueprint $table) use ($existingColumns) {
            if (!in_array('current_participants', $existingColumns)) {
                $table->integer('current_participants')->default(0)->comment('Participants actuels');
            }
            if (!in_array('current_lift', $existingColumns)) {
                $table->decimal('current_lift', 6, 4)->nullable()->comment('Lift actuel (décimal)');
            }
            if (!in_array('is_significant', $existingColumns)) {
                $table->boolean('is_significant')->default(false)->comment('Résultats significatifs');
            }
            if (!in_array('end_reason', $existingColumns)) {
                $table->text('end_reason')->nullable()->comment('Raison de fin de test');
            }
        });
    }
    
    private function updateMLPerformanceTable()
    {
        $existingColumns = $this->getExistingColumns('ml_model_performance');
        
        Schema::table('ml_model_performance', function (Blueprint $table) use ($existingColumns) {
            if (!in_array('training_duration_minutes', $existingColumns)) {
                $table->decimal('training_duration_minutes', 8, 2)->nullable();
            }
            if (!in_array('model_size_mb', $existingColumns)) {
                $table->decimal('model_size_mb', 8, 2)->nullable();
            }
            if (!in_array('revenue_impact', $existingColumns)) {
                $table->decimal('revenue_impact', 12, 2)->nullable()->comment('Impact revenus en TND');
            }
            if (!in_array('success_rate_improvement', $existingColumns)) {
                $table->decimal('success_rate_improvement', 6, 4)->nullable()->comment('Amélioration taux succès');
            }
            if (!in_array('training_params', $existingColumns)) {
                $table->json('training_params')->nullable()->comment('Paramètres d\'entraînement');
            }
            if (!in_array('feature_importance', $existingColumns)) {
                $table->json('feature_importance')->nullable()->comment('Importance des features');
            }
        });
    }
};