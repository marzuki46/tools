<?php

return [
    'providers' => [
        'openai' => [
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('META_ADS_OPENAI_MODEL', 'dall-e-3'),
        ],
        'stability' => [
            'url' => env('STABILITY_URL', 'https://api.stability.ai/v1'),
            'api_key' => env('STABILITY_API_KEY'),
            'model' => env('META_ADS_STABILITY_MODEL', 'stable-diffusion-xl-1024-v1-0'),
        ],
        '9router' => [
            'url' => env('NINEROUTER_URL'),
            'api_key' => env('NINEROUTER_KEY'),
            'model' => env('META_ADS_9ROUTER_MODEL', 'openai/dall-e-3'),
            'chat_model' => env('META_ADS_9ROUTER_CHAT_MODEL', 'openai/gpt-4o'),
        ],
    ],

    'default_provider' => env('META_ADS_DEFAULT_PROVIDER', '9router'),

    'credits' => [
        'per_generation' => 1,
    ],

    /*
    | Path to a .ttf font file used for text overlays on ad creatives.
    | If not set, text overlays will be skipped with a log warning.
    | Example: storage_path('fonts/Roboto-Bold.ttf')
    */
    'font_path' => env('META_ADS_FONT_PATH', null),
];
