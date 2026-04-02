<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('client_abonnement')) {
            return;
        }

        if (!$this->indexExists('client_abonnement', 'idx_ca_client_creation')) {
            DB::statement('CREATE INDEX idx_ca_client_creation ON client_abonnement(client_id, client_abonnement_creation)');
        }

        if (!$this->indexExists('client_abonnement', 'idx_ca_client_expiration')) {
            DB::statement('CREATE INDEX idx_ca_client_expiration ON client_abonnement(client_id, client_abonnement_expiration)');
        }

        if (!$this->indexExists('client_abonnement', 'idx_ca_cpm_id')) {
            DB::statement('CREATE INDEX idx_ca_cpm_id ON client_abonnement(country_payments_methods_id)');
        }
    }

    public function down(): void
    {
        $indexes = [
            'idx_ca_cpm_id',
            'idx_ca_client_expiration',
            'idx_ca_client_creation',
        ];

        foreach ($indexes as $index) {
            if ($this->indexExists('client_abonnement', $index)) {
                DB::statement("DROP INDEX {$index} ON client_abonnement");
            }
        }
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
