<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\KeywordResearch\Jobs\ProcessKeywordResearchJob;
use Modules\KeywordResearch\Models\KeywordResearch;

class ProcessKeywordJob extends Command
{
    protected $signature = 'keyword:process {id}';
    protected $description = 'Process a keyword research job in background';

    public function handle(): int
    {
        set_time_limit(0);
        $id = (int) $this->argument('id');

        $research = KeywordResearch::find($id);
        if (!$research) {
            $this->error("Keyword research #{$id} not found.");
            return 1;
        }

        dispatch_sync(new ProcessKeywordResearchJob($research));
        $this->info("Keyword research #{$id} processed.");
        return 0;
    }
}
