<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'ml_client_features';

        // Supprimer les doublons (garder la ligne avec le plus grand id par client_id + calculation_date)
        $duplicates = DB::table($table)
            ->select('client_id', 'calculation_date', DB::raw('MAX(id) as keep_id'))
            ->groupBy('client_id', 'calculation_date')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $d) {
            DB::table($table)
                ->where('client_id', $d->client_id)
                ->where('calculation_date', $d->calculation_date)
                ->where('id', '!=', $d->keep_id)
                ->delete();
        }

        // Ajouter l'index UNIQUE pour que upsert mette à jour au lieu d'insérer des doublons
        $indexName = 'ml_client_features_client_date_unique';
        $exists = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        if (empty($exists)) {
            DB::statement("CREATE UNIQUE INDEX {$indexName} ON {$table} (client_id, calculation_date)");
        }
    }

    public function down(): void
    {
        $indexName = 'ml_client_features_client_date_unique';
        $exists = DB::select("SHOW INDEX FROM ml_client_features WHERE Key_name = ?", [$indexName]);
        if (!empty($exists)) {
            DB::statement("DROP INDEX {$indexName} ON ml_client_features");
        }
    }
};
