<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'ai.9router.chat_model')
            ->whereIn('value', ['openai/gpt-4o', ''])
            ->update(['value' => 'kr/deepseek-3.2']);

        DB::table('settings')
            ->where('key', 'ai.default_provider')
            ->where('value', 'openai')
            ->update(['value' => '9router']);

        DB::table('settings')->updateOrInsert(
            ['key' => 'queue.worker_enabled'],
            [
                'value' => '1',
                'group' => 'queue',
                'type' => 'boolean',
                'description' => 'Status worker antrian (1 aktif, 0 mati)',
            ]
        );
    }

    public function down(): void
    {
        // tidak ada rollback yang aman untuk perbaikan data
    }
};
