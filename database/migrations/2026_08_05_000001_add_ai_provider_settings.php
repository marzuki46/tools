<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            ['ai.9router.chat_model', env('AGENT_CHAT_MODEL', 'openai/gpt-4o'), 'text', '9Router Chat Model'],
            ['ai.9router.embedding_model', env('AGENT_EMBEDDING_MODEL', 'gemini/gemini-embedding-001'), 'text', '9Router Embedding Model'],
            ['ai.openai.url', env('OPENAI_URL', 'https://api.openai.com/v1'), 'url', 'OpenAI Base URL'],
            ['ai.openai.chat_model', env('OPENAI_CHAT_MODEL', 'gpt-4o'), 'text', 'OpenAI Chat Model'],
            ['ai.openai.embedding_model', env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'), 'text', 'OpenAI Embedding Model'],
            ['ai.stability.url', env('STABILITY_URL', 'https://api.stability.ai/v1'), 'url', 'Stability AI Base URL'],
            ['ai.stability.chat_model', env('STABILITY_CHAT_MODEL', 'stable-diffusion-xl-1024-v1-0'), 'text', 'Stability AI Chat Model'],
        ];

        foreach ($settings as [$key, $value, $type, $description]) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => $value,
                    'group' => 'ai-providers',
                    'type' => $type,
                    'description' => $description,
                ]
            );
        }

        $providers = DB::table('settings')
            ->where('key', 'like', 'ai.%.url')
            ->pluck('key')
            ->map(fn ($k) => explode('.', $k)[1])
            ->unique()
            ->values()
            ->toJson();

        DB::table('settings')->updateOrInsert(
            ['key' => 'ai.providers'],
            [
                'value' => $providers,
                'group' => 'ai-providers',
                'type' => 'text',
                'description' => 'Daftar provider AI aktif',
            ]
        );
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'ai.9router.chat_model',
            'ai.9router.embedding_model',
            'ai.openai.url',
            'ai.openai.chat_model',
            'ai.openai.embedding_model',
            'ai.stability.url',
            'ai.stability.chat_model',
            'ai.providers',
        ])->delete();
    }
};
