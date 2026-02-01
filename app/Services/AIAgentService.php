<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\AIConversation;
use App\Services\AIContextProvider;
use Exception;

class AIAgentService
{
    private AIContextProvider $contextProvider;
    private string $apiKey;
    private string $model;
    private int $maxTokens;
    private float $temperature;
    
    public function __construct(AIContextProvider $contextProvider)
    {
        $this->contextProvider = $contextProvider;
        $this->apiKey = env('OPENAI_API_KEY', '');
        $this->model = env('AI_AGENT_MODEL', 'gpt-4');
        $this->maxTokens = (int)env('AI_AGENT_MAX_TOKENS', 1500);
        $this->temperature = (float)env('AI_AGENT_TEMPERATURE', 0.7);
        
        if (empty($this->apiKey)) {
            Log::warning("AIAgentService - Clé API OpenAI non configurée");
        }
    }
    
    /**
     * Pose une question à l'agent IA
     */
    public function ask(string $question, string $sessionId, int $userId): array
    {
        $startTime = microtime(true);
        
        try {
            Log::info("AIAgentService - Question posée", [
                'user_id' => $userId,
                'session_id' => substr($sessionId, 0, 8),
                'question_length' => strlen($question)
            ]);
            
            // 1. Sauvegarder la question de l'utilisateur
            $this->saveMessage($userId, $sessionId, 'user', $question);
            
            // 2. Déterminer le contexte nécessaire selon la question
            $context = $this->buildContext($question);
            
            // 3. Construire le prompt système
            $systemPrompt = $this->buildSystemPrompt($context);
            
            // 4. Récupérer l'historique de conversation
            $conversationHistory = $this->getConversationHistory($sessionId);
            
            // 5. Appeler l'API OpenAI
            $response = $this->callOpenAI($systemPrompt, $conversationHistory, $question);
            
            // 6. Sauvegarder la réponse
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            
            $this->saveMessage(
                $userId,
                $sessionId,
                'assistant',
                $response['message'],
                $context,
                $response['tokens_used'],
                $this->model,
                $executionTime
            );
            
            Log::info("AIAgentService - Réponse générée", [
                'execution_time_ms' => $executionTime,
                'tokens_used' => $response['tokens_used'],
                'context_types' => array_keys($context)
            ]);
            
            return [
                'success' => true,
                'message' => $response['message'],
                'context_used' => array_keys($context),
                'tokens_used' => $response['tokens_used'],
                'execution_time_ms' => $executionTime,
                'model_used' => $this->model
            ];
            
        } catch (Exception $e) {
            Log::error('AIAgentService - Erreur traitement question', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => 'Désolé, je ne peux pas traiter votre question pour le moment. Veuillez réessayer.',
                'debug' => env('APP_DEBUG') ? $e->getMessage() : null,
                'execution_time_ms' => (int)((microtime(true) - $startTime) * 1000)
            ];
        }
    }
    
    /**
     * Construit le contexte ML selon la question
     */
    private function buildContext(string $question): array
    {
        $context = [];
        $questionLower = strtolower($question);
        
        // Toujours inclure le contexte système de base
        $context['system'] = $this->contextProvider->getSystemContext();
        
        // Détection intelligente du contexte nécessaire
        
        // KPIs et performance
        if (preg_match('/(kpi|taux|succès|performance|revenu|chiffre|statistique|aujourd\'hui|maintenant)/i', $question)) {
            $context['kpis'] = $this->contextProvider->getKPIsContext();
        }
        
        // Features ML et modèles
        if (preg_match('/(feature|modèle|ml|machine learning|prédiction|algorithme|lightgbm|entrainement)/i', $question)) {
            $context['ml_features'] = $this->contextProvider->getMLFeaturesContext();
        }
        
        // Recommandations et stratégies
        if (preg_match('/(recommand|stratégie|conseil|optimis|améliorer|quotidien|hebdo|mensuel|prix)/i', $question)) {
            $context['recommendations'] = $this->contextProvider->getRecommendationsContext();
        }
        
        // Client spécifique (ID numérique dans la question)
        if (preg_match('/(?:client|id|numéro)\s+(?:id\s+)?(\d+)/i', $question, $matches)) {
            $clientId = (int)$matches[1];
            $context['client'] = $this->contextProvider->getClientContext($clientId);
        }
        
        // Opérateurs spécifiques
        if (preg_match('/(timwe|eklektik|ooredoo|dgv)/i', $question)) {
            $context['operators'] = $this->getOperatorSpecificContext($question);
        }
        
        // Segments spécifiques
        if (preg_match('/(premium|regular|struggling|high.risk|churn)/i', $question)) {
            $context['segments'] = $this->getSegmentSpecificContext($question);
        }
        
        Log::debug("AIAgentService - Contexte construit", [
            'question_keywords' => $this->extractKeywords($question),
            'context_types' => array_keys($context),
            'total_context_size' => strlen(json_encode($context))
        ]);
        
        return $context;
    }
    
    /**
     * Construit le prompt système optimisé pour l'expertise ML
     */
    private function buildSystemPrompt(array $context): string
    {
        $contextJson = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        return <<<PROMPT
Tu es un expert senior en analyse de données et Machine Learning pour un système de facturation multi-opérateur (Timwe, Eklektik, Ooredoo/DGV).

## TON RÔLE
Assistant intelligent pour analyser les données, expliquer les modèles ML, et recommander des stratégies d'optimisation de facturation.

## EXPERTISE
- 85,744+ clients segmentés en 5 catégories  
- Modèles ML multi-opérateur (LightGBM + Rule-based v2.1)
- 36 features ML par client (incluant patterns multi-opérateur)
- 3 stratégies de pricing : quotidien 0.3 TND, hebdomadaire 1.0 TND, mensuel 3.0 TND

## CONTEXTE ACTUEL
{$contextJson}

## RÈGLES DE RÉPONSE
1. **Français uniquement** (sauf demande explicite)
2. **Chiffres précis** du contexte quand disponibles
3. **Concis mais complet** (300-500 mots max)
4. **Actionnable** : proposer des actions concrètes
5. **Visuel** : utiliser émojis pour clarifier (📊 📈 ⚠️ ✅ ❌ 💡 🎯)
6. **Tableaux Markdown** pour les comparaisons
7. **Transparent** : dire si une donnée n'est pas disponible

## FORMAT TYPE DE RÉPONSE
- **Réponse directe** à la question
- **Chiffres clés** pertinents
- **Tableau comparatif** (si applicable)
- **💡 Recommandation** avec justification
- **🎯 Actions concrètes** à entreprendre

## SPÉCIALISATIONS
- **Segmentation clients** : premium_payers (91.3%), regular_payers (54.3%), struggling_payers (24.6%), high_risk (0.2%), churn_risk (0.5%)
- **Stratégies pricing** : Quotidien 0.3 TND (+643% ROI), Hebdo 1.0 TND (+58% ROI), Mensuel 3.0 TND (-67% ROI)
- **Opérateurs** : Timwe (mensuel premium), Eklektik (quotidien accessible), Ooredoo/DGV (mensuel premium)
- **Features ML** : 36 features incluant patterns temporels, comportementaux, multi-opérateur

## EXEMPLES DE QUESTIONS TYPE
- "Quel est le taux de succès actuel ?"
- "Compare quotidien vs mensuel pour high_risk"
- "Explique les top 5 features ML"
- "Recommandations pour améliorer struggling_payers"
- "Client ID 12345 : quelle stratégie ?"

Réponds de manière experte, basée sur les données, et orientée action.
PROMPT;
    }
    
    /**
     * Appelle l'API OpenAI avec fallback vers simulation
     */
    private function callOpenAI(string $systemPrompt, array $history, string $userMessage): array
    {
        // Si pas de clé OpenAI configurée, utiliser simulation
        if (empty($this->apiKey) || $this->apiKey === 'sk-your-openai-key-here') {
            return $this->simulateOpenAIResponse($userMessage);
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];
        
        // Ajouter l'historique (limité aux 8 derniers échanges pour économiser les tokens)
        foreach (array_slice($history, -16) as $msg) {
            // $msg est maintenant un array (toArray())
            $role = $msg['message_type'] === 'user' ? 'user' : 'assistant';
            $content = $msg['message'];
            $messages[] = ['role' => $role, 'content' => $content];
        }
        
        // Ajouter le message actuel
        $messages[] = ['role' => 'user', 'content' => $userMessage];
        
        $requestData = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'top_p' => 0.9,
            'frequency_penalty' => 0.2,
            'presence_penalty' => 0.1
        ];
        
        Log::debug("AIAgentService - Requête OpenAI", [
            'model' => $this->model,
            'messages_count' => count($messages),
            'estimated_tokens' => strlen(json_encode($messages)) / 4 // Approximation
        ]);
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json'
        ])
        ->withOptions([
            'verify' => false, // Ignorer la vérification SSL pour Windows/XAMPP
            'timeout' => 45,
            'connect_timeout' => 10
        ])
        ->retry(2, 1000)
        ->post('https://api.openai.com/v1/chat/completions', $requestData);
        
        if (!$response->successful()) {
            $errorBody = $response->body();
            Log::error("AIAgentService - Erreur OpenAI API", [
                'status' => $response->status(),
                'error' => $errorBody
            ]);
            
            if ($response->status() === 401) {
                throw new Exception("Clé API OpenAI invalide. Vérifiez OPENAI_API_KEY dans .env");
            } elseif ($response->status() === 429) {
                throw new Exception("Limite de taux OpenAI atteinte. Attendez quelques minutes.");
            } elseif ($response->status() >= 500) {
                throw new Exception("Erreur serveur OpenAI. Réessayez dans quelques instants.");
            } else {
                throw new Exception("Erreur OpenAI: " . ($response->json()['error']['message'] ?? $errorBody));
            }
        }
        
        $data = $response->json();
        
        if (!isset($data['choices'][0]['message']['content'])) {
            throw new Exception("Réponse OpenAI invalide : " . json_encode($data));
        }
        
        return [
            'message' => $data['choices'][0]['message']['content'],
            'tokens_used' => $data['usage']['total_tokens'] ?? 0,
            'model_used' => $data['model'] ?? $this->model
        ];
    }
    
    /**
     * Récupère l'historique de conversation pour maintenir le contexte
     */
    private function getConversationHistory(string $sessionId): array
    {
        return AIConversation::bySession($sessionId)->get()->toArray();
    }
    
    /**
     * Sauvegarde un message dans la conversation
     */
    private function saveMessage(
        int $userId,
        string $sessionId,
        string $messageType,
        string $message,
        ?array $context = null,
        ?int $tokensUsed = null,
        ?string $modelUsed = null,
        ?int $executionTime = null
    ): void {
        try {
            AIConversation::create([
                'user_id' => $userId,
                'session_id' => $sessionId,
                'message_type' => $messageType,
                'message' => $message,
                'context_used' => $context,
                'tokens_used' => $tokensUsed,
                'model_used' => $modelUsed,
                'execution_time_ms' => $executionTime
            ]);
        } catch (Exception $e) {
            Log::error("AIAgentService - Erreur sauvegarde message", [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'session_id' => $sessionId
            ]);
        }
    }

    /**
     * Contexte spécifique pour les opérateurs
     */
    private function getOperatorSpecificContext(string $question): array
    {
        $questionLower = strtolower($question);
        
        $context = [];
        
        if (str_contains($questionLower, 'timwe')) {
            $context['timwe'] = [
                'type' => 'mensuel',
                'price' => '3.0 TND',
                'target' => 'premium_payers',
                'current_performance' => '9.09% succès',
                'characteristics' => 'Abonnements premium mensuels'
            ];
        }
        
        if (str_contains($questionLower, 'eklektik')) {
            $context['eklektik'] = [
                'type' => 'quotidien',
                'price' => '0.3 TND',
                'target' => 'Club Privilèges',
                'expected_performance' => '20-30% succès',
                'characteristics' => 'Micro-paiements quotidiens accessibles'
            ];
        }
        
        if (str_contains($questionLower, 'ooredoo') || str_contains($questionLower, 'dgv')) {
            $context['ooredoo'] = [
                'type' => 'mensuel',
                'price' => '3.0 TND',
                'target' => 'clients_ooredoo_premium',
                'expected_performance' => '12-22% succès',
                'characteristics' => 'Base client existante Ooredoo'
            ];
        }
        
        return $context;
    }

    /**
     * Contexte spécifique pour les segments
     */
    private function getSegmentSpecificContext(string $question): array
    {
        $questionLower = strtolower($question);
        
        $segments = [
            'premium_payers' => [
                'count' => 591,
                'success_rate' => '91.3%',
                'recommended_strategy' => 'mensuel_3.0_tnd',
                'characteristics' => 'Excellente performance, clients de valeur'
            ],
            'regular_payers' => [
                'count' => 3852,
                'success_rate' => '54.3%',
                'recommended_strategy' => 'hebdomadaire_1.0_tnd',
                'characteristics' => 'Performance correcte, potentiel d\'amélioration'
            ],
            'struggling_payers' => [
                'count' => 2726,
                'success_rate' => '24.6%',
                'recommended_strategy' => 'quotidien_0.3_tnd',
                'characteristics' => 'Difficultés avec prix élevé, besoin accessibilité'
            ],
            'high_risk' => [
                'count' => 29439,
                'success_rate' => '0.2%',
                'recommended_strategy' => 'quotidien_0.3_tnd',
                'characteristics' => 'Échec quasi-total, nécessite réactivation'
            ],
            'churn_risk' => [
                'count' => 252,
                'success_rate' => '0.5%',
                'recommended_strategy' => 'quotidien_0.3_tnd',
                'characteristics' => 'Risque abandon, rétention prioritaire'
            ]
        ];
        
        $context = [];
        foreach ($segments as $segmentName => $segmentData) {
            if (str_contains($questionLower, str_replace('_', ' ', $segmentName)) || 
                str_contains($questionLower, $segmentName)) {
                $context[$segmentName] = $segmentData;
            }
        }
        
        // Si aucun segment spécifique détecté, inclure un résumé
        if (empty($context) && str_contains($questionLower, 'segment')) {
            $context['all_segments'] = $segments;
        }
        
        return $context;
    }

    /**
     * Extrait les mots-clés de la question pour le debugging
     */
    private function extractKeywords(string $question): array
    {
        $keywords = [];
        
        // Mots-clés ML
        if (preg_match_all('/(taux|succès|performance|kpi|revenu|clients?|modèle|prédiction|features?|segments?)/i', $question, $matches)) {
            $keywords['ml_terms'] = $matches[0];
        }
        
        // Opérateurs
        if (preg_match_all('/(timwe|eklektik|ooredoo|dgv)/i', $question, $matches)) {
            $keywords['operators'] = $matches[0];
        }
        
        // Stratégies pricing
        if (preg_match_all('/(quotidien|hebdo|mensuel|0\.3|1\.0|3\.0)/i', $question, $matches)) {
            $keywords['pricing'] = $matches[0];
        }
        
        return $keywords;
    }

    /**
     * Analyse le sentiment/intention de la question
     */
    private function analyzeQuestionIntent(string $question): array
    {
        $questionLower = strtolower($question);
        
        $intent = 'unknown';
        $confidence = 0.5;
        
        // Intentions possibles
        if (preg_match('/(comment|pourquoi|explique|expliquer)/i', $question)) {
            $intent = 'explanation';
            $confidence = 0.8;
        } elseif (preg_match('/(compare|différence|vs|versus|mieux|pire)/i', $question)) {
            $intent = 'comparison';
            $confidence = 0.8;
        } elseif (preg_match('/(recommand|conseil|stratégie|que faire|optimiser)/i', $question)) {
            $intent = 'recommendation';
            $confidence = 0.9;
        } elseif (preg_match('/(quel|combien|nombre|taux|statistique)/i', $question)) {
            $intent = 'data_query';
            $confidence = 0.7;
        } elseif (preg_match('/client\s+\d+/i', $question)) {
            $intent = 'client_analysis';
            $confidence = 0.9;
        }
        
        return [
            'intent' => $intent,
            'confidence' => $confidence
        ];
    }

    /**
     * Valide que l'agent IA est correctement configuré
     */
    public function validateConfiguration(): array
    {
        $issues = [];
        $status = 'ok';
        
        // Vérifier la clé API
        if (empty($this->apiKey)) {
            $issues[] = 'OPENAI_API_KEY manquante dans .env';
            $status = 'error';
        } elseif (!str_starts_with($this->apiKey, 'sk-')) {
            $issues[] = 'OPENAI_API_KEY semble invalide (doit commencer par sk-)';
            $status = 'warning';
        }
        
        // Vérifier les tables
        try {
            \Illuminate\Support\Facades\DB::table('ai_agent_conversations')->count();
            \Illuminate\Support\Facades\DB::table('ai_agent_context_cache')->count();
        } catch (Exception $e) {
            $issues[] = 'Tables AI non créées : ' . $e->getMessage();
            $status = 'error';
        }
        
        // Vérifier les données ML
        try {
            $mlFeatures = DB::table('ml_client_features')->count();
            if ($mlFeatures < 1000) {
                $issues[] = "Peu de données ML ($mlFeatures features)";
                $status = $status === 'ok' ? 'warning' : $status;
            }
        } catch (Exception $e) {
            $issues[] = 'Tables ML inaccessibles : ' . $e->getMessage();
            $status = 'error';
        }
        
        return [
            'status' => $status,
            'issues' => $issues,
            'configuration' => [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'api_configured' => !empty($this->apiKey)
            ]
        ];
    }

    /**
     * Test rapide de l'agent avec une question simple
     */
    public function quickTest(): array
    {
        $testSessionId = 'test_' . uniqid();
        $testUserId = 1; // Admin
        $testQuestion = "Quel est le taux de succès global actuel ?";
        
        try {
            $response = $this->ask($testQuestion, $testSessionId, $testUserId);
            
            return [
                'test_status' => 'success',
                'response_received' => $response['success'],
                'execution_time_ms' => $response['execution_time_ms'] ?? 0,
                'tokens_used' => $response['tokens_used'] ?? 0,
                'message_preview' => substr($response['message'] ?? '', 0, 100) . '...'
            ];
        } catch (Exception $e) {
            return [
                'test_status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Simule une réponse OpenAI avec des réponses intelligentes basées sur les données ML
     */
    private function simulateOpenAIResponse(string $userMessage): array
    {
        $mockService = app(AIMockService::class);
        $mockResponse = $mockService->generateMockResponse($userMessage);
        
        return [
            'message' => $mockResponse['message'] . "\n\n*🤖 Réponse simulée - Configurez OPENAI_API_KEY pour l'IA réelle*",
            'tokens_used' => $mockResponse['tokens_used']
        ];
    }
}