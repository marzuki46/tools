<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keyword_clusters', function (Blueprint $table) {
            $table->unsignedBigInteger('api_key_website_id')->nullable()->after('user_id')
                ->comment('Situs WP pemilik cluster; NULL = dibuat dari web UI');
            $table->index(['user_id', 'api_key_website_id']);
        });
    }

    public function down(): void
    {
        Schema::table('keyword_clusters', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'api_key_website_id']);
            $table->dropColumn('api_key_website_id');
        });
    }
};
