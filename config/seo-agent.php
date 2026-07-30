<?php

return [
    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN', ''),
    ],

    'allowed_numbers' => explode(',', env('SEO_AGENT_ALLOWED_USERS', '')),

    'max_message_length' => 4000,

    'rate_limit' => [
        'max_attempts' => 10,
        'decay_minutes' => 1,
    ],

    'google_trends' => [
        'enabled' => env('GOOGLE_TRENDS_ENABLED', false),
    ],
];
