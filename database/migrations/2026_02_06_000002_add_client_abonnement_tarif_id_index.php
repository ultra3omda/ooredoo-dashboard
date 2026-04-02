<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ajoute un index sur client_abonnement.tarif_id pour optimiser
     * les LEFT JOIN avec abonnement_tarifs (extraction ML multi-opérateur).
     */
    public function up(): void
    {
        if (!Schema::hasTable('client_abonnement')) {
            return;
        }

        $columns = Schema::getColumnListing('client_abonnement');
        if (!in_array('tarif_id', $columns, true)) {
            return;
        }

        if ($this->indexExists('client_abonnement', 'idx_ca_tarif_id')) {
            return;
        }

        DB::statement('CREATE INDEX idx_ca_tarif_id ON client_abonnement(tarif_id)');
    }

    public function down(): void
    {
        if (!$this->indexExists('client_abonnement', 'idx_ca_tarif_id')) {
            return;
        }

        DB::statement('DROP INDEX idx_ca_tarif_id ON client_abonnement');
    }

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
