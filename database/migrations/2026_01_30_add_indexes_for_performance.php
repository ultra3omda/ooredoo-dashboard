<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ajouter des index pour optimiser les requêtes de diagnostic Timwe
        Schema::table('transactions_history', function (Blueprint $table) {
            // Index composé pour recherches Timwe par date et status
            $table->index(['created_at', 'status'], 'idx_th_created_status');
            
            // Index pour recherches par client
            $table->index(['client_id', 'created_at'], 'idx_th_client_created');
        });
        
        // Index pour client_abonnement si pas déjà présents
        if (!$this->indexExists('client_abonnement', 'idx_ca_creation_cpm')) {
            Schema::table('client_abonnement', function (Blueprint $table) {
                $table->index(['client_abonnement_creation', 'country_payments_methods_id'], 'idx_ca_creation_cpm');
            });
        }
        
        if (!$this->indexExists('client_abonnement', 'idx_ca_expiration_cpm')) {
            Schema::table('client_abonnement', function (Blueprint $table) {
                $table->index(['client_abonnement_expiration', 'country_payments_methods_id'], 'idx_ca_expiration_cpm');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions_history', function (Blueprint $table) {
            $table->dropIndex('idx_th_created_status');
            $table->dropIndex('idx_th_client_created');
        });
        
        if ($this->indexExists('client_abonnement', 'idx_ca_creation_cpm')) {
            Schema::table('client_abonnement', function (Blueprint $table) {
                $table->dropIndex('idx_ca_creation_cpm');
            });
        }
        
        if ($this->indexExists('client_abonnement', 'idx_ca_expiration_cpm')) {
            Schema::table('client_abonnement', function (Blueprint $table) {
                $table->dropIndex('idx_ca_expiration_cpm');
            });
        }
    }
    
    /**
     * Vérifier si un index existe
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        
        $result = DB::select(
            "SELECT COUNT(*) as count 
             FROM information_schema.statistics 
             WHERE table_schema = ? 
             AND table_name = ? 
             AND index_name = ?",
            [$database, $table, $index]
        );
        
        return $result[0]->count > 0;
    }
};
