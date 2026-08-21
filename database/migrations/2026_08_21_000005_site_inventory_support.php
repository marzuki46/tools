<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_content_urls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('api_key_website_id')->nullable()->index();
            $table->string('url', 500);
            $table->string('title', 255);
            $table->string('keyword', 255)->nullable();
            $table->timestamps();

            $table->unique(['api_key_website_id', 'url']);
        });

        Schema::table('api_key_website', function (Blueprint $table) {
            $table->string('site_name')->nullable()->after('domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_content_urls');

        Schema::table('api_key_website', function (Blueprint $table) {
            $table->dropColumn('site_name');
        });
    }
};
