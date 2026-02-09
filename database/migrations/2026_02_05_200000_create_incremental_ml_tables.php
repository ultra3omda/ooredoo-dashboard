<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Crée les tables pour l'architecture ML incrémentale :
     * - ml_job_state : checkpoint pour reprendre l'ingestion
     * - tx_daily_agg : agrégation journalière des transactions
     * - Index optimisé sur transactions_history
     */
    public function up(): void
    {
        // 1) TABLE DE CHECKPOINT (état des jobs incrémentaux)
        Schema::create('ml_job_state', function (Blueprint $table) {
            $table->string('job_name', 64)->primary();
            $table->unsignedBigInteger('last_processed_id')->default(0);
            $table->dateTime('last_processed_at')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            $table->index('last_processed_id');
        });

        // Initialiser le checkpoint à 0
        DB::table('ml_job_state')->insert([
            'job_name' => 'tx_daily_ingest',
            'last_processed_id' => 0,
            'last_processed_at' => null,
        ]);

        // 2) TABLE D'AGRÉGATION JOURNALIÈRE
        Schema::create('tx_daily_agg', function (Blueprint $table) {
            $table->date('day');
            $table->unsignedBigInteger('client_id');
            $table->string('status', 20); // TIMWE, ORANGE, TARAJI, TT, OOREDOO, etc.
            
            // Métriques agrégées
            $table->unsignedInteger('tx_count')->default(0);
            $table->decimal('amount_sum', 18, 3)->default(0);
            $table->decimal('amount_avg', 18, 3)->default(0);
            
            // Métadonnées pour debug
            $table->unsignedBigInteger('last_tx_id')->default(0);
            $table->dateTime('last_tx_at')->nullable();
            
            $table->timestamps();
            
            // PRIMARY KEY composite (évite doublons)
            $table->primary(['day', 'client_id', 'status']);
            
            // Index pour requêtes ML (lectures 90 jours)
            $table->index(['client_id', 'day'], 'idx_client_day');
            $table->index(['day', 'status'], 'idx_day_status');
        });

        // 3) INDEX OPTIMISÉ SUR TRANSACTIONS_HISTORY
        // Pour l'ingestion incrémentale (WHERE id > X ORDER BY id)
        $hasIncIndex = collect(DB::select("SHOW INDEX FROM transactions_history WHERE Key_name = 'idx_tx_inc_ml'"))->isNotEmpty();
        
        if (!$hasIncIndex) {
            DB::statement('
                CREATE INDEX idx_tx_inc_ml 
                ON transactions_history (transaction_history_id, created_at, client_id, status)
            ');
        }

        // 4) INDEX POUR FILTRER PAR STATUS RAPIDEMENT
        // Note: MySQL peut utiliser cet index pour les LIKE 'PREFIX%'
        $hasStatusIndex = collect(DB::select("SHOW INDEX FROM transactions_history WHERE Key_name = 'idx_status_created'"))->isNotEmpty();
        
        if (!$hasStatusIndex) {
            DB::statement('
                CREATE INDEX idx_status_created 
                ON transactions_history (status(10), created_at, client_id)
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer les index d'abord
        $hasIncIndex = collect(DB::select("SHOW INDEX FROM transactions_history WHERE Key_name = 'idx_tx_inc_ml'"))->isNotEmpty();
        if ($hasIncIndex) {
            DB::statement('DROP INDEX idx_tx_inc_ml ON transactions_history');
        }
        
        $hasStatusIndex = collect(DB::select("SHOW INDEX FROM transactions_history WHERE Key_name = 'idx_status_created'"))->isNotEmpty();
        if ($hasStatusIndex) {
            DB::statement('DROP INDEX idx_status_created ON transactions_history');
        }
        
        // Supprimer les tables
        Schema::dropIfExists('tx_daily_agg');
        Schema::dropIfExists('ml_job_state');
    }
};
