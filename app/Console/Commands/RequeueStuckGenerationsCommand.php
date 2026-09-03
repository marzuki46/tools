<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\ContentGenerator\Jobs\ProcessContentGenerationJob;
use Modules\ContentGenerator\Models\ContentGeneration;

class RequeueStuckGenerationsCommand extends Command
{
    protected $signature = 'content-generator:requeue-stuck
                            {--limit=50 : Jumlah maksimum item yang diproses sekaligus}
                            {--pending : Sertakan item pending stale (tidak punya job di antrian)}
                            {--dry-run : Tampilkan item tanpa mengubah apapun}';

    protected $description = 'Requeue content_generations yang failed/stuck kembali ke antrian (self-healing).';

    private const MAX_AGE_DAYS = 7;

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');
        $includePending = $this->option('pending');

        $total = 0;

        // 1. Item 'failed' → reset ke pending & re-dispatch. Aman: item yang
        //    sudah 'failed' tidak punya job aktif, jadi tidak ada duplikasi.
        $failed = ContentGeneration::where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(self::MAX_AGE_DAYS))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($failed->isNotEmpty()) {
            $this->info("Ditemukan {$failed->count()} item 'failed' untuk direqueue.");

            foreach ($failed as $gen) {
                if ($dryRun) {
                    $this->line("  [DRY RUN] id={$gen->id} keyword=\"{$gen->target_keyword}\" phase=" . ($gen->current_phase ?: 1));
                    continue;
                }

                $gen->update([
                    'status' => 'pending',
                    'retry_count' => 0,
                    'current_phase' => $gen->current_phase ?: 1,
                ]);

                ProcessContentGenerationJob::dispatch($gen->fresh(), $gen->current_phase ?: 1);
                $total++;
            }
        }

        // 2. (Opt-in) Item 'pending' stale yang TIDAK punya job di antrian —
        //    indikasi worker mati sebelum menjemput job-nya, jadi job-nya hilang.
        //    Hanya diproses jika --pending diberikan, agar tidak menimbulkan
        //    duplikasi job ketika worker sebenarnya sehat.
        if ($includePending) {
            $stale = $this->findStalePending($limit);
            foreach ($stale as $gen) {
                if ($dryRun) {
                    $this->line("  [DRY RUN][PENDING] id={$gen->id} keyword=\"{$gen->target_keyword}\" phase=" . ($gen->current_phase ?: 1));
                    continue;
                }

                $gen->update(['retry_count' => 0]);

                ProcessContentGenerationJob::dispatch($gen->fresh(), $gen->current_phase ?: 1);
                $total++;
            }

            if ($stale->isNotEmpty()) {
                $this->info("Ditemukan {$stale->count()} item 'pending' stale untuk re-dispatch (--pending).");
            }
        }

        if ($total === 0) {
            $this->info('Tidak ada item stuck. Semua aman.');
            return 0;
        }

        if ($dryRun) {
            $this->warn("Dry run selesai. {$total} item akan direqueue jika tanpa --dry-run.");
        } else {
            $this->info("Berhasil merequeue {$total} item ke antrian.");
        }

        return 0;
    }

    private function findStalePending(int $limit)
    {
        if ($limit <= 0) {
            return collect();
        }

        $pendingIds = ContentGeneration::where('status', 'pending')
            ->where('created_at', '>=', now()->subDays(self::MAX_AGE_DAYS))
            ->pluck('id');

        if ($pendingIds->isEmpty()) {
            return collect();
        }

        $stuckIds = collect();

        foreach ($pendingIds as $id) {
            // Ada job (delay lokal / antrian) yang memuat serialisasi job untuk
            // ContentGeneration id ini? Jika ada, barang akan diproses sendiri —
            // jangan buat duplikat.
            $hasJob = \DB::table('jobs')
                ->where('payload', 'LIKE', '%ProcessContentGenerationJob%')
                ->where('payload', 'LIKE', '%"id":' . (int) $id . '%')
                ->exists();

            if (!$hasJob) {
                $stuckIds->push($id);
            }
        }

        if ($stuckIds->isEmpty()) {
            return collect();
        }

        return ContentGeneration::whereIn('id', $stuckIds)
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }
}
