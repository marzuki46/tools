<?php

return [
    'providers' => [
        '9router' => [
            'url' => env('NINEROUTER_URL'),
            'api_key' => env('NINEROUTER_KEY'),
            'model' => env('CONTENT_AI_MODEL', 'kr/deepseek-3.2'),
        ],
    ],

    'default_provider' => env('CONTENT_DEFAULT_PROVIDER', '9router'),

    'queue' => env('CONTENT_QUEUE', 'default'),

    'request_delay' => env('CONTENT_REQUEST_DELAY', 2),

    'include_external_links' => env('CONTENT_INCLUDE_EXTERNAL_LINKS', true),

    'ai_slop' => [
        'enabled' => env('CONTENT_AI_SLOP_ENABLED', true),
        'auto_fix_banned_words' => env('CONTENT_AI_SLOP_AUTO_FIX', false),
        'rewrite_enabled' => env('CONTENT_AI_SLOP_REWRITE_ENABLED', true),
        'max_retries' => (int) env('CONTENT_AI_SLOP_MAX_RETRIES', 2),
    ],
];
