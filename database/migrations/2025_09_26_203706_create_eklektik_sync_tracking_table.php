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
        if (Schema::hasTable('eklektik_sync_tracking')) {
            return;
        }
        Schema::create('eklektik_sync_tracking', function (Blueprint $table) {
            $table->id();
            
            // Informations de la synchronisation
            $table->string('sync_id', 50)->unique(); // ID unique de la synchronisation
            $table->date('sync_date'); // Date synchronisée
            $table->string('operator', 50)->default('ALL'); // Opérateur synchronisé
            $table->enum('sync_type', ['manual', 'cron', 'api'])->default('cron'); // Type de sync
            $table->enum('status', ['running', 'success', 'failed', 'partial'])->default('running');
            
            // Détails d'exécution
            $table->timestamp('started_at'); // Heure de début
            $table->timestamp('completed_at')->nullable(); // Heure de fin
            $table->integer('duration_seconds')->nullable(); // Durée en secondes
            
            // Résultats
            $table->integer('records_processed')->default(0); // Nombre d'enregistrements traités
            $table->integer('records_created')->default(0); // Nouveaux enregistrements
            $table->integer('records_updated')->default(0); // Enregistrements mis à jour
            $table->integer('records_skipped')->default(0); // Enregistrements ignorés
            
            // Détails par opérateur
            $table->json('operators_results')->nullable(); // Résultats par opérateur
            
            // Métadonnées
            $table->text('error_message')->nullable(); // Message d'erreur si échec
            $table->json('sync_metadata')->nullable(); // Métadonnées additionnelles
            $table->string('source', 50)->default('eklektik_api'); // Source des données
            
            // Informations système
            $table->string('server_info', 100)->nullable(); // Info serveur
            $table->string('memory_usage', 20)->nullable(); // Utilisation mémoire
            $table->string('execution_user', 50)->nullable(); // Utilisateur qui a lancé
            
            $table->timestamps();
            
            // Index pour les performances
            $table->index(['sync_date', 'operator']);
            $table->index(['status', 'started_at']);
            $table->index('sync_type');
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eklektik_sync_tracking');
    }
};