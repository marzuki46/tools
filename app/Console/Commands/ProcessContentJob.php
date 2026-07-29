<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\ContentGenerator\Jobs\ProcessContentGenerationJob;
use Modules\ContentGenerator\Models\ContentGeneration;

class ProcessContentJob extends Command
{
    protected $signature = 'content:process {id} {phase?}';
    protected $description = 'Process a content generation job in background';

    public function handle(): int
    {
        set_time_limit(0);
        $id = (int) $this->argument('id');
        $phase = (int) ($this->argument('phase') ?? 3);

        $generation = ContentGeneration::find($id);
        if (!$generation) {
            $this->error("Content generation #{$id} not found.");
            return 1;
        }

        dispatch_sync(new ProcessContentGenerationJob($generation, $phase));
        $this->info("Content generation #{$id} processed.");
        return 0;
    }
}
