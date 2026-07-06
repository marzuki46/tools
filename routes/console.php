<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Juki\MetaAdsGenerator\Models\AdProject;
use App\Models\User;
use Illuminate\Support\Facades\Http;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Juki\MetaAdsGenerator\Models\AdGeneration;
use Juki\MetaAdsGenerator\Jobs\GenerateAdCreativeJob;
use Juki\MetaAdsGenerator\Services\PromptBuilderService;

Artisan::command('test:meta-ads', function () {
    $this->info('Membuat User & Project dummy...');
    
    $user = User::firstOrCreate(
        ['email' => 'test@juki.eu.org'],
        ['name' => 'Juki Test', 'password' => bcrypt('password')]
    );

    $project = AdProject::firstOrCreate(
        ['user_id' => $user->id, 'name' => 'Kopi Nusantara']
    );

    $this->info('Membuat Generation dummy...');
    
    $inputForm = [
        'product_name' => 'Kopi Nusantara',
        'headline' => 'Kopi Asli Indonesia',
        'vibe' => 'warm and energetic'
    ];
    
    $promptService = new PromptBuilderService();
    $compiledPrompt = $promptService->build($inputForm);

    $generation = AdGeneration::create([
        'project_id' => $project->id,
        'user_id' => $user->id,
        'input_form' => $inputForm,
        'compiled_prompt' => $compiledPrompt,
        'ai_provider' => '9router',
        'ai_model' => 'dall-e-3',
        'status' => 'queued',
    ]);

    $this->info('Dispatching Job...');
    
    // Dispatch job secara sinkron (langsung jalan di console)
    dispatch_sync(new GenerateAdCreativeJob($generation));

    $generation->refresh();

    $this->info('Status Akhir: ' . $generation->status);
    $this->info('Compiled Prompt: ' . $generation->compiled_prompt);
    
    if ($generation->status === 'done') {
        $this->info('Sukses! Hasil export (3 ukuran):');
        foreach ($generation->exports as $export) {
            $this->line("- {$export->placement}: {$export->final_image_path}");
        }
    } else {
        $this->error('Gagal memproses AI.');
    }
})->purpose('Test Meta Ads Generator secara langsung via CLI');
