<?php

namespace Modules\AgentConnector\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\AgentConnector\Models\AgentChatMessage;
use Modules\AgentConnector\Services\AgentConnectorService;
use Throwable;

class ProcessAgentMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public $tries = 1;

    public function __construct(
        public int $messageId,
        public int $userId,
        public string $sessionId,
        public string $input,
    ) {}

    public function handle(AgentConnectorService $service): void
    {
        $assistant = AgentChatMessage::find($this->messageId);

        if (!$assistant) {
            return;
        }

        $assistant->update(['status' => 'processing', 'stage' => 'Menganalisis pesan...']);

        try {
            $result = $service->processInput(
                userId: $this->userId,
                input: $this->input,
                source: 'web',
                sessionId: $this->sessionId,
                onProgress: fn (string $stage) => $assistant->update(['stage' => $stage]),
            );

            $assistant->update([
                'status' => 'completed',
                'content' => $result['response'],
                'intent' => $result['intent'],
                'tool_name' => $result['tool_called'],
                'tool_data' => $result['actions'] ?? null,
                'stage' => null,
            ]);
        } catch (Throwable $e) {
            $assistant->update([
                'status' => 'error',
                'content' => "⚠️ Gagal memproses: " . $e->getMessage(),
                'stage' => null,
            ]);
        }
    }
}
