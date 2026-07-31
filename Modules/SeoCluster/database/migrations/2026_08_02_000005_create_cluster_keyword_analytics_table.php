<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cluster_keyword_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cluster_keyword_id')->constrained('cluster_keywords')->cascadeOnDelete();
            $table->string('post_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedTinyInteger('posted_hour')->nullable();
            $table->float('quality_score')->nullable();
            $table->unsignedInteger('word_count')->nullable();
            $table->unsignedInteger('tokens_used')->default(0);
            $table->unsignedTinyInteger('image_count')->default(0);
            $table->timestamps();

            $table->index('cluster_keyword_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_keyword_analytics');
    }
};
