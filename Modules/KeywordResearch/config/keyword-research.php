<?php

return [
    'providers' => [
        '9router' => [
            'url' => env('NINEROUTER_URL'),
            'api_key' => env('NINEROUTER_KEY'),
            'model' => env('KEYWORD_AI_MODEL', 'kr/deepseek-3.2'),
        ],
    ],

    'default_provider' => env('KEYWORD_DEFAULT_PROVIDER', '9router'),

    'queue' => env('KEYWORD_QUEUE', 'default'),

    'request_delay' => env('KEYWORD_REQUEST_DELAY', 2),

    'webhook' => [
        'retries' => 3,
        'timeout' => 15,
        'allowed_domains' => explode(',', env('KEYWORD_WEBHOOK_ALLOWED_DOMAINS', '')),
    ],
];
