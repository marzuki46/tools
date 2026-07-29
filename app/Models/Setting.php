<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'type', 'description'];

    protected $casts = [
        'value' => 'string',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('settings'));
        static::deleted(fn () => Cache::forget('settings'));
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $settings = Cache::remember('settings', 3600, function () {
            return static::pluck('value', 'key')->toArray();
        });

        $value = $settings[$key] ?? $default;

        if ($value === '' || $value === null) {
            return $default;
        }

        $setting = static::where('key', $key)->first();
        if ($setting && $setting->type === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if ($setting && $setting->type === 'number') {
            return is_numeric($value) ? (float) $value : $value;
        }

        return $value;
    }

    public static function getGroup(string $group): array
    {
        return Cache::rememberForever("settings_group_{$group}", function () use ($group) {
            return static::where('group', $group)->get()->toArray();
        });
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        Cache::forget('settings');
        Cache::forget('settings_group_ai-providers');
    }

    public static function providerConfig(string $provider): array
    {
        return [
            'api_key' => static::getValue("ai.{$provider}.api_key"),
            'model' => static::getValue("ai.{$provider}.model"),
            'url' => static::getValue("ai.{$provider}.url"),
            'is_active' => static::getValue("ai.{$provider}.is_active", false),
        ];
    }

    public static function defaultProvider(): string
    {
        return static::getValue('ai.default_provider', 'openai');
    }

    public static function fontPath(): ?string
    {
        $path = static::getValue('ai.font_path');
        return $path ?: null;
    }
}
