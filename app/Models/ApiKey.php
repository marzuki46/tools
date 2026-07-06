<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'key',
        'last_ip',
        'last_used_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generate(string $name, int $userId, ?string $expiresAt = null): array
    {
        $plainText = Str::random(40);
        $prefix = 'juki_';
        $fullKey = $prefix . $plainText;

        $apiKey = static::create([
            'user_id' => $userId,
            'name' => $name,
            'key' => hash('sha256', $fullKey),
            'expires_at' => $expiresAt,
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
