<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keyword_researches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('target_keyword');
            $table->string('locale', 10)->default('id');
            $table->json('lsi_keywords')->nullable();
            $table->json('entities')->nullable();
            $table->json('raw_response')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('source', 20)->default('manual');
            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->timestamp('webhook_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_researches');
    }
};
