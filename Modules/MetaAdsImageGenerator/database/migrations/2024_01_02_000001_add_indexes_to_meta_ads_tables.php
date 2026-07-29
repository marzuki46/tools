<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_generations', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
            $table->index(['user_id', 'status']);
        });

        Schema::table('ad_projects', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('ad_exports', function (Blueprint $table) {
            $table->index('generation_id');
        });

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('provider');
        });
    }

    public function down(): void
    {
        Schema::table('ad_generations', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('ad_projects', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('ad_exports', function (Blueprint $table) {
            $table->dropIndex(['generation_id']);
        });

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['provider']);
        });
    }
};
