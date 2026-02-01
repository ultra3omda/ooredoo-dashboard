<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateAITablesCommand extends Command
{
    protected $signature = 'ai:setup';
    protected $description = 'Créer les tables Agent IA';

    public function handle()
    {
        $this->info('🤖 Configuration Agent IA...');
        
        try {
            // Créer table conversations
            if (!Schema::hasTable('ai_agent_conversations')) {
                DB::statement("
                    CREATE TABLE ai_agent_conversations (
                        id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        user_id bigint unsigned NOT NULL,
                        session_id char(36) NOT NULL,
                        message_type enum('user','assistant','system') NOT NULL,
                        message text NOT NULL,
                        context_used json NULL,
                        tokens_used int NULL,
                        model_used varchar(50) NULL,
                        execution_time_ms int NULL,
                        created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )
                ");
                $this->info('✅ Table ai_agent_conversations créée');
            } else {
                $this->info('✅ Table ai_agent_conversations existe déjà');
            }
            
            // Créer table cache
            if (!Schema::hasTable('ai_agent_context_cache')) {
                DB::statement("
                    CREATE TABLE ai_agent_context_cache (
                        id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        cache_key varchar(100) NOT NULL UNIQUE,
                        context_type varchar(50) NOT NULL,
                        context_data json NOT NULL,
                        expires_at timestamp NOT NULL,
                        data_size_kb int NULL,
                        created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )
                ");
                $this->info('✅ Table ai_agent_context_cache créée');
            } else {
                $this->info('✅ Table ai_agent_context_cache existe déjà');
            }
            
            // Test des services
            $this->info('🔧 Test des services...');
            
            $contextProvider = app(\App\Services\AIContextProvider::class);
            $this->info('✅ AIContextProvider chargé');
            
            $aiAgent = app(\App\Services\AIAgentService::class);
            $this->info('✅ AIAgentService chargé');
            
            // Test de validation
            $validation = $aiAgent->validateConfiguration();
            $this->info('📊 Status: ' . $validation['status']);
            
            if (!empty($validation['issues'])) {
                foreach ($validation['issues'] as $issue) {
                    $this->warn('⚠️  ' . $issue);
                }
            }
            
            // Test contexte
            $context = $contextProvider->getSystemContext();
            $this->info('✅ Contexte système chargé (' . count($context) . ' éléments)');
            
            $this->info('');
            $this->info('🎉 Configuration Agent IA terminée !');
            $this->info('📍 Accès: http://localhost:8000/dashboard → Onglet "🤖 Agent IA"');
            
            if (empty(env('OPENAI_API_KEY')) || env('OPENAI_API_KEY') === 'sk-your-openai-key-here') {
                $this->warn('⚠️  Ajoutez votre vraie clé OpenAI dans .env pour activer l\'agent');
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Erreur: ' . $e->getMessage());
            $this->error('📍 Trace: ' . $e->getFile() . ':' . $e->getLine());
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
}