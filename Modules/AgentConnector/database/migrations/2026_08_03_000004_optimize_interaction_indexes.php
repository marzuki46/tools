<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_chat_messages', function (Blueprint $table) {
            $table->index(['user_id', 'session_id', 'id'], 'acm_user_session_id_idx');
        });

        Schema::table('agent_memories', function (Blueprint $table) {
            $table->index(['user_id', 'type', 'created_at'], 'am_user_type_created_idx');
        });

        Schema::table('content_analysis_reports', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'car_user_created_idx');
        });

        Schema::table('keyword_clusters', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'kc_user_created_idx');
            $table->index(['user_id', 'status'], 'kc_user_status_idx');
        });

        Schema::table('content_generations', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'idx_cg_user_created');
        });

        Schema::table('keyword_researches', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'idx_kr_user_created');
        });

        Schema::table('generation_memories', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'gm_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('agent_chat_messages', function (Blueprint $table) {
            $table->dropIndex('acm_user_session_id_idx');
        });

        Schema::table('agent_memories', function (Blueprint $table) {
            $table->dropIndex('am_user_type_created_idx');
        });

        Schema::table('content_analysis_reports', function (Blueprint $table) {
            $table->dropIndex('car_user_created_idx');
        });

        Schema::table('keyword_clusters', function (Blueprint $table) {
            $table->dropIndex('kc_user_created_idx');
            $table->dropIndex('kc_user_status_idx');
        });

        Schema::table('content_generations', function (Blueprint $table) {
            $table->dropIndex('idx_cg_user_created');
        });

        Schema::table('keyword_researches', function (Blueprint $table) {
            $table->dropIndex('idx_kr_user_created');
        });

        Schema::table('generation_memories', function (Blueprint $table) {
            $table->dropIndex('gm_user_created_idx');
        });
    }
};
