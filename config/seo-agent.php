<?php

return [
    'fonnte' => [
        'api_url' => env('FONNTE_API_URL', 'https://api.fonnte.com'),
        'token' => env('FONNTE_TOKEN', ''),
        'webhook_secret' => env('FONNTE_WEBHOOK_SECRET', ''),
    ],

    'allowed_numbers' => explode(',', env('SEO_AGENT_ALLOWED_NUMBERS', '')),

    'max_message_length' => 1500,

    'rate_limit' => [
        'max_attempts' => 10,
        'decay_minutes' => 1,
    ],

    'google_trends' => [
        'enabled' => env('GOOGLE_TRENDS_ENABLED', false),
    ],
];
