<?php

return [
    'queue' => env('GEO_QUEUE', 'default'),

    'fetch' => [
        'timeout' => (int) env('GEO_FETCH_TIMEOUT', 15),
        'max_redirects' => (int) env('GEO_FETCH_MAX_REDIRECTS', 5),
        'max_bytes' => (int) env('GEO_FETCH_MAX_BYTES', 5242880),
        'respect_robots' => env('GEO_RESPECT_ROBOTS', true),
        'max_urls' => (int) env('GEO_MAX_URLS', 5),
    ],

    'brand_scrub' => [
        'enabled' => env('GEO_BRAND_SCRUB_ENABLED', true),
    ],

    'copywriting' => [
        'framework' => env('GEO_COPYWRITING_FRAMEWORK', 'aida'), // aida
    ],

    'ai' => [
        'request_delay' => (int) env('GEO_REQUEST_DELAY', 2),
    ],
];
