<?php

namespace Modules\AgentConnector\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentChatMessage extends Model
{
    protected $table = 'agent_chat_messages';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'session_id',
        'role',
        'content',
        'tool_name',
        'tool_data',
        'intent',
        'status',
        'stage',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'tool_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForSession($q, string $sessionId)
    {
        return $q->where('session_id', $sessionId);
    }

    public function scopeRole($q, string $role)
    {
        return $q->where('role', $role);
    }
}
