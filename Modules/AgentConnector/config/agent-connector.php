<?php

return [
    'ai' => [
        'url' => env('NINEROUTER_URL'),
        'api_key' => env('NINEROUTER_KEY'),
        'chat_model' => env('AGENT_CHAT_MODEL', 'gemini/gemma-4-31b-it'),
        'embedding_model' => env('AGENT_EMBEDDING_MODEL', 'gemini/gemini-embedding-001'),
    ],

    'memory' => [
        'max_results' => 5,
        'min_score' => 0.6,
    ],

    'session' => [
        'timeout_minutes' => 30,
        'max_context_messages' => 10,
    ],
];
