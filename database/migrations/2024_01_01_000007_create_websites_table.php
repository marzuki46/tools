<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'domain']);
        });

        Schema::create('website_tool', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->string('api_key_hash')->unique()->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_ip')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'tool_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_tool');
        Schema::dropIfExists('websites');
    }
};
