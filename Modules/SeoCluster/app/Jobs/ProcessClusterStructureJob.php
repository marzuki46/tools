<?php

namespace Modules\SeoCluster\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\SeoCluster\Services\ClusterStructureService;

/**
 * Pembuatan struktur SILO memanggil AI yang bisa memakan waktu > 30 detik.
 * Dijalankan sebagai job queue agar tidak terkena batas max_execution_time
 * web host (pola sama dengan ProcessKeywordResearchJob).
 *
 * tries = 1: generateStructure tidak idempoten — retry bisa membuat cluster ganda.
 */
class ProcessClusterStructureJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 900;

    public function __construct(public array $payload)
    {
    }

    public function handle(ClusterStructureService $service): void
    {
        try {
            $clusters = $service->generateStructure(
                (int) $this->payload['user_id'],
                (string) $this->payload['topic'],
                (int) $this->payload['parent_count'],
                (int) $this->payload['child_count'],
            );
        } catch (Exception $e) {
            Log::error('Cluster structure job failed', [
                'topic' => $this->payload['topic'] ?? null,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        foreach ($clusters as $cluster) {
            $cluster->update([
                'url_template' => $this->payload['url_template'],
                'publish_start' => $this->payload['publish_start'],
                'publish_end' => $this->payload['publish_end'],
                'tz_offset' => $this->payload['tz_offset'],
                'api_key_website_id' => $this->payload['api_key_website_id'],
            ]);
        }

        Log::info('Cluster structure job completed', [
            'count' => count($clusters),
            'topic' => $this->payload['topic'],
        ]);
    }
}
