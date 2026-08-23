<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'ai.9router.chat_model')
            ->where('value', 'kr/deepseek-3.2')
            ->update(['value' => 'gemini/gemma-4-31b-it']);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'ai.9router.chat_model')
            ->where('value', 'gemini/gemma-4-31b-it')
            ->update(['value' => 'kr/deepseek-3.2']);
    }
};
