<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Services\AIAgentService;
use App\Models\AIConversation;

class AIAgentController extends Controller
{
    private AIAgentService $aiAgent;
    
    public function __construct(AIAgentService $aiAgent)
    {
        $this->aiAgent = $aiAgent;
    }
    
    /**
     * Affiche la page de l'agent IA
     */
    public function index(): View
    {
        // Vérifier la configuration de l'agent
        $config = $this->aiAgent->validateConfiguration();
        
        // Sessions récentes de l'utilisateur
        $recentSessions = AIConversation::getActiveSessionsForUser(auth()->id());
        
        // Statistiques d'utilisation
        $usageStats = AIConversation::getUsageStats(7);
        
        return view('admin.ai-agent', compact('config', 'recentSessions', 'usageStats'));
    }
    
    /**
     * API pour poser une question à l'agent
     */
    public function ask(Request $request): JsonResponse
    {
        $request->validate([
            'question' => 'required|string|max:2000',
            'session_id' => 'nullable|string' // Changé de uuid vers string pour plus de flexibilité
        ]);
        
        // Vérifier que l'agent est configuré
        $config = $this->aiAgent->validateConfiguration();
        if ($config['status'] === 'error') {
            return response()->json([
                'success' => false,
                'error' => 'Agent IA non configuré correctement.',
                'issues' => $config['issues']
            ], 500);
        }
        
        $sessionId = $request->input('session_id') ?: Str::uuid()->toString();
        $question = trim($request->input('question'));
        $userId = auth()->id();
        
        // Validation rate limiting simple
        $recentQuestions = AIConversation::where('user_id', $userId)
            ->where('message_type', 'user')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();
            
        if ($recentQuestions >= 20) {
            return response()->json([
                'success' => false,
                'error' => '🚫 Trop de questions récentes. Attendez quelques minutes.',
                'retry_after' => 300
            ], 429);
        }
        
        Log::info("AIAgentController - Question reçue", [
            'user_id' => $userId,
            'session_id' => substr($sessionId, 0, 8),
            'question_length' => strlen($question)
        ]);
        
        $response = $this->aiAgent->ask($question, $sessionId, $userId);
        
        // Ajouter l'ID de session à la réponse
        $response['session_id'] = $sessionId;
        
        return response()->json($response);
    }
    
    /**
     * Récupère l'historique d'une conversation
     */
    public function getConversation(Request $request, string $sessionId): JsonResponse
    {
        $messages = AIConversation::bySession($sessionId)
            ->where('user_id', auth()->id())
            ->get()
            ->map(function($msg) {
                return [
                    'id' => $msg->id,
                    'type' => $msg->message_type,
                    'message' => $msg->message,
                    'timestamp' => $msg->created_at->toISOString(),
                    'tokens_used' => $msg->tokens_used,
                    'execution_time_ms' => $msg->execution_time_ms,
                    'context_types' => $msg->context_used ? array_keys($msg->context_used) : []
                ];
            });
        
        return response()->json([
            'success' => true,
            'session_id' => $sessionId,
            'messages' => $messages,
            'total_messages' => $messages->count()
        ]);
    }
    
    /**
     * Supprime une conversation
     */
    public function deleteConversation(Request $request, string $sessionId): JsonResponse
    {
        $deleted = AIConversation::where('session_id', $sessionId)
            ->where('user_id', auth()->id())
            ->delete();
        
        Log::info("AIAgentController - Conversation supprimée", [
            'session_id' => $sessionId,
            'user_id' => auth()->id(),
            'messages_deleted' => $deleted
        ]);
        
        return response()->json([
            'success' => true,
            'message' => "Conversation supprimée ($deleted messages)"
        ]);
    }

    /**
     * API pour les sessions récentes de l'utilisateur
     */
    public function getRecentSessions(): JsonResponse
    {
        $sessions = AIConversation::getActiveSessionsForUser(auth()->id());
        
        return response()->json([
            'success' => true,
            'sessions' => $sessions
        ]);
    }

    /**
     * Test rapide de l'agent IA
     */
    public function test(): JsonResponse
    {
        try {
            $testResult = $this->aiAgent->quickTest();
            
            return response()->json([
                'success' => true,
                'test_result' => $testResult
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Test échoué: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statistiques d'utilisation de l'agent
     */
    public function getStats(): JsonResponse
    {
        $stats = AIConversation::getUsageStats(30);
        
        // Statistiques par utilisateur
        $userStats = AIConversation::where('created_at', '>=', now()->subDays(30))
            ->where('message_type', 'user')
            ->selectRaw('user_id, COUNT(*) as questions_count')
            ->groupBy('user_id')
            ->orderBy('questions_count', 'desc')
            ->limit(10)
            ->get();
        
        // Questions les plus fréquentes (approximation par mots-clés)
        $popularTopics = AIConversation::where('created_at', '>=', now()->subDays(30))
            ->where('message_type', 'user')
            ->get()
            ->map(function($msg) {
                return $this->extractTopicsFromMessage($msg->message);
            })
            ->flatten()
            ->countBy()
            ->sortDesc()
            ->take(10);
        
        return response()->json([
            'success' => true,
            'global_stats' => $stats,
            'top_users' => $userStats,
            'popular_topics' => $popularTopics
        ]);
    }

    /**
     * Extrait les sujets d'un message pour les statistiques
     */
    private function extractTopicsFromMessage(string $message): array
    {
        $topics = [];
        $messageLower = strtolower($message);
        
        $topicKeywords = [
            'performance' => ['performance', 'taux', 'succès', 'kpi'],
            'segments' => ['segment', 'premium', 'regular', 'struggling', 'high risk'],
            'stratégies' => ['stratégie', 'quotidien', 'hebdo', 'mensuel', 'prix'],
            'ml' => ['modèle', 'prédiction', 'ml', 'features', 'algorithme'],
            'clients' => ['client', 'utilisateur', 'numéro'],
            'opérateurs' => ['timwe', 'eklektik', 'ooredoo', 'dgv']
        ];
        
        foreach ($topicKeywords as $topic => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($messageLower, $keyword)) {
                    $topics[] = $topic;
                    break;
                }
            }
        }
        
        return array_unique($topics);
    }
}