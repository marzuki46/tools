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
];
