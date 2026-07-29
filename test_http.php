<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$prompt = "A high-quality, professional commercial photograph of Sepatu Sendal Gunung. The visual style and vibe should be playful.";
$encodedPrompt = rawurlencode($prompt);
$url = "https://image.pollinations.ai/prompt/{$encodedPrompt}?width=1024&height=1024&nologo=true";

echo "URL: {$url}\n";
echo "URL length: " . strlen($url) . "\n";

$response = Illuminate\Support\Facades\Http::timeout(120)->get($url);

echo "Status: " . $response->status() . "\n";
echo "Content-Type: " . $response->header('Content-Type') . "\n";
echo "Body size: " . strlen($response->body()) . "\n";
echo "First bytes: " . bin2hex(substr($response->body(), 0, 4)) . "\n";
