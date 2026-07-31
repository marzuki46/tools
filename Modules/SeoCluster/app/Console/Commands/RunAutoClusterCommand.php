<?php

namespace Modules\SeoCluster\Console\Commands;

use Illuminate\Console\Command;
use Modules\SeoCluster\Services\AutoClusterAgent;

class RunAutoClusterCommand extends Command
{
    protected $signature = 'seo-cluster:run
        {--cluster= : ID cluster tertentu yang ingin diproses}
        {--force : Abaikan jam posting & kuota harian}';

    protected $description = 'Jalankan siklus AutoClusterAgent untuk memproses keyword pending di cluster aktif';

    public function handle(AutoClusterAgent $agent): int
    {
        set_time_limit(0);

        $this->info('Memulai siklus AutoClusterAgent...');

        $clusterId = $this->option('cluster')
            ? (int) $this->option('cluster')
            : null;

        $stats = $agent->runCycle($clusterId, (bool) $this->option('force'));

        $this->table(
            ['Metric', 'Jumlah'],
            [
                ['Cluster dipindai', $stats['clusters_scanned']],
                ['Keyword diproses', $stats['keywords_processed']],
                ['Keyword published', $stats['keywords_published']],
                ['Keyword gagal', $stats['keywords_failed']],
                ['Dilewati', $stats['skipped']],
            ]
        );

        return 0;
    }
}
