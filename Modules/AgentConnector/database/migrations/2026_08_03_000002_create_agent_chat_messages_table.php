<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_id');
            $table->string('role', 20)->default('assistant');
            $table->text('content')->nullable();
            $table->string('tool_name')->nullable();
            $table->json('tool_data')->nullable();
            $table->string('intent')->nullable();
            $table->string('status', 20)->default('completed');
            $table->string('stage')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

            $table->index(['user_id', 'session_id', 'created_at']);
            $table->index(['user_id', 'session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_chat_messages');
    }
};
