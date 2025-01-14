<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Anthropic API Configuration
    |--------------------------------------------------------------------------
    */

    'api_key' => env('ANTHROPIC_API_KEY'),
    'admin_api_key' => env('ANTHROPIC_ADMIN_API_KEY'),
    'version' => env('ANTHROPIC_API_VERSION', '2023-06-01'),
    'base_url' => env('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1'),

    /*
    |--------------------------------------------------------------------------
    | API IP Ranges
    |--------------------------------------------------------------------------
    |
    | These are the official Anthropic API IP ranges. These ranges can be used
    | to configure firewall rules for egress traffic.
    |
    */

    'ip_ranges' => [
        'ipv4' => ['160.79.104.0/23'],
        'ipv6' => ['2607:6bc0::/48'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Models
    |--------------------------------------------------------------------------
    */

    'default_model' => env('ANTHROPIC_DEFAULT_MODEL', 'claude-3-5-sonnet-20241022'),
    'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 1024),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    */

    'http' => [
        'timeout' => env('ANTHROPIC_TIMEOUT', 30),
        'connect_timeout' => env('ANTHROPIC_CONNECT_TIMEOUT', 10),
        'retry_times' => env('ANTHROPIC_RETRY_TIMES', 3),
        'retry_sleep' => env('ANTHROPIC_RETRY_SLEEP', 500),
    ],
];
