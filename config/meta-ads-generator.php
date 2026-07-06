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
    ],

    'default_provider' => env('META_ADS_DEFAULT_PROVIDER', 'openai'),

    'credits' => [
        'per_generation' => 1,
    ],
];