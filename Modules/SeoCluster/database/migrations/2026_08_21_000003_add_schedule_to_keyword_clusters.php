<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keyword_clusters', function (Blueprint $table) {
            $table->date('publish_start')->nullable()->after('url_template')
                ->comment('Awal rentang tanggal terbit (boleh mundur/tahun lalu)');
            $table->date('publish_end')->nullable()->after('publish_start')
                ->comment('Akhir rentang tanggal terbit');
            $table->decimal('tz_offset', 4, 1)->default(7)->after('publish_end')
                ->comment('Offset timezone situs WP dari UTC, misal 7 = WIB');
        });
    }

    public function down(): void
    {
        Schema::table('keyword_clusters', function (Blueprint $table) {
            $table->dropColumn(['publish_start', 'publish_end', 'tz_offset']);
        });
    }
};
