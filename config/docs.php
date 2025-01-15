<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Documentation Generation Settings
    |--------------------------------------------------------------------------
    |
    | Configure settings for automatic documentation generation, including
    | output paths, formatting rules, and generation options.
    |
    */
    'generation' => [
        'enabled' => env('ANTHROPIC_DOCS_GENERATION_ENABLED', true),
        'output_path' => env('ANTHROPIC_DOCS_OUTPUT_PATH', base_path('docs')),
        'clean_output' => env('ANTHROPIC_DOCS_CLEAN_OUTPUT', true),
        'format' => env('ANTHROPIC_DOCS_FORMAT', 'markdown'),
        'theme' => env('ANTHROPIC_DOCS_THEME', 'default'),
        'version' => env('ANTHROPIC_DOCS_VERSION', '1.0.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PHPDoc Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rules and standards for PHPDoc blocks throughout the codebase.
    | These settings will be used by documentation validators.
    |
    */
    'phpdoc' => [
        'required_tags' => [
            'param',
            'return',
            'throws',
        ],
        'optional_tags' => [
            'api',
            'author',
            'copyright',
            'deprecated',
            'example',
            'link',
            'method',
            'property',
            'since',
            'todo',
            'version',
        ],
        'inheritance' => [
            'inherit_doc' => true,
            'inherit_return' => true,
            'inherit_params' => true,
        ],
        'validation' => [
            'validate_param_types' => true,
            'validate_return_types' => true,
            'validate_throw_types' => true,
            'require_param_types' => true,
            'require_return_types' => true,
            'require_throw_types' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Documentation
    |--------------------------------------------------------------------------
    |
    | Configure settings for API documentation generation, including OpenAPI/Swagger
    | specifications and API reference documentation.
    |
    */
    'api' => [
        'enabled' => env('ANTHROPIC_API_DOCS_ENABLED', true),
        'title' => env('ANTHROPIC_API_DOCS_TITLE', 'Laravel Anthropic API'),
        'version' => env('ANTHROPIC_API_DOCS_VERSION', '1.0.0'),
        'base_path' => env('ANTHROPIC_API_DOCS_BASE_PATH', '/api'),
        'output_path' => env('ANTHROPIC_API_DOCS_OUTPUT_PATH', base_path('docs/api')),
        'format' => env('ANTHROPIC_API_DOCS_FORMAT', 'openapi'),
        'servers' => [
            [
                'url' => env('ANTHROPIC_API_DOCS_SERVER_URL', 'https://api.example.com'),
                'description' => 'Production API Server',
            ],
        ],
        'security_schemes' => [
            'api_key' => [
                'type' => 'apiKey',
                'name' => 'X-API-Key',
                'in' => 'header',
            ],
            'bearer' => [
                'type' => 'http',
                'scheme' => 'bearer',
            ],
        ],
        'tags' => [
            'agents' => 'Agent Management',
            'sessions' => 'Session Management',
            'knowledge' => 'Knowledge Base',
            'teams' => 'Team Management',
            'organizations' => 'Organization Management',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Code Examples
    |--------------------------------------------------------------------------
    |
    | Configure settings for code examples and usage documentation, including
    | supported languages and formatting options.
    |
    */
    'examples' => [
        'languages' => [
            'php',
            'javascript',
            'python',
            'ruby',
            'java',
            'curl',
        ],
        'format' => [
            'indent_size' => 4,
            'line_length' => 80,
            'show_comments' => true,
            'include_output' => true,
        ],
        'templates' => [
            'path' => base_path('docs/examples'),
            'extension' => '.example.{lang}',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration Documentation
    |--------------------------------------------------------------------------
    |
    | Configure settings for documenting configuration options and environment
    | variables used by the package.
    |
    */
    'config' => [
        'enabled' => env('ANTHROPIC_CONFIG_DOCS_ENABLED', true),
        'output_path' => env('ANTHROPIC_CONFIG_DOCS_OUTPUT_PATH', base_path('docs/config')),
        'format' => env('ANTHROPIC_CONFIG_DOCS_FORMAT', 'markdown'),
        'include_env' => env('ANTHROPIC_CONFIG_DOCS_INCLUDE_ENV', true),
        'group_by' => env('ANTHROPIC_CONFIG_DOCS_GROUP_BY', 'file'),
        'sections' => [
            'general' => 'General Configuration',
            'api' => 'API Configuration',
            'agents' => 'Agent Configuration',
            'sessions' => 'Session Configuration',
            'cache' => 'Cache Configuration',
            'queue' => 'Queue Configuration',
            'monitoring' => 'Monitoring Configuration',
            'security' => 'Security Configuration',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Testing
    |--------------------------------------------------------------------------
    |
    | Configure settings for testing documentation accuracy and completeness.
    | These tests help ensure documentation stays up to date.
    |
    */
    'testing' => [
        'enabled' => env('ANTHROPIC_DOCS_TESTING_ENABLED', true),
        'test_examples' => env('ANTHROPIC_DOCS_TEST_EXAMPLES', true),
        'validate_urls' => env('ANTHROPIC_DOCS_VALIDATE_URLS', true),
        'check_internal_links' => env('ANTHROPIC_DOCS_CHECK_INTERNAL_LINKS', true),
        'validate_code_blocks' => env('ANTHROPIC_DOCS_VALIDATE_CODE_BLOCKS', true),
        'validate_config_refs' => env('ANTHROPIC_DOCS_VALIDATE_CONFIG_REFS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Monitoring
    |--------------------------------------------------------------------------
    |
    | Configure settings for monitoring documentation health and identifying
    | areas that need updates or improvements.
    |
    */
    'monitoring' => [
        'enabled' => env('ANTHROPIC_DOCS_MONITORING_ENABLED', true),
        'driver' => env('ANTHROPIC_DOCS_MONITORING_DRIVER', 'log'),
        'channels' => ['daily'],
        'metrics' => [
            'coverage' => true,
            'freshness' => true,
            'completeness' => true,
            'accuracy' => true,
        ],
        'alert_thresholds' => [
            'coverage' => env('ANTHROPIC_DOCS_ALERT_COVERAGE', 90),
            'freshness_days' => env('ANTHROPIC_DOCS_ALERT_FRESHNESS', 90),
            'completeness' => env('ANTHROPIC_DOCS_ALERT_COMPLETENESS', 90),
            'accuracy' => env('ANTHROPIC_DOCS_ALERT_ACCURACY', 95),
        ],
    ],
];
