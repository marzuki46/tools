<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class Background
{
    public static function run(string $command): void
    {
        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        $full = escapeshellarg($php) . ' ' . escapeshellarg($artisan) . ' ' . $command;

        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen("start /B {$full} > NUL 2>&1", 'r'));
        } else {
            exec("{$full} > /dev/null 2>&1 &");
        }

        Log::info('Background process spawned', ['command' => "{$command}"]);
    }
}
