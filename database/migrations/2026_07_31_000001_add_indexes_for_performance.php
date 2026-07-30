<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── api_keys ──
        Schema::table('api_keys', function (Blueprint $table) {
            $table->index('user_id', 'idx_api_keys_user_id');
            $table->index(['is_active', 'expires_at'], 'idx_api_keys_active_expires');
        });

        DB::statement('ALTER TABLE api_keys MODIFY key_prefix TEXT NULL');

        // Backfill existing keys: decrypt key_encrypted into key_prefix
        $keys = DB::table('api_keys')->whereNotNull('key_encrypted')->where(function ($q) {
            $q->whereNull('key_prefix')->orWhere(DB::raw('LENGTH(key_prefix)'), '<', 45);
        })->get();
        foreach ($keys as $k) {
            try {
                $plain = \Illuminate\Support\Facades\Crypt::decryptString($k->key_encrypted);
                DB::table('api_keys')->where('id', $k->id)->update(['key_prefix' => $plain]);
            } catch (\Exception) {
                // cannot decrypt, skip
            }
        }

        // ── api_key_website ──
        Schema::table('api_key_website', function (Blueprint $table) {
            $table->index('domain', 'idx_akw_domain');
            $table->index('last_used_at', 'idx_akw_last_used');
        });

        // ── content_generations ──
        Schema::table('content_generations', function (Blueprint $table) {
            $table->index('user_id', 'idx_cg_user_id');
            $table->index('api_key_website_id', 'idx_cg_akw_id');
            $table->index('status', 'idx_cg_status');
            $table->index('keyword_research_id', 'idx_cg_kr_id');
            $table->index(['user_id', 'status'], 'idx_cg_user_status');
            $table->index('created_at', 'idx_cg_created');
        });

        // ── keyword_researches ──
        Schema::table('keyword_researches', function (Blueprint $table) {
            $table->index('user_id', 'idx_kr_user_id');
            $table->index('api_key_website_id', 'idx_kr_akw_id');
            $table->index('status', 'idx_kr_status');
            $table->index(['user_id', 'status'], 'idx_kr_user_status');
            $table->index('created_at', 'idx_kr_created');
        });

        // ── jobs (queue) ──
        Schema::table('jobs', function (Blueprint $table) {
            $table->index(['queue', 'available_at', 'reserved_at'], 'idx_jobs_poll');
        });

        // ── failed_jobs ──
        if (Schema::hasTable('failed_jobs')) {
            Schema::table('failed_jobs', function (Blueprint $table) {
                $table->index('uuid', 'idx_failed_jobs_uuid');
            });
        }

        // ── business_profiles ──
        if (Schema::hasTable('business_profiles')) {
            Schema::table('business_profiles', function (Blueprint $table) {
                $table->index('user_id', 'idx_bp_user_id');
            });
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE api_keys MODIFY key_prefix VARCHAR(14) NULL');

        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropIndex('idx_api_keys_user_id');
            $table->dropIndex('idx_api_keys_active_expires');
        });

        Schema::table('api_key_website', function (Blueprint $table) {
            $table->dropIndex('idx_akw_domain');
            $table->dropIndex('idx_akw_last_used');
        });

        Schema::table('content_generations', function (Blueprint $table) {
            $table->dropIndex('idx_cg_user_id');
            $table->dropIndex('idx_cg_akw_id');
            $table->dropIndex('idx_cg_status');
            $table->dropIndex('idx_cg_kr_id');
            $table->dropIndex('idx_cg_user_status');
            $table->dropIndex('idx_cg_created');
        });

        Schema::table('keyword_researches', function (Blueprint $table) {
            $table->dropIndex('idx_kr_user_id');
            $table->dropIndex('idx_kr_akw_id');
            $table->dropIndex('idx_kr_status');
            $table->dropIndex('idx_kr_user_status');
            $table->dropIndex('idx_kr_created');
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->dropIndex('idx_jobs_poll');
        });

        if (Schema::hasTable('failed_jobs')) {
            Schema::table('failed_jobs', function (Blueprint $table) {
                $table->dropIndex('idx_failed_jobs_uuid');
            });
        }

        if (Schema::hasTable('business_profiles')) {
            Schema::table('business_profiles', function (Blueprint $table) {
                $table->dropIndex('idx_bp_user_id');
            });
        }
    }
};
