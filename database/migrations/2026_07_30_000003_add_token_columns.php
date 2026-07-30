<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_key_website', function (Blueprint $table) {
            $table->bigInteger('tokens_in')->default(0)->after('last_ip');
            $table->bigInteger('tokens_out')->default(0)->after('tokens_in');
            $table->bigInteger('tokens_total')->default(0)->after('tokens_out');
        });

        Schema::table('content_generations', function (Blueprint $table) {
            $table->foreignId('api_key_website_id')->nullable()->constrained('api_key_website')->nullOnDelete()->after('user_id');
            $table->integer('tokens_in')->default(0)->after('raw_response');
            $table->integer('tokens_out')->default(0)->after('tokens_in');
            $table->integer('tokens_total')->default(0)->after('tokens_out');
        });

        Schema::table('keyword_researches', function (Blueprint $table) {
            $table->foreignId('api_key_website_id')->nullable()->constrained('api_key_website')->nullOnDelete()->after('user_id');
            $table->integer('tokens_in')->default(0)->after('webhook_sent_at');
            $table->integer('tokens_out')->default(0)->after('tokens_in');
            $table->integer('tokens_total')->default(0)->after('tokens_out');
        });
    }

    public function down(): void
    {
        Schema::table('api_key_website', function (Blueprint $table) {
            $table->dropColumn(['tokens_in', 'tokens_out', 'tokens_total']);
        });

        Schema::table('content_generations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('api_key_website_id');
            $table->dropColumn(['tokens_in', 'tokens_out', 'tokens_total']);
        });

        Schema::table('keyword_researches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('api_key_website_id');
            $table->dropColumn(['tokens_in', 'tokens_out', 'tokens_total']);
        });
    }
};
