<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 255)->unique();
            $table->text('value')->nullable();
            $table->string('group', 100)->default('general');
            $table->string('type', 50)->default('text');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            ['key' => 'ai.9router.url', 'value' => env('NINEROUTER_URL', ''), 'group' => 'ai-providers', 'type' => 'url', 'description' => '9Router Base URL'],
            ['key' => 'ai.9router.api_key', 'value' => env('NINEROUTER_KEY', ''), 'group' => 'ai-providers', 'type' => 'password', 'description' => '9Router API Key'],
            ['key' => 'ai.9router.model', 'value' => env('META_ADS_9ROUTER_MODEL', 'openai/dall-e-3'), 'group' => 'ai-providers', 'type' => 'text', 'description' => '9Router Default Model'],
            ['key' => 'ai.9router.is_active', 'value' => 'true', 'group' => 'ai-providers', 'type' => 'boolean', 'description' => 'Enable 9Router Provider'],

            ['key' => 'ai.openai.api_key', 'value' => env('OPENAI_API_KEY', ''), 'group' => 'ai-providers', 'type' => 'password', 'description' => 'OpenAI API Key'],
            ['key' => 'ai.openai.model', 'value' => env('META_ADS_OPENAI_MODEL', 'dall-e-3'), 'group' => 'ai-providers', 'type' => 'text', 'description' => 'OpenAI Default Model'],
            ['key' => 'ai.openai.is_active', 'value' => 'true', 'group' => 'ai-providers', 'type' => 'boolean', 'description' => 'Enable OpenAI Provider'],

            ['key' => 'ai.stability.api_key', 'value' => env('STABILITY_API_KEY', ''), 'group' => 'ai-providers', 'type' => 'password', 'description' => 'Stability AI API Key'],
            ['key' => 'ai.stability.model', 'value' => env('META_ADS_STABILITY_MODEL', 'stable-diffusion-xl-1024-v1-0'), 'group' => 'ai-providers', 'type' => 'text', 'description' => 'Stability AI Default Model'],
            ['key' => 'ai.stability.is_active', 'value' => 'false', 'group' => 'ai-providers', 'type' => 'boolean', 'description' => 'Enable Stability AI Provider'],

            ['key' => 'ai.default_provider', 'value' => '9router', 'group' => 'ai-providers', 'type' => 'text', 'description' => 'Default AI Provider'],
            ['key' => 'ai.credits_per_generation', 'value' => '1', 'group' => 'ai-providers', 'type' => 'number', 'description' => 'Credits Used Per Generation'],
            ['key' => 'ai.font_path', 'value' => env('META_ADS_FONT_PATH', ''), 'group' => 'ai-providers', 'type' => 'text', 'description' => 'TTF Font Path for Text Overlay'],
        ];

        foreach ($defaults as $setting) {
            $setting['created_at'] = $now;
            $setting['updated_at'] = $now;
            DB::table('settings')->insert($setting);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
