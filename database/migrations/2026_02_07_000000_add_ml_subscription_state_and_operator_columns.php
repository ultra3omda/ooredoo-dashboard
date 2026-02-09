<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute les colonnes dérivées de la jointure client_abonnement + abonnement_tarifs + abonnement :
 * - État d'abonnement (facturé = expiration NULL, actif, expiré)
 * - Répartition par opérateur (Orange, TT, Ooredoo) via tarif_id
 * Pour un apprentissage ML plus pertinent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = 'ml_client_features';
        if (!Schema::hasTable($table)) {
            return;
        }
        $existing = Schema::getColumnListing($table);

        Schema::table($table, function (Blueprint $t) use ($existing) {
            // État abonnement (fenêtre 6 mois, à la date de calcul)
            if (!in_array('subs_facture_count', $existing)) {
                $t->unsignedInteger('subs_facture_count')->default(0)->comment('Nb abonnements facturés (expiration NULL) dans la fenêtre');
            }
            if (!in_array('subs_expire_count', $existing)) {
                $t->unsignedInteger('subs_expire_count')->default(0)->comment('Nb abonnements expirés (expiration < date calcul) dans la fenêtre');
            }
            if (!in_array('subs_actif_count', $existing)) {
                $t->unsignedInteger('subs_actif_count')->default(0)->comment('Nb abonnements actifs à la date (expiration NULL ou >= date)');
            }
            if (!in_array('has_facture_subscription', $existing)) {
                $t->boolean('has_facture_subscription')->default(false)->comment('Au moins un abo facturé (expiration NULL)');
            }
            // Répartition par opérateur (CPM 9 : tarif_id 10,16=Orange, 15=TT, 39=Ooredoo)
            if (!in_array('orange_subs_count', $existing)) {
                $t->unsignedInteger('orange_subs_count')->default(0)->comment('Nb abonnements Orange (tarif_id 10,16) dans la fenêtre');
            }
            if (!in_array('tt_subs_count', $existing)) {
                $t->unsignedInteger('tt_subs_count')->default(0)->comment('Nb abonnements TT (tarif_id 15) dans la fenêtre');
            }
            if (!in_array('ooredoo_subs_count', $existing)) {
                $t->unsignedInteger('ooredoo_subs_count')->default(0)->comment('Nb abonnements Ooredoo/DGV (tarif_id 39) dans la fenêtre');
            }
        });
    }

    public function down(): void
    {
        $table = 'ml_client_features';
        $existing = Schema::hasTable($table) ? Schema::getColumnListing($table) : [];
        $cols = array_intersect($existing, [
            'subs_facture_count', 'subs_expire_count', 'subs_actif_count', 'has_facture_subscription',
            'orange_subs_count', 'tt_subs_count', 'ooredoo_subs_count',
        ]);
        if (!empty($cols)) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn($cols));
        }
    }
};
