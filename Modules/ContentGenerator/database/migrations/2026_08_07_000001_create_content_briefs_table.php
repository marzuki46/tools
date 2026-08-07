<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_briefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('api_key_website_id')->nullable();
            $table->string('target_keyword');
            $table->string('locale', 10)->default('id');
            $table->string('meta_title')->nullable();
            $table->string('h1_tag')->nullable();
            $table->string('url_slug')->nullable();
            $table->string('target_audience')->nullable();
            $table->text('pain_point')->nullable();
            $table->json('local_entities')->nullable();
            $table->json('keywords')->nullable();
            $table->json('raw_response')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_briefs');
    }
};
