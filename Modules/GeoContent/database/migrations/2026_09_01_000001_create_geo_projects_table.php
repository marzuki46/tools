<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('api_key_website_id')->nullable()->constrained('api_key_website')->nullOnDelete();
            $table->string('keyword_utama', 255);
            $table->string('locale', 5)->default('id');
            $table->json('competitor_urls')->nullable();
            $table->json('competitor_brands')->nullable();
            $table->foreignId('keyword_research_id')->nullable()->constrained('keyword_researches')->nullOnDelete();
            $table->string('status', 30)->default('draft'); // draft, researching, facts_ready, questions_ready, generating, review, published, failed
            $table->string('mode', 20)->default('baru'); // baru, revisi
            $table->unsignedBigInteger('wp_post_id')->nullable(); // untuk mode revisi
            $table->text('wp_post_before_snapshot')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_projects');
    }
};
