<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Index pour les requêtes de période sur history.time
        if (Schema::hasTable('history')) {
            Schema::table('history', function (Blueprint $table) {
                if (!$this->indexExists('history', 'idx_history_time')) {
                    $table->index('time', 'idx_history_time');
                }
                
                // Index composé pour les jointures fréquentes
                if (!$this->indexExists('history', 'idx_history_time_client_abonnement')) {
                    $table->index(['time', 'client_abonnement_id'], 'idx_history_time_client_abonnement');
                }
            });
        }

        // Index pour les dates d'expiration
        if (Schema::hasTable('client_abonnement')) {
            Schema::table('client_abonnement', function (Blueprint $table) {
                if (!$this->indexExists('client_abonnement', 'idx_client_abonnement_expiration')) {
                    $table->index('client_abonnement_expiration', 'idx_client_abonnement_expiration');
                }
                
                // Index composé pour les requêtes de période avec statut actif
                if (!$this->indexExists('client_abonnement', 'idx_client_abonnement_creation_expiration')) {
                    $table->index(['client_abonnement_creation', 'client_abonnement_expiration'], 'idx_client_abonnement_creation_expiration');
                }
            });
        }

        // Index pour les requêtes sur partner
        if (Schema::hasTable('partner')) {
            Schema::table('partner', function (Blueprint $table) {
                // Vérifier si la colonne partner_active existe avant de créer l'index
                $columns = Schema::getColumnListing('partner');
                if (in_array('partner_active', $columns) && !$this->indexExists('partner', 'idx_partner_active')) {
                    $table->index('partner_active', 'idx_partner_active');
                }
            });
        }

        // Index pour partner_location si la table existe
        if (Schema::hasTable('partner_location')) {
            Schema::table('partner_location', function (Blueprint $table) {
                if (!$this->indexExists('partner_location', 'idx_partner_location_partner_id')) {
                    $table->index('partner_id', 'idx_partner_location_partner_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('history', function (Blueprint $table) {
            $table->dropIndex('idx_history_time');
            $table->dropIndex('idx_history_time_client_abonnement');
        });

        Schema::table('client_abonnement', function (Blueprint $table) {
            $table->dropIndex('idx_client_abonnement_expiration');
            $table->dropIndex('idx_client_abonnement_creation_expiration');
        });

        Schema::table('partner', function (Blueprint $table) {
            $columns = Schema::getColumnListing('partner');
            if (in_array('partner_active', $columns)) {
                $table->dropIndex('idx_partner_active');
            }
        });

        if (Schema::hasTable('partner_location')) {
            Schema::table('partner_location', function (Blueprint $table) {
                $table->dropIndex('idx_partner_location_partner_id');
            });
        }
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $indexes = collect(DB::select("SHOW INDEX FROM `{$table}`"))
                ->pluck('Key_name')
                ->toArray();
            return in_array($index, $indexes);
        } catch (\Exception $e) {
            return false;
        }
    }
};