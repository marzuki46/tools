<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_brand_kits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 7);
            $table->string('secondary_color', 7)->nullable();
            $table->string('font_family')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('ad_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('brand_kit_id')->nullable()->constrained('ad_brand_kits')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('ad_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('ad_projects')->onDelete('cascade');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->integer('size_kb');
            $table->timestamps();
        });

        Schema::create('ad_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('style_tag');
            $table->text('prompt_template');
            $table->string('thumbnail')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ad_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('ad_projects')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('preset_id')->nullable()->constrained('ad_presets')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('ad_assets')->nullOnDelete();
            $table->json('input_form');
            $table->text('compiled_prompt');
            $table->string('ai_provider');
            $table->string('ai_model');
            $table->json('ai_raw_response')->nullable();
            $table->string('seed')->nullable();
            $table->string('base_image_path')->nullable();
            $table->enum('status', ['queued', 'processing', 'done', 'failed'])->default('queued');
            $table->integer('credit_used')->default(1);
            $table->boolean('moderation_flag')->default(false);
            $table->timestamps();
        });

        Schema::create('ad_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generation_id')->constrained('ad_generations')->onDelete('cascade');
            $table->enum('placement', ['feed_square', 'story', 'feed_landscape']);
            $table->integer('width');
            $table->integer('height');
            $table->string('final_image_path');
            $table->json('overlay_config');
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('generation_id')->nullable()->constrained('ad_generations')->nullOnDelete();
            $table->string('provider');
            $table->integer('tokens_or_units');
            $table->decimal('estimated_cost', 10, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ad_exports');
        Schema::dropIfExists('ad_generations');
        Schema::dropIfExists('ad_presets');
        Schema::dropIfExists('ad_assets');
        Schema::dropIfExists('ad_projects');
        Schema::dropIfExists('ad_brand_kits');
    }
};