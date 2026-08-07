<?php

namespace App\Listeners;

use App\Models\Setting;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Log;

class AutoEnableWorker
{
    public function handle(JobQueued $event): void
    {
        if (Setting::workerEnabled()) {
            return;
        }

        Setting::setValue('queue.worker_enabled', '1');
        Log::info('Queue worker auto-enabled via JobQueued event.', [
            'queue' => $event->queue,
            'job_id' => $event->id,
        ]);
    }
}
