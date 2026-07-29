<?php

namespace App\Models\Websites;

use App\Models\Tools\Tool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WebsiteTool extends Model
{
    protected $table = 'website_tool';

    protected $fillable = [
        'website_id',
        'tool_id',
        'is_active',
        'config',
        'api_key_hash',
        'last_used_at',
        'last_ip',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'json',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key_hash',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public static function generateApiKey(int $websiteId, int $toolId): array
    {
        $plainText = Str::random(40);
        $prefix = 'wjt_';
        $fullKey = $prefix . $plainText;

        $websiteTool = static::updateOrCreate(
            ['website_id' => $websiteId, 'tool_id' => $toolId],
            ['api_key_hash' => hash('sha256', $fullKey)]
        );

        return [
            'website_tool' => $websiteTool,
            'plain_text' => $fullKey,
        ];
    }

    public static function authenticate(string $plainText): ?self
    {
        $hashed = hash('sha256', $plainText);
        return static::where('api_key_hash', $hashed)
            ->where('is_active', true)
            ->whereHas('website', fn ($q) => $q->where('is_active', true))
            ->whereHas('tool', fn ($q) => $q->where('is_active', true))
            ->first();
    }

    public function touchLastUsed(string $ip): void
    {
        $this->update([
            'last_used_at' => now(),
            'last_ip' => $ip,
        ]);
    }
}
