<?php

namespace App\Jobs;

use App\Services\SeoAgent\SeoAgentOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SeoAgentProcessCommandJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $chatId,
        protected string $message,
        protected string $name,
    ) {}

    public function handle(SeoAgentOrchestrator $orchestrator): void
    {
        $orchestrator->handle($this->chatId, $this->message, $this->name);
    }
}
