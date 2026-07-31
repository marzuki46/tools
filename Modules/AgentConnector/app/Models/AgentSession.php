<?php

namespace Modules\AgentConnector\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentSession extends Model
{
    protected $table = 'agent_sessions';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'session_id',
        'active_tool',
        'context',
        'intent',
        'started_at',
        'last_activity_at',
    ];

    protected $casts = [
        'context' => 'array',
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function touch($attribute = null)
    {
        return $this->update(['last_activity_at' => now()]);
    }
}
