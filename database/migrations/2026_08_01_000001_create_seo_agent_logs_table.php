<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_agent_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sender', 30);
            $table->string('sender_name', 100)->nullable();
            $table->text('message');
            $table->string('command_type', 30)->nullable();
            $table->json('command_data')->nullable();
            $table->text('response')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('keyword_research_id')->nullable();
            $table->unsignedBigInteger('content_generation_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('sender');
            $table->index('command_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_agent_logs');
    }
};
