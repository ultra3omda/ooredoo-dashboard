<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table des conversations avec l'agent IA
        if (!Schema::hasTable('ai_agent_conversations')) {
        Schema::create('ai_agent_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Pas de foreign key contrainte pour éviter problèmes
            $table->uuid('session_id')->index();
            $table->enum('message_type', ['user', 'assistant', 'system']);
            $table->text('message');
            $table->json('context_used')->nullable()->comment('Contexte ML utilisé pour la réponse');
            $table->integer('tokens_used')->nullable()->comment('Tokens consommés OpenAI');
            $table->string('model_used', 50)->nullable()->comment('Modèle IA utilisé');
            $table->integer('execution_time_ms')->nullable()->comment('Temps d\'exécution en ms');
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['session_id', 'created_at']);
            
            // Index foreign key manuel sans contrainte
            $table->index('user_id');
        });
        }
        
        // Table du cache de contexte ML pour optimiser les requêtes
        if (!Schema::hasTable('ai_agent_context_cache')) {
        Schema::create('ai_agent_context_cache', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key', 100)->unique();
            $table->string('context_type', 50)->index(); // 'kpis', 'segments', 'ml_features', etc.
            $table->json('context_data')->comment('Données contextuelles ML');
            $table->timestamp('expires_at')->index();
            $table->integer('data_size_kb')->nullable()->comment('Taille des données en KB');
            $table->timestamps();
            
            $table->index(['context_type', 'expires_at']);
        });
        }
    }
    
    public function down(): void
    {
        Schema::dropIfExists('ai_agent_context_cache');
        Schema::dropIfExists('ai_agent_conversations');
    }
};