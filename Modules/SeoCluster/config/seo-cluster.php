<?php

return [
    'wp' => [
        'url' => env('SEO_CLUSTER_WP_URL', env('WP_URL', '')),
        'username' => env('SEO_CLUSTER_WP_USERNAME', env('WP_USERNAME', '')),
        'password' => env('SEO_CLUSTER_WP_PASSWORD', env('WP_APP_PASSWORD', '')),
    ],

    'ping' => [
        'indexnow_key' => env('INDEXNOW_KEY', ''),
    ],

    'image' => [
        'source' => env('SEO_CLUSTER_IMAGE_SOURCE', 'duckduckgo'),
        'default_keyword' => env('SEO_CLUSTER_IMAGE_DEFAULT_KEYWORD', 'indonesia'),
        'max_per_article' => (int) env('SEO_CLUSTER_IMAGE_MAX_PER_ARTICLE', 3),
        'webp_quality' => (int) env('SEO_CLUSTER_IMAGE_WEBP_QUALITY', 80),
        'min_width' => 400,
        'min_height' => 300,
    ],

    'automation' => [
        'post_time_start' => env('SEO_CLUSTER_POST_TIME_START', '08:00'),
        'post_time_end' => env('SEO_CLUSTER_POST_TIME_END', '22:00'),
        'posts_per_day' => (int) env('SEO_CLUSTER_POSTS_PER_DAY', 3),
        'min_readability' => (int) env('SEO_CLUSTER_MIN_READABILITY', 50),
        'max_retries' => (int) env('SEO_CLUSTER_MAX_RETRIES', 3),
        'cycle_minutes' => (int) env('SEO_CLUSTER_CYCLE_MINUTES', 30),
    ],

    'silo' => [
        'url_pattern' => env('SEO_CLUSTER_URL_PATTERN', '{url}/{slug}/'),
        'pillar_target_words' => (int) env('SEO_CLUSTER_PILLAR_TARGET_WORDS', 2000),
    ],
];
