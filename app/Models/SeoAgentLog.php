<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoAgentLog extends Model
{
    protected $table = 'seo_agent_logs';

    protected $fillable = [
        'sender',
        'sender_name',
        'message',
        'command_type',
        'command_data',
        'response',
        'status',
        'error_message',
        'keyword_research_id',
        'content_generation_id',
        'processed_at',
    ];

    protected $casts = [
        'command_data' => 'array',
        'processed_at' => 'datetime',
    ];

    public function scopeBySender($q, string $sender)
    {
        return $q->where('sender', $sender);
    }

    public function scopeRecent($q, int $minutes = 60)
    {
        return $q->where('created_at', '>=', now()->subMinutes($minutes));
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }
}
