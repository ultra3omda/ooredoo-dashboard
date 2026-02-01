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
    public const PROVIDER_OPENAI = 'openai';
    public const PROVIDER_ANTHROPIC = 'anthropic';
    public const PROVIDER_GEMINI = 'gemini';

    private AIContextProvider $contextProvider;
    private string $openaiKey;
    private string $openaiModel;
    private string $anthropicKey;
    private string $anthropicModel;
    private string $geminiKey;
    private string $geminiModel;
    private int $maxTokens;
    private float $temperature;

    public function __construct(AIContextProvider $contextProvider)
    {
        $this->contextProvider = $contextProvider;
        $this->openaiKey = env('OPENAI_API_KEY', '');
        $this->openaiModel = env('AI_AGENT_MODEL', 'gpt-4');
        $this->anthropicKey = env('ANTHROPIC_API_KEY', '');
        $this->anthropicModel = env('ANTHROPIC_MODEL', 'claude-sonnet-4-5-20250929');
        $this->geminiKey = env('GEMINI_API_KEY', '');
        $this->geminiModel = env('GEMINI_MODEL', 'gemini-2.0-flash');
        $this->maxTokens = (int)env('AI_AGENT_MAX_TOKENS', 1500);
        $this->temperature = (float)env('AI_AGENT_TEMPERATURE', 0.7);

        if (empty($this->openaiKey)) {
            Log::warning("AIAgentService - Clé API OpenAI non configurée");
        }
        if (empty($this->anthropicKey)) {
            Log::warning("AIAgentService - Clé API Anthropic non configurée");
        }
        if (empty($this->geminiKey)) {
            Log::warning("AIAgentService - Clé API Gemini non configurée");
        }
    }

    /**
     * Liste des fournisseurs disponibles (clé configurée)
     */
    public function getAvailableProviders(): array
    {
        $providers = [];
        if (!empty($this->openaiKey) && $this->openaiKey !== 'sk-your-openai-key-here') {
            $providers[self::PROVIDER_OPENAI] = ['label' => 'OpenAI (GPT)', 'model' => $this->openaiModel];
        }
        if (!empty($this->anthropicKey)) {
            $providers[self::PROVIDER_ANTHROPIC] = ['label' => 'Claude (Anthropic)', 'model' => $this->anthropicModel];
        }
        if (!empty($this->geminiKey)) {
            $providers[self::PROVIDER_GEMINI] = ['label' => 'Gemini (Google)', 'model' => $this->geminiModel];
        }
        return $providers;
    }

    /**
     * Pose une question à l'agent IA (provider: openai, anthropic, gemini)
     */
    public function ask(string $question, string $sessionId, int $userId, string $provider = self::PROVIDER_OPENAI): array
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
            
            // 5. Appeler l'API du fournisseur sélectionné
            $response = $this->callProvider($provider, $systemPrompt, $conversationHistory, $question);
            $modelUsed = $this->getModelForProvider($provider);
            
            // 6. Sauvegarder la réponse
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            
            $this->saveMessage(
                $userId,
                $sessionId,
                'assistant',
                $response['message'],
                $context,
                $response['tokens_used'],
                $modelUsed,
                $executionTime
            );
            
            Log::info("AIAgentService - Réponse générée", [
                'provider' => $provider,
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
                'model_used' => $modelUsed,
                'provider' => $provider
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

        // Insights avancés (opportunités, quick wins, A/B tests)
        if (preg_match('/(insight|opportunité|quick win|ab test|revenue opportunity|risque|alerte)/i', $question)) {
            $context['advanced_insights'] = $this->contextProvider->getAdvancedInsightsContext();
        }

        Log::debug("AIAgentService - Contexte construit", [
            'question_keywords' => $this->extractKeywords($question),
            'context_types' => array_keys($context),
            'total_context_size' => strlen(json_encode($context))
        ]);
        
        return $context;
    }
    
    /**
     * Construit le prompt système enrichi "Dr. ML" pour réponses ultra-pertinentes
     */
    private function buildSystemPrompt(array $context): string
    {
        $contextJson = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Tu es **Dr. ML**, expert senior en Machine Learning appliqué à la facturation télécoms avec 10+ ans d'expérience.

## 🎯 TON EXPERTISE UNIQUE
1. **Analyse Prédictive** : Modèles LightGBM, XGBoost, feature engineering avancé
2. **Optimisation Revenue** : Pricing dynamique, segmentation client, A/B testing
3. **Multi-Operator Strategy** : Timwe (premium), Eklektik (micro-billing), Ooredoo (base installée)
4. **Behavioral Analytics** : Patterns temporels, churn prediction, LTV optimization

## CONTEXTE BUSINESS
- **Base client** : 85,744+ clients segmentés (5 catégories de risque)
- **Performance actuelle** : ~10% succès → Objectif : 35%+ (+250%)
- **Stratégies** : Quotidien 0.3 TND (+643% ROI) ✅ | Hebdo 1.0 TND (+58% ROI) ⚠️ | Mensuel 3.0 TND (-67% ROI) ❌
- **Opérateurs** : Timwe, Eklektik, Ooredoo/DGV
- **Features ML** : 36 features (patterns temporels, comportementaux, multi-opérateur)

## 📊 DONNÉES EN TEMPS RÉEL
{$contextJson}

## 🎨 STRUCTURE OBLIGATOIRE DES RÉPONSES
1. **🎯 RÉPONSE DIRECTE** (1-2 phrases claires)
2. **📊 DONNÉES CLÉS** (3-5 chiffres avec contexte)
3. **📈 ANALYSE COMPARATIVE** (tableau Markdown si applicable)
4. **💡 RECOMMANDATION ACTIONNABLE** (pourquoi + comment)
5. **✅ PROCHAINES ÉTAPES** (actions concrètes numérotées)

## ⚠️ RÈGLES ABSOLUES
✅ TOUJOURS : chiffres précis du contexte, plan d'action 3-5 étapes, tableaux pour comparaisons, quantifier impact (TND, %, ROI), concis (300-600 mots max).
❌ JAMAIS : inventer des chiffres, réponses vagues "ça dépend...", jargon sans explication, recommandations sans justification chiffrée. Utiliser les émojis de structure (🎯 📊 💡 ✅).

## SEGMENTATION & FEATURES
- **Segments** : premium_payers (91.3%), regular_payers (54.3%), struggling_payers (24.6%), high_risk (0.2%), churn_risk (0.5%)
- **Top features** : consecutive_failures, payment_success_rate, total_payments, recovery_after_failure_rate, timwe_success_rate, payment_reliability_score, etc.

Réponds en français, de manière experte, basée uniquement sur les données fournies, et orientée action.
PROMPT;
    }
    
    /**
     * Retourne le nom du modèle pour un fournisseur donné
     */
    private function getModelForProvider(string $provider): string
    {
        return match ($provider) {
            self::PROVIDER_ANTHROPIC => $this->anthropicModel,
            self::PROVIDER_GEMINI => $this->geminiModel,
            default => $this->openaiModel,
        };
    }

    /**
     * Dispatch vers le bon fournisseur (OpenAI, Anthropic, Gemini) ou simulation
     */
    private function callProvider(string $provider, string $systemPrompt, array $history, string $userMessage): array
    {
        $available = $this->getAvailableProviders();
        if (!isset($available[$provider])) {
            // Fallback: essayer OpenAI en simulation si aucun fournisseur configuré
            if (empty($available)) {
                return $this->simulateOpenAIResponse($userMessage);
            }
            $provider = array_key_first($available);
        }

        return match ($provider) {
            self::PROVIDER_ANTHROPIC => $this->callAnthropic($systemPrompt, $history, $userMessage),
            self::PROVIDER_GEMINI => $this->callGemini($systemPrompt, $history, $userMessage),
            default => $this->callOpenAI($systemPrompt, $history, $userMessage),
        };
    }

    /**
     * Appelle l'API OpenAI avec fallback vers simulation
     */
    private function callOpenAI(string $systemPrompt, array $history, string $userMessage): array
    {
        if (empty($this->openaiKey) || $this->openaiKey === 'sk-your-openai-key-here') {
            return $this->simulateOpenAIResponse($userMessage);
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];
        foreach (array_slice($history, -16) as $msg) {
            $role = $msg['message_type'] === 'user' ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => $msg['message']];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $requestData = [
            'model' => $this->openaiModel,
            'messages' => $messages,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'top_p' => 0.9,
            'frequency_penalty' => 0.2,
            'presence_penalty' => 0.1
        ];

        Log::debug("AIAgentService - Requête OpenAI", ['model' => $this->openaiModel, 'messages_count' => count($messages)]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->openaiKey,
            'Content-Type' => 'application/json'
        ])
        ->withOptions(['verify' => false, 'timeout' => 45, 'connect_timeout' => 10])
        ->retry(2, 1000)
        ->post('https://api.openai.com/v1/chat/completions', $requestData);

        if (!$response->successful()) {
            $errorBody = $response->body();
            Log::error("AIAgentService - Erreur OpenAI API", ['status' => $response->status(), 'error' => $errorBody]);
            if ($response->status() === 401) {
                throw new Exception("Clé API OpenAI invalide. Vérifiez OPENAI_API_KEY dans .env");
            }
            if ($response->status() === 429) {
                $msg = $response->json()['error']['message'] ?? '';
                $hint = (str_contains($msg, 'quota') || str_contains($msg, 'billing'))
                    ? ' Activez la facturation sur https://platform.openai.com/account/billing'
                    : ' Attendez quelques minutes ou vérifiez https://platform.openai.com/account/limits';
                throw new Exception("OpenAI : quota ou limite de taux dépassé." . $hint);
            }
            throw new Exception("Erreur OpenAI: " . ($response->json()['error']['message'] ?? $errorBody));
        }

        $data = $response->json();
        if (!isset($data['choices'][0]['message']['content'])) {
            throw new Exception("Réponse OpenAI invalide : " . json_encode($data));
        }
        return [
            'message' => $data['choices'][0]['message']['content'],
            'tokens_used' => $data['usage']['total_tokens'] ?? 0,
            'model_used' => $data['model'] ?? $this->openaiModel
        ];
    }

    /**
     * Appelle l'API Anthropic (Claude)
     */
    private function callAnthropic(string $systemPrompt, array $history, string $userMessage): array
    {
        $messages = [];
        foreach (array_slice($history, -16) as $msg) {
            $role = $msg['message_type'] === 'user' ? 'user' : 'assistant';
            $content = is_string($msg['message'] ?? '') ? [['type' => 'text', 'text' => $msg['message']]] : $msg['message'];
            $messages[] = ['role' => $role, 'content' => $content];
        }
        $messages[] = ['role' => 'user', 'content' => [['type' => 'text', 'text' => $userMessage]]];

        $body = [
            'model' => $this->anthropicModel,
            'max_tokens' => $this->maxTokens,
            'system' => $systemPrompt,
            'messages' => $messages
        ];

        Log::debug("AIAgentService - Requête Anthropic", ['model' => $this->anthropicModel]);

        $response = Http::withHeaders([
            'x-api-key' => $this->anthropicKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json'
        ])
        ->withOptions(['verify' => false, 'timeout' => 45, 'connect_timeout' => 10])
        ->retry(2, 1000)
        ->post('https://api.anthropic.com/v1/messages', $body);

        if (!$response->successful()) {
            $errorBody = $response->body();
            $status = $response->status();
            Log::error("AIAgentService - Erreur Anthropic API", ['status' => $status, 'error' => $errorBody]);
            if ($status === 401) {
                throw new Exception("Clé API Anthropic invalide. Vérifiez ANTHROPIC_API_KEY dans .env");
            }
            if ($status === 404) {
                $err = $response->json();
                $msg = $err['error']['message'] ?? $errorBody;
                throw new Exception("Modèle Claude introuvable (404). Essayez ANTHROPIC_MODEL=claude-sonnet-4-5-20250929 dans .env. Détail: " . $msg);
            }
            if ($status === 429) {
                throw new Exception("Limite de taux Anthropic atteinte. Attendez quelques minutes.");
            }
            $err = $response->json();
            throw new Exception("Erreur Claude: " . ($err['error']['message'] ?? $errorBody));
        }

        $data = $response->json();
        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'] ?? '';
            }
        }
        if ($text === '') {
            throw new Exception("Réponse Anthropic invalide : " . json_encode($data));
        }
        $usage = $data['usage'] ?? [];
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;
        return [
            'message' => $text,
            'tokens_used' => $inputTokens + $outputTokens,
            'model_used' => $this->anthropicModel
        ];
    }

    /**
     * Appelle l'API Google Gemini
     */
    private function callGemini(string $systemPrompt, array $history, string $userMessage): array
    {
        $contents = [];
        foreach (array_slice($history, -16) as $msg) {
            $role = $msg['message_type'] === 'user' ? 'user' : 'model';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $msg['message']]]
            ];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

        $body = [
            'contents' => $contents,
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'generationConfig' => [
                'maxOutputTokens' => $this->maxTokens,
                'temperature' => $this->temperature,
            ]
        ];

        $geminiVersion = env('GEMINI_API_VERSION', 'v1beta');
        $url = 'https://generativelanguage.googleapis.com/' . $geminiVersion . '/models/' . $this->geminiModel . ':generateContent?key=' . urlencode($this->geminiKey);

        Log::debug("AIAgentService - Requête Gemini", ['model' => $this->geminiModel]);

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->withOptions(['verify' => false, 'timeout' => 45, 'connect_timeout' => 10])
            ->retry(2, 1000)
            ->post($url, $body);

        if (!$response->successful()) {
            $errorBody = $response->body();
            $status = $response->status();
            Log::error("AIAgentService - Erreur Gemini API", ['status' => $status, 'error' => $errorBody]);
            if ($status === 401 || $status === 403) {
                throw new Exception("Clé API Gemini invalide. Vérifiez GEMINI_API_KEY dans .env");
            }
            if ($status === 404) {
                $err = $response->json();
                $msg = $err['error']['message'] ?? $errorBody;
                throw new Exception("Modèle Gemini introuvable (404). Essayez GEMINI_MODEL=gemini-2.0-flash ou GEMINI_API_VERSION=v1 dans .env. Détail: " . $msg);
            }
            if ($status === 429) {
                $msg = ($response->json()['error']['message'] ?? '') ?: $errorBody;
                $hint = (str_contains($msg, 'quota') || str_contains($msg, 'billing'))
                    ? ' Vérifiez les quotas et la facturation sur https://aistudio.google.com/app/apikey'
                    : ' Attendez quelques minutes ou vérifiez les quotas Google AI.';
                throw new Exception("Gemini : quota ou limite de taux dépassé." . $hint);
            }
            $err = $response->json();
            throw new Exception("Erreur Gemini: " . ($err['error']['message'] ?? $errorBody));
        }

        $data = $response->json();
        $candidates = $data['candidates'] ?? [];
        if (empty($candidates) || empty($candidates[0]['content']['parts'])) {
            throw new Exception("Réponse Gemini invalide : " . json_encode($data));
        }
        $text = $candidates[0]['content']['parts'][0]['text'] ?? '';
        $usage = $data['usageMetadata'] ?? [];
        $total = ($usage['promptTokenCount'] ?? 0) + ($usage['candidatesTokenCount'] ?? 0);
        return [
            'message' => $text,
            'tokens_used' => $total,
            'model_used' => $this->geminiModel
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
     * Valide que l'agent IA est correctement configuré (au moins un fournisseur)
     */
    public function validateConfiguration(): array
    {
        $issues = [];
        $status = 'ok';
        $available = $this->getAvailableProviders();

        if (empty($available)) {
            $issues[] = 'Aucune clé API configurée. Ajoutez OPENAI_API_KEY, ANTHROPIC_API_KEY ou GEMINI_API_KEY dans .env';
            $status = 'error';
        }
        if (empty($this->openaiKey) || $this->openaiKey === 'sk-your-openai-key-here') {
            $issues[] = 'OPENAI_API_KEY manquante ou placeholder';
        } elseif (!str_starts_with($this->openaiKey, 'sk-')) {
            $issues[] = 'OPENAI_API_KEY semble invalide (doit commencer par sk-)';
            $status = $status === 'ok' ? 'warning' : $status;
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
                'model' => $this->openaiModel,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'api_configured' => !empty($available),
                'available_providers' => $available,
                'default_provider' => !empty($available) ? array_key_first($available) : null
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