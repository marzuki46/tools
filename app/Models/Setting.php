<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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
            'chat_model' => static::getValue("ai.{$provider}.chat_model"),
            'embedding_model' => static::getValue("ai.{$provider}.embedding_model"),
            'url' => static::getValue("ai.{$provider}.url"),
            'is_active' => static::getValue("ai.{$provider}.is_active", false),
        ];
    }

    public static function defaultProvider(): string
    {
        return static::getValue('ai.default_provider', 'openai');
    }

    public static function providers(): array
    {
        $raw = static::getValue('ai.providers');
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded));
            }
        }

        return static::where('key', 'like', 'ai.%.url')
            ->pluck('key')
            ->map(fn ($key) => explode('.', $key)[1] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function addProvider(string $slug, array $values = []): void
    {
        $slug = Str::slug($slug, '_');
        if ($slug === '') {
            throw new \InvalidArgumentException('Nama provider tidak valid.');
        }

        $fields = [
            'url' => ['type' => 'url', 'description' => ucfirst($slug) . ' Base URL'],
            'api_key' => ['type' => 'password', 'description' => ucfirst($slug) . ' API Key'],
            'chat_model' => ['type' => 'text', 'description' => ucfirst($slug) . ' Chat Model'],
            'embedding_model' => ['type' => 'text', 'description' => ucfirst($slug) . ' Embedding Model'],
            'model' => ['type' => 'text', 'description' => ucfirst($slug) . ' Image Model'],
            'is_active' => ['type' => 'boolean', 'description' => 'Enable ' . ucfirst($slug) . ' Provider'],
        ];

        foreach ($fields as $field => $def) {
            static::updateOrCreate(
                ['key' => "ai.{$slug}.{$field}"],
                [
                    'value' => (string) ($values[$field] ?? ''),
                    'group' => 'ai-providers',
                    'type' => $def['type'],
                    'description' => $def['description'],
                ]
            );
        }

        $list = static::providers();
        if (!in_array($slug, $list, true)) {
            $list[] = $slug;
            static::setValue('ai.providers', json_encode(array_values($list)));
        }
    }

    public static function removeProvider(string $slug): void
    {
        static::where('key', 'like', "ai.{$slug}.%")->delete();

        $list = array_values(array_filter(static::providers(), fn ($p) => $p !== $slug));
        static::setValue('ai.providers', json_encode($list));
    }

    public static function aiConfig(?string $provider = null): array
    {
        $provider = $provider ?: static::defaultProvider();

        return [
            'url' => static::getValue("ai.{$provider}.url", config('agent-connector.ai.url')),
            'api_key' => static::getValue("ai.{$provider}.api_key", config('agent-connector.ai.api_key')),
            'chat_model' => static::getValue("ai.{$provider}.chat_model", config('agent-connector.ai.chat_model', 'openai/gpt-4o')),
            'embedding_model' => static::getValue("ai.{$provider}.embedding_model", config('agent-connector.ai.embedding_model', 'gemini/gemini-embedding-001')),
            'image_model' => static::getValue("ai.{$provider}.model"),
        ];
    }

    public static function fontPath(): ?string
    {
        $path = static::getValue('ai.font_path');
        return $path ?: null;
    }
}
