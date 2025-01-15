<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Anthropic API Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure your Anthropic API settings. The API key is required
    | for making requests to the Anthropic API.
    |
    */

    'api_key' => env('ANTHROPIC_API_KEY'),

    'base_url' => env('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1'),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching settings for AI Assistants
    |
    */
    'cache' => [
        'enabled' => env('AI_ASSISTANT_CACHE_ENABLED', true),
        'ttl' => env('AI_ASSISTANT_CACHE_TTL', 3600), // 1 hour
        'prefix' => env('AI_ASSISTANT_CACHE_PREFIX', 'ai_assistant:'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Assistant Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for new AI Assistants
    |
    */
    'defaults' => [
        'model' => env('ANTHROPIC_DEFAULT_MODEL', 'claude-3-5-sonnet-20241022'),
        'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging settings for AI Assistant operations
    |
    */
    'logging' => [
        'enabled' => env('AI_ASSISTANT_LOGGING_ENABLED', true),
        'channel' => env('AI_ASSISTANT_LOG_CHANNEL', 'stack'),
    ],
];
