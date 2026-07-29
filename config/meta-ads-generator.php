<?php

return [
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('META_ADS_OPENAI_MODEL', 'dall-e-3'),
        ],
        'stability' => [
            'api_key' => env('STABILITY_API_KEY'),
            'model' => env('META_ADS_STABILITY_MODEL', 'stable-diffusion-xl-1024-v1-0'),
        ],
        '9router' => [
            'url' => env('NINEROUTER_URL'),
            'api_key' => env('NINEROUTER_KEY'),
            'model' => env('META_ADS_9ROUTER_MODEL', 'openai/dall-e-3'),
        ],
    ],

    'default_provider' => env('META_ADS_DEFAULT_PROVIDER', 'openai'),

    'credits' => [
        'per_generation' => 1,
    ],

    'font_path' => env('META_ADS_FONT_PATH', null),
];