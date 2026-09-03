<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Modules\ContentGenerator\Jobs\ProcessContentGenerationJob;
use Modules\ContentGenerator\Models\ContentGeneration;
use Tests\TestCase;

class RequeueStuckGenerationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_generation_is_reset_to_pending_and_redispatched(): void
    {
        Bus::fake();

        $user = \App\Models\User::factory()->create();

        $gen = ContentGeneration::create([
            'user_id' => $user->id,
            'target_keyword' => 'Stenli Rattan Living Set',
            'locale' => 'id',
            'status' => 'failed',
            'current_phase' => 2,
            'retry_count' => 3,
        ]);

        $this->artisan('content-generator:requeue-stuck')
            ->expectsOutputToContain('Berhasil merequeue 1 item')
            ->assertExitCode(0);

        $this->assertDatabaseHas('content_generations', [
            'id' => $gen->id,
            'status' => 'pending',
            'retry_count' => 0,
        ]);

        Bus::assertDispatched(ProcessContentGenerationJob::class);
    }

    public function test_uid_failed_untouched_when_dry_run(): void
    {
        $user = \App\Models\User::factory()->create();

        $gen = ContentGeneration::create([
            'user_id' => $user->id,
            'target_keyword' => 'Maroco Rattan Living Set',
            'status' => 'failed',
            'current_phase' => 2,
            'retry_count' => 2,
        ]);

        $this->artisan('content-generator:requeue-stuck', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('content_generations', [
            'id' => $gen->id,
            'status' => 'failed',
            'retry_count' => 2,
        ]);
    }

    public function test_completed_generation_never_requeued(): void
    {
        Bus::fake();

        $user = \App\Models\User::factory()->create();

        ContentGeneration::create([
            'user_id' => $user->id,
            'target_keyword' => 'Dome Wicker Living Set',
            'status' => 'completed',
            'current_phase' => 3,
        ]);

        $this->artisan('content-generator:requeue-stuck')
            ->expectsOutputToContain('Tidak ada item stuck')
            ->assertExitCode(0);

        Bus::assertNotDispatched(ProcessContentGenerationJob::class);
    }
}
