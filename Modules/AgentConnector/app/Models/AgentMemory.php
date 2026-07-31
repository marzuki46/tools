<?php

namespace Modules\AgentConnector\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMemory extends Model
{
    protected $table = 'agent_memories';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'type',
        'key',
        'content',
        'embedding',
        'metadata',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'embedding' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOfType($q, string $type)
    {
        return $q->where('type', $type);
    }

    public function scopeByKey($q, string $key)
    {
        return $q->where('key', $key);
    }

    public static function remember(int $userId, string $type, string $key, string $content, ?array $metadata = null): self
    {
        return self::updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['type' => $type, 'content' => $content, 'metadata' => $metadata, 'updated_at' => now()],
        );
    }
}
