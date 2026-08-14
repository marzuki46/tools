<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $columnExists = Schema::hasColumn('business_profiles', 'api_key_website_id');

        if (!$columnExists) {
            Schema::table('business_profiles', function (Blueprint $table) {
                $table->foreignId('api_key_website_id')->nullable()->after('user_id');
            });
        }

        $hasFk = true;
        if ($driver === 'mysql') {
            $hasFk = DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
                ->where('TABLE_NAME', 'business_profiles')
                ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
                ->where('CONSTRAINT_NAME', 'business_profiles_api_key_website_id_foreign')
                ->exists();
        } elseif (!$columnExists) {
            $hasFk = false;
        }

        if (!$hasFk) {
            Schema::table('business_profiles', function (Blueprint $table) {
                $table->foreign('api_key_website_id')->references('id')->on('api_key_website')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('business_profiles', function (Blueprint $table) {
            $table->dropForeign(['api_key_website_id']);
            if (Schema::hasColumn('business_profiles', 'api_key_website_id')) {
                $table->dropColumn('api_key_website_id');
            }
        });
    }
};