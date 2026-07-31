<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keyword_clusters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('parent_keyword');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('schedule', 20)->default('manual');
            $table->unsignedInteger('total_keywords')->default(0);
            $table->unsignedInteger('published_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('image_keyword')->nullable();
            $table->string('image_source', 30)->default('duckduckgo');
            $table->boolean('image_enabled')->default(true);
            $table->unsignedTinyInteger('image_per_article')->default(3);
            $table->unsignedTinyInteger('webp_quality')->default(80);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_clusters');
    }
};
