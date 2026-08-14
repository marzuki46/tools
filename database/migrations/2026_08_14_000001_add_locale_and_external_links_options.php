<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_key_website', function (Blueprint $table) {
            $table->string('locale', 10)->nullable()->after('domain');
        });

        Schema::table('content_generations', function (Blueprint $table) {
            $table->boolean('include_external_links')->nullable()->after('link_sources');
        });
    }

    public function down(): void
    {
        Schema::table('api_key_website', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::table('content_generations', function (Blueprint $table) {
            $table->dropColumn('include_external_links');
        });
    }
};