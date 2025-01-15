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
    'api' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1'),
        'version' => env('ANTHROPIC_API_VERSION', '2024-01-01'),
        'timeout' => env('ANTHROPIC_API_TIMEOUT', 30),
        'retry' => [
            'times' => env('ANTHROPIC_API_RETRY_TIMES', 3),
            'sleep' => env('ANTHROPIC_API_RETRY_SLEEP', 100),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache & Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching and rate limiting settings for the package
    |
    */
    'cache' => [
        // General cache settings
        'enabled' => env('ANTHROPIC_CACHE_ENABLED', true),
        'ttl' => env('ANTHROPIC_CACHE_TTL', 3600),
        'prefix' => env('ANTHROPIC_CACHE_PREFIX', 'anthropic:'),
        'store' => env('ANTHROPIC_CACHE_STORE', 'redis'),
        'tags_enabled' => env('ANTHROPIC_CACHE_TAGS_ENABLED', true),

        // Rate limiting specific cache settings
        'rate_limiting' => [
            'enabled' => env('ANTHROPIC_RATE_LIMITING_ENABLED', true),
            'max_requests' => env('ANTHROPIC_RATE_LIMIT_MAX_REQUESTS', 60),
            'decay_minutes' => env('ANTHROPIC_RATE_LIMIT_DECAY_MINUTES', 1),
            'key_prefix' => env('ANTHROPIC_RATE_LIMIT_PREFIX', 'anthropic:limit:'),
        ],
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
        'temperature' => env('ANTHROPIC_TEMPERATURE', 0.7),
        'top_p' => env('ANTHROPIC_TOP_P', 1.0),
        'timeout' => env('ANTHROPIC_TIMEOUT', 60),
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
        'enabled' => env('ANTHROPIC_LOGGING_ENABLED', true),
        'channel' => env('ANTHROPIC_LOG_CHANNEL', 'anthropic'),
        'level' => env('ANTHROPIC_LOG_LEVEL', 'info'),
        'separate_files' => env('ANTHROPIC_LOG_SEPARATE_FILES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Configuration
    |--------------------------------------------------------------------------
    |
    | Configure AI agents and their capabilities
    |
    */
    'agents' => [
        'developer' => [
            'class' => \Ajz\Anthropic\Agency\AiAgents\Specialized\DeveloperAgent::class,
            'capabilities' => ['code_generation', 'debugging', 'review'],
            'model' => env('ANTHROPIC_DEVELOPER_MODEL', 'claude-3-5-sonnet-20241022'),
            'max_tokens' => env('ANTHROPIC_DEVELOPER_MAX_TOKENS', 2048),
        ],
        'architect' => [
            'class' => \Ajz\Anthropic\Agency\AiAgents\Specialized\ArchitectAgent::class,
            'capabilities' => ['system_design', 'architecture_review'],
            'model' => env('ANTHROPIC_ARCHITECT_MODEL', 'claude-3-5-sonnet-20241022'),
            'max_tokens' => env('ANTHROPIC_ARCHITECT_MAX_TOKENS', 4096),
        ],
        'security' => [
            'class' => \Ajz\Anthropic\Agency\AiAgents\Specialized\SecurityAgent::class,
            'capabilities' => ['security_analysis', 'vulnerability_assessment'],
            'model' => env('ANTHROPIC_SECURITY_MODEL', 'claude-3-5-sonnet-20241022'),
            'max_tokens' => env('ANTHROPIC_SECURITY_MAX_TOKENS', 2048),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Team Configuration
    |--------------------------------------------------------------------------
    |
    | Configure AI teams and their member agents
    |
    */
    'teams' => [
        'development' => [
            'class' => \Ajz\Anthropic\Agency\Teams\DevelopmentTeam::class,
            'agents' => ['developer', 'architect', 'security'],
            'workflow' => 'sequential',
            'max_rounds' => env('ANTHROPIC_TEAM_MAX_ROUNDS', 5),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Define validation rules for request parameters and input data
    |
    */
    'validation' => [
        // Content validation
        'max_prompt_length' => env('ANTHROPIC_MAX_PROMPT_LENGTH', 4000),
        'max_context_length' => env('ANTHROPIC_MAX_CONTEXT_LENGTH', 8000),
        'min_prompt_length' => env('ANTHROPIC_MIN_PROMPT_LENGTH', 1),
        'allowed_mime_types' => [
            'text/plain',
            'application/json',
            'text/markdown',
            'text/csv',
            'application/xml',
        ],
        'max_file_size' => env('ANTHROPIC_MAX_FILE_SIZE', 1024 * 1024), // 1MB

        // Request validation
        'request' => [
            'max_retries' => env('ANTHROPIC_MAX_RETRIES', 3),
            'timeout' => env('ANTHROPIC_REQUEST_TIMEOUT', 30),
            'allowed_methods' => ['GET', 'POST'],
            'required_headers' => ['X-Api-Key', 'Content-Type'],
        ],

        // Response validation
        'response' => [
            'max_size' => env('ANTHROPIC_MAX_RESPONSE_SIZE', 10 * 1024 * 1024), // 10MB
            'allowed_formats' => ['json', 'text', 'stream'],
            'timeout' => env('ANTHROPIC_RESPONSE_TIMEOUT', 60),
        ],

        // Security validation
        'security' => [
            'allowed_ips' => env('ANTHROPIC_ALLOWED_IPS', '*'),
            'require_https' => env('ANTHROPIC_REQUIRE_HTTPS', true),
            'api_key_pattern' => '/^sk-[a-zA-Z0-9]{32,}$/',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Transformation
    |--------------------------------------------------------------------------
    |
    | Configure how API responses should be transformed and formatted.
    |
    */
    'response' => [
        // Enable/disable response transformation
        'transform_enabled' => env('ANTHROPIC_TRANSFORM_ENABLED', true),

        // Enable/disable response enveloping (wrapping responses in a standard format)
        'envelope_enabled' => env('ANTHROPIC_ENVELOPE_ENABLED', true),

        // Include metadata in responses (timing, version, etc.)
        'include_metadata' => env('ANTHROPIC_INCLUDE_METADATA', true),

        // Default messages for different response types
        'messages' => [
            'success' => env('ANTHROPIC_SUCCESS_MESSAGE', 'Request processed successfully'),
            'error' => env('ANTHROPIC_ERROR_MESSAGE', 'Request failed'),
        ],

        // Configure which response types should be transformed
        'transform_types' => [
            'json' => true,
            'stream' => false,
            'binary' => false,
        ],

        // Configure metadata fields to include
        'metadata_fields' => [
            'timestamp' => true,
            'duration' => true,
            'version' => true,
            'request_id' => true,
            'pagination' => true,
        ],
    ],
];
