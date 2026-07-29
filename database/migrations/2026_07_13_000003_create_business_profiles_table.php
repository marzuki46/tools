<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->comment('Nama profil, mis: Website Utama, Toko Online');
            $table->string('business_name')->nullable()->comment('Nama bisnis/perusahaan');
            $table->string('website_url')->nullable();
            $table->text('description')->nullable()->comment('Deskripsi bisnis');
            $table->text('products_services')->nullable()->comment('Produk/jasa yang dijual');
            $table->string('target_audience')->nullable()->comment('Target pasar/audiens');
            $table->text('usp')->nullable()->comment('Unique selling points / keunggulan');
            $table->string('business_hours')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('address')->nullable();
            $table->text('social_media')->nullable()->comment('JSON: {"instagram":"...","facebook":"..."}');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
    }
};
