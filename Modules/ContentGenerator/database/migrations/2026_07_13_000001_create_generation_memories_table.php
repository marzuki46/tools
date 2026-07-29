<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('content_generation_id')->nullable()->constrained('content_generations')->nullOnDelete();
            $table->string('keyword');
            $table->string('locale', 10)->default('id');
            $table->string('tone', 50)->nullable();
            $table->json('lsi_keywords')->nullable();
            $table->json('entities')->nullable();
            $table->text('summary')->nullable();
            $table->text('embedding')->nullable();
            $table->float('quality_score')->nullable();
            $table->timestamps();

            $table->index('keyword');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_memories');
    }
};
