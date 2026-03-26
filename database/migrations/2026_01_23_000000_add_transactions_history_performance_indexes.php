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
        echo "Ajout des index de performance sur transactions_history...\n";
        
        // Index pour les recherches fréquentes sur client_id + status
        if (Schema::hasTable('transactions_history')) {
            // Index composite pour les requêtes de type WHERE client_id IN (...) AND status LIKE '%TIMWE%'
            if (!$this->indexExists('transactions_history', 'idx_transactions_client_status_date')) {
                DB::statement('CREATE INDEX idx_transactions_client_status_date ON transactions_history(client_id, status(100), created_at)');
                echo "✓ Index idx_transactions_client_status_date créé\n";
            } else {
                echo "✓ Index idx_transactions_client_status_date existe déjà\n";
            }
            
            // Index sur created_at pour les tris
            if (!$this->indexExists('transactions_history', 'idx_transactions_created_at')) {
                DB::statement('CREATE INDEX idx_transactions_created_at ON transactions_history(created_at)');
                echo "✓ Index idx_transactions_created_at créé\n";
            } else {
                echo "✓ Index idx_transactions_created_at existe déjà\n";
            }
            
            // Index sur client_id seul pour les recherches rapides
            if (!$this->indexExists('transactions_history', 'idx_transactions_client_id')) {
                DB::statement('CREATE INDEX idx_transactions_client_id ON transactions_history(client_id)');
                echo "✓ Index idx_transactions_client_id créé\n";
            } else {
                echo "✓ Index idx_transactions_client_id existe déjà\n";
            }
        }
        
        echo "✅ Index de performance ajoutés avec succès.\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexes = [
            'idx_transactions_client_id',
            'idx_transactions_created_at',
            'idx_transactions_client_status_date',
        ];
        
        foreach ($indexes as $index) {
            if ($this->indexExists('transactions_history', $index)) {
                DB::statement("DROP INDEX {$index} ON transactions_history");
                echo "✓ Index {$index} supprimé\n";
            }
        }
        
        echo "✅ Index de performance supprimés.\n";
    }
    
    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }
};
