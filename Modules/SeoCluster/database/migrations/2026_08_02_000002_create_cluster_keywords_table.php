<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cluster_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cluster_id')->constrained('keyword_clusters')->cascadeOnDelete();
            $table->string('keyword');
            $table->string('status', 30)->default('pending');
            $table->unsignedBigInteger('keyword_research_id')->nullable();
            $table->unsignedBigInteger('content_generation_id')->nullable();
            $table->string('post_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamps();

            $table->index('cluster_id');
            $table->index('status');
            $table->index(['cluster_id', 'status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_keywords');
    }
};
