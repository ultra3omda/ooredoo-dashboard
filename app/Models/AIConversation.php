<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AIConversation extends Model
{
    protected $table = 'ai_agent_conversations';
    
    protected $fillable = [
        'user_id',
        'session_id',
        'message_type',
        'message',
        'context_used',
        'tokens_used',
        'model_used',
        'execution_time_ms'
    ];
    
    protected $casts = [
        'context_used' => 'array',
        'tokens_used' => 'integer',
        'execution_time_ms' => 'integer'
    ];

    /**
     * Relation avec l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Scope pour récupérer les messages d'une session
     */
    public function scopeBySession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId)
                    ->orderBy('created_at', 'asc');
    }

    /**
     * Scope pour les conversations récentes d'un utilisateur
     */
    public function scopeRecentByUser($query, int $userId, int $days = 7)
    {
        return $query->where('user_id', $userId)
                    ->where('created_at', '>=', Carbon::now()->subDays($days))
                    ->orderBy('created_at', 'desc');
    }

    /**
     * Récupère les sessions actives pour un utilisateur
     */
    public static function getActiveSessionsForUser(int $userId): array
    {
        return self::where('user_id', $userId)
                  ->where('created_at', '>=', Carbon::now()->subHours(24))
                  ->selectRaw('session_id, MAX(created_at) as created_at')
                  ->groupBy('session_id')
                  ->orderByRaw('MAX(created_at) desc')
                  ->limit(10)
                  ->pluck('created_at', 'session_id')
                  ->toArray();
    }

    /**
     * Statistiques d'utilisation de l'agent IA
     */
    public static function getUsageStats(int $days = 7): array
    {
        $stats = self::where('created_at', '>=', Carbon::now()->subDays($days))
                    ->where('message_type', 'user')
                    ->selectRaw('
                        COUNT(*) as total_questions,
                        COUNT(DISTINCT user_id) as unique_users,
                        COUNT(DISTINCT session_id) as unique_sessions,
                        AVG(execution_time_ms) as avg_response_time,
                        SUM(tokens_used) as total_tokens
                    ')
                    ->first();
                    
        return [
            'total_questions' => $stats->total_questions ?? 0,
            'unique_users' => $stats->unique_users ?? 0,
            'unique_sessions' => $stats->unique_sessions ?? 0,
            'avg_response_time_ms' => round($stats->avg_response_time ?? 0),
            'total_tokens_consumed' => $stats->total_tokens ?? 0
        ];
    }
}