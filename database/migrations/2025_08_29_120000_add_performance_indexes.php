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
        // Index critiques pour les performances du dashboard
        
        // 1. Index sur history.time (colonne la plus filtrée)
        if (Schema::hasTable('history') && !$this->indexExists('history', 'idx_history_time')) {
            DB::statement('CREATE INDEX idx_history_time ON history(time)');
        }
        
        // 2. Index composite sur history pour les jointures fréquentes
        if (Schema::hasTable('history') && !$this->indexExists('history', 'idx_history_time_client')) {
            DB::statement('CREATE INDEX idx_history_time_client ON history(time, client_abonnement_id)');
        }
        
        // 3. Index sur client_abonnement.client_abonnement_creation
        if (Schema::hasTable('client_abonnement') && !$this->indexExists('client_abonnement', 'idx_ca_creation')) {
            DB::statement('CREATE INDEX idx_ca_creation ON client_abonnement(client_abonnement_creation)');
        }
        
        // 4. Index composite sur client_abonnement pour les filtres par opérateur
        if (Schema::hasTable('client_abonnement') && !$this->indexExists('client_abonnement', 'idx_ca_creation_cpm')) {
            DB::statement('CREATE INDEX idx_ca_creation_cpm ON client_abonnement(client_abonnement_creation, country_payments_methods_id)');
        }
        
        // 5. Index sur client_abonnement.client_abonnement_expiration
        if (Schema::hasTable('client_abonnement') && !$this->indexExists('client_abonnement', 'idx_ca_expiration')) {
            DB::statement('CREATE INDEX idx_ca_expiration ON client_abonnement(client_abonnement_expiration)');
        }
        
        // 6. Index sur country_payments_methods.country_payments_methods_name
        if (Schema::hasTable('country_payments_methods') && !$this->indexExists('country_payments_methods', 'idx_cpm_name')) {
            DB::statement('CREATE INDEX idx_cpm_name ON country_payments_methods(country_payments_methods_name)');
        }
        
        // 7. Index sur history.promotion_id pour les requêtes marchands
        if (Schema::hasTable('history') && !$this->indexExists('history', 'idx_history_promotion')) {
            DB::statement('CREATE INDEX idx_history_promotion ON history(promotion_id)');
        }
        
        // 8. Index composite pour les requêtes marchands complexes
        if (Schema::hasTable('history') && !$this->indexExists('history', 'idx_history_time_promo')) {
            DB::statement('CREATE INDEX idx_history_time_promo ON history(time, promotion_id, client_abonnement_id)');
        }
        
        // 9. Index sur partner.partner_id pour les jointures
        if (Schema::hasTable('partner') && !$this->indexExists('partner', 'idx_partner_id')) {
            DB::statement('CREATE INDEX idx_partner_id ON partner(partner_id)');
        }
        
        // 10. Index sur promotion.partner_id pour les jointures
        if (Schema::hasTable('promotion') && !$this->indexExists('promotion', 'idx_promotion_partner')) {
            DB::statement('CREATE INDEX idx_promotion_partner ON promotion(partner_id)');
        }
        
        echo "Index de performance ajoutés avec succès.\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer les index dans l'ordre inverse
        $indexes = [
            ['promotion', 'idx_promotion_partner'],
            ['partner', 'idx_partner_id'],
            ['history', 'idx_history_time_promo'],
            ['history', 'idx_history_promotion'],
            ['country_payments_methods', 'idx_cpm_name'],
            ['client_abonnement', 'idx_ca_expiration'],
            ['client_abonnement', 'idx_ca_creation_cmp'],
            ['client_abonnement', 'idx_ca_creation'],
            ['history', 'idx_history_time_client'],
            ['history', 'idx_history_time']
        ];
        
        foreach ($indexes as [$table, $index]) {
            if ($this->indexExists($table, $index)) {
                DB::statement("DROP INDEX {$index} ON {$table}");
            }
        }
        
        echo "Index de performance supprimés.\n";
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

