<?php

namespace Modules\MetaAdsImageGenerator\Services;

class ModerationService
{
    public function checkContent(string $text): bool
    {
        // Placeholder for moderation logic (e.g., checking against a blocklist or calling an API)
        $blockedWords = ['nsfw', 'illegal', 'violence'];

        foreach ($blockedWords as $word) {
            if (stripos($text, $word) !== false) {
                return false; // Failed moderation
            }
        }

        return true; // Passed moderation
    }
}