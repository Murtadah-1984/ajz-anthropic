# Laravel Anthropic Middleware Stack

The Laravel Anthropic package includes a comprehensive middleware stack that handles API requests, responses, caching, rate limiting, and error handling. This document explains the middleware components and their configuration options.

## Overview

The middleware stack consists of the following components, executed in order:

1. `HandleAnthropicErrors` - Error handling and logging
2. `ValidateAnthropicConfig` - Configuration validation
3. `RateLimitAnthropicRequests` - API rate limiting
4. `LogAnthropicRequests` - Request/response logging
5. `CacheAnthropicResponses` - Response caching
6. `TransformAnthropicResponse` - Response transformation

## Configuration

The middleware stack can be configured in your `config/anthropic.php` file:

```php
return [
    // Rate Limiting Configuration
    'rate_limiting' => [
        'enabled' => env('ANTHROPIC_RATE_LIMITING_ENABLED', true),
        'max_requests' => env('ANTHROPIC_RATE_LIMIT_MAX_REQUESTS', 60),
        'decay_minutes' => env('ANTHROPIC_RATE_LIMIT_DECAY_MINUTES', 1),
        'cache_driver' => env('ANTHROPIC_RATE_LIMIT_CACHE_DRIVER', 'redis'),
    ],

    // Cache Configuration
    'cache' => [
        'enabled' => env('ANTHROPIC_CACHE_ENABLED', true),
        'ttl' => env('ANTHROPIC_CACHE_TTL', 3600),
        'prefix' => env('ANTHROPIC_CACHE_PREFIX', 'anthropic:'),
        'store' => env('ANTHROPIC_CACHE_STORE', 'redis'),
    ],

    // Logging Configuration
    'logging' => [
        'enabled' => env('ANTHROPIC_LOGGING_ENABLED', true),
        'channel' => env('ANTHROPIC_LOG_CHANNEL', 'anthropic'),
        'level' => env('ANTHROPIC_LOG_LEVEL', 'info'),
    ],

    // Response Transformation
    'response' => [
        'transform_enabled' => env('ANTHROPIC_TRANSFORM_ENABLED', true),
        'envelope_enabled' => env('ANTHROPIC_ENVELOPE_ENABLED', true),
        'include_metadata' => env('ANTHROPIC_INCLUDE_METADATA', true),
        'messages' => [
            'success' => env('ANTHROPIC_SUCCESS_MESSAGE', 'Request processed successfully'),
            'error' => env('ANTHROPIC_ERROR_MESSAGE', 'Request failed'),
        ],
    ],
];
```

## Usage

### Basic Usage

The middleware stack is automatically applied to all routes using the `anthropic` middleware group:

```php
Route::middleware('anthropic')->group(function () {
    Route::post('/messages', [AnthropicController::class, 'sendMessage']);
});
```

### Individual Middleware

You can also apply individual middleware components:

```php
Route::middleware('anthropic.rate-limit')->group(function () {
    // Only apply rate limiting
});

Route::middleware('anthropic.cache')->group(function () {
    // Only apply response caching
});
```

## Features

### Error Handling

The `HandleAnthropicErrors` middleware:

- Catches and logs exceptions
- Formats error responses consistently
- Sanitizes sensitive data
- Includes debug information when enabled
- Preserves original error context

Example error response:
```json
{
    "success": false,
    "status": 400,
    "message": "Invalid request",
    "error": {
        "type": "validation_error",
        "message": "The model field is required",
        "details": {
            "model": ["The model field is required"]
        }
    },
    "metadata": {
        "timestamp": "2024-03-22T10:30:00Z",
        "request_id": "req_abc123"
    }
}
```

### Rate Limiting

The `RateLimitAnthropicRequests` middleware:

- Enforces API rate limits
- Uses Redis or cache for limit tracking
- Adds rate limit headers to responses
- Configurable limits and windows
- Handles rate limit exceeded errors

Headers added:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1621123200
```

### Response Caching

The `CacheAnthropicResponses` middleware:

- Caches successful responses
- Configurable TTL and cache keys
- Supports cache tags when available
- Skips caching for streaming responses
- Preserves response headers

### Response Transformation

The `TransformAnthropicResponse` middleware:

- Standardizes response format
- Adds metadata and timestamps
- Handles pagination data
- Preserves original response data
- Supports response enveloping

Example transformed response:
```json
{
    "success": true,
    "status": 200,
    "message": "Request processed successfully",
    "data": {
        "id": "msg_abc123",
        "content": [
            {
                "type": "text",
                "text": "Response content"
            }
        ]
    },
    "metadata": {
        "timestamp": "2024-03-22T10:30:00Z",
        "duration_ms": 123,
        "version": "2024-01-01",
        "request_id": "req_abc123"
    }
}
```

## Performance

The middleware stack is designed for optimal performance:

- Efficient caching mechanisms
- Minimal memory footprint
- Quick response transformation
- Optimized error handling
- Smart rate limiting

Performance metrics (typical):
- Small payload response time: < 50ms
- Large payload response time: < 200ms
- Memory usage: < 10MB
- Cached response time: < 20ms

## Testing

The package includes comprehensive tests:

- Unit tests for each middleware
- Feature tests for the complete stack
- Integration tests with API
- Performance benchmarks
- Edge case handling

Run the tests:
```bash
php artisan test --filter=AnthropicMiddleware
```

## Best Practices

1. **Configuration**
   - Enable caching in production
   - Set appropriate rate limits
   - Configure logging channels
   - Use Redis for rate limiting

2. **Error Handling**
   - Monitor error logs
   - Set up error notifications
   - Handle rate limit errors gracefully
   - Validate input before API calls

3. **Caching**
   - Use appropriate TTL values
   - Clear cache when needed
   - Monitor cache size
   - Handle cache failures

4. **Response Handling**
   - Check response metadata
   - Handle streaming properly
   - Validate transformed data
   - Monitor response times

## Troubleshooting

Common issues and solutions:

1. **Rate Limiting Issues**
   - Check rate limit configuration
   - Verify Redis connection
   - Monitor rate limit headers
   - Handle rate limit errors

2. **Caching Problems**
   - Verify cache driver setup
   - Check cache keys
   - Monitor cache hit rate
   - Clear cache if needed

3. **Error Handling**
   - Check error logs
   - Verify error format
   - Test error scenarios
   - Monitor error rates

4. **Performance Issues**
   - Enable response caching
   - Optimize payload size
   - Monitor response times
   - Check memory usage
