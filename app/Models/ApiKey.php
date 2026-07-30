<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'key',
        'key_prefix',
        'last_ip',
        'last_used_at',
        'expires_at',
        'is_active',
        'max_sites',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'key',
    ];

    protected $appends = [
        'suffix',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function websites(): HasMany
    {
        return $this->hasMany(ApiKeyWebsite::class);
    }

    public function getSuffixAttribute(): string
    {
        return $this->key_prefix ?? 'juki_...';
    }

    public function getStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'suspended';
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'expired';
        }
        return 'active';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function canUse(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    public static function generate(string $name, int $userId, ?string $expiresAt = null, ?int $maxSites = null): array
    {
        $plainText = Str::random(40);
        $prefix = 'juki_';
        $fullKey = $prefix . $plainText;

        $apiKey = static::create([
            'user_id' => $userId,
            'name' => $name,
            'key' => hash('sha256', $fullKey),
            'key_prefix' => $fullKey,
            'expires_at' => $expiresAt,
            'max_sites' => $maxSites,
        ]);

        return [
            'api_key' => $apiKey,
            'plain_text' => $fullKey,
        ];
    }

    public static function authenticate(string $plainText): ?self
    {
        $hashed = hash('sha256', $plainText);
        return static::where('key', $hashed)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
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
