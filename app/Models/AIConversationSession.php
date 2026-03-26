<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIConversationSession extends Model
{
    protected $table = 'ai_agent_sessions';

    protected $primaryKey = 'session_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['session_id', 'user_id', 'title'];

    public function conversations(): HasMany
    {
        return $this->hasMany(AIConversation::class, 'session_id', 'session_id');
    }
}
