# Laravel Anthropic Middleware API Reference

This document provides detailed technical specifications for each middleware component in the Laravel Anthropic package.

## HandleAnthropicErrors

Handles exceptions and error responses in the middleware stack.

### Class Definition
```php
class HandleAnthropicErrors
{
    public function handle(Request $request, Closure $next): Response
    public function handleException(Throwable $e): JsonResponse
    protected function formatError(Throwable $e): array
    protected function shouldIncludeDebugInfo(): bool
    protected function sanitizeContext(array $context): array
}
```

### Methods

#### handle()
- Purpose: Main middleware handler
- Parameters:
  - `$request`: Illuminate\Http\Request
  - `$next`: Closure
- Returns: Symfony\Component\HttpFoundation\Response
- Throws: Throwable

#### handleException()
- Purpose: Processes caught exceptions
- Parameters:
  - `$e`: Throwable
- Returns: Illuminate\Http\JsonResponse
- Response Format:
```json
{
    "success": false,
    "status": integer,
    "message": string,
    "error": {
        "type": string,
        "message": string,
        "details": object|null,
        "trace": array|null
    }
}
```

## ValidateAnthropicConfig

Validates the package configuration.

### Class Definition
```php
class ValidateAnthropicConfig
{
    public function handle(Request $request, Closure $next): Response
    protected function validateConfig(): void
    protected function getRequiredConfig(): array
}
```

### Configuration Requirements
```php
[
    'api.key' => 'string|required',
    'api.base_url' => 'string|required',
    'api.version' => 'string|required',
    'api.timeout' => 'integer|min:1',
]
```

## RateLimitAnthropicRequests

Implements rate limiting for API requests.

### Class Definition
```php
class RateLimitAnthropicRequests
{
    public function handle(Request $request, Closure $next): Response
    protected function resolveRequestSignature(Request $request): string
    protected function handleRateLimitExceeded(int $seconds): JsonResponse
    protected function addRateLimitHeaders(Response $response, RateLimiter $limiter): void
}
```

### Rate Limit Headers
```
X-RateLimit-Limit: Maximum requests per window
X-RateLimit-Remaining: Remaining requests in current window
X-RateLimit-Reset: Timestamp when the limit resets
```

## LogAnthropicRequests

Handles request and response logging.

### Class Definition
```php
class LogAnthropicRequests
{
    public function handle(Request $request, Closure $next): Response
    protected function logRequest(Request $request): void
    protected function logResponse(Response $response): void
    protected function formatRequestData(Request $request): array
    protected function formatResponseData(Response $response): array
}
```

### Log Format
```php
[
    'request' => [
        'method' => string,
        'path' => string,
        'headers' => array,
        'body' => array|string|null
    ],
    'response' => [
        'status' => integer,
        'headers' => array,
        'body' => array|string|null
    ],
    'duration_ms' => float,
    'timestamp' => string
]
```

## CacheAnthropicResponses

Implements response caching.

### Class Definition
```php
class CacheAnthropicResponses
{
    public function handle(Request $request, Closure $next): Response
    protected function getCacheKey(Request $request): string
    protected function shouldCache(Request $request, Response $response): bool
    protected function cacheResponse(string $key, Response $response): void
    protected function getCachedResponse(string $key): ?Response
}
```

### Cache Key Format
```
{prefix}:{method}:{path}:{hash}
```

### Cached Data Structure
```php
[
    'content' => array|string,
    'status' => integer,
    'headers' => array,
    'metadata' => array
]
```

## TransformAnthropicResponse

Transforms API responses into a standardized format.

### Class Definition
```php
class TransformAnthropicResponse
{
    public function handle(Request $request, Closure $next): Response
    protected function shouldTransform(Response $response): bool
    protected function transformResponse(Response $response, float $duration): array
    protected function getResponseData(Response $response): array
    protected function getResponseMessage(array $data, bool $isSuccess): ?string
    protected function getErrorDetails(array $data): ?array
    protected function isBinaryResponse(Response $response): bool
    protected function getPaginationMetadata(array $data): ?array
}
```

### Response Format
```json
{
    "success": boolean,
    "status": integer,
    "message": string,
    "data": object|null,
    "error": {
        "type": string,
        "message": string,
        "details": object|null
    }|null,
    "metadata": {
        "timestamp": string,
        "duration_ms": float,
        "version": string,
        "request_id": string,
        "pagination": {
            "current_page": integer,
            "per_page": integer,
            "total": integer,
            "total_pages": integer
        }|null
    }
}
```

## Events

### RequestProcessed
```php
class RequestProcessed
{
    public Request $request;
    public Response $response;
    public float $duration;
}
```

### RateLimitExceeded
```php
class RateLimitExceeded
{
    public Request $request;
    public int $availableIn;
}
```

### CacheHit
```php
class CacheHit
{
    public Request $request;
    public string $key;
}
```

### CacheMiss
```php
class CacheMiss
{
    public Request $request;
    public string $key;
}
```

## Constants

### Error Types
```php
const ERROR_TYPES = [
    'validation_error' => 400,
    'rate_limit_exceeded' => 429,
    'invalid_configuration' => 500,
    'api_error' => 502,
    'internal_error' => 500,
];
```

### Content Types
```php
const CACHEABLE_CONTENT_TYPES = [
    'application/json',
    'text/plain',
    'application/x-www-form-urlencoded',
];
```

### Cache Tags
```php
const CACHE_TAGS = [
    'anthropic',
    'api',
    'responses',
];
```

## Interfaces

### RateLimiterInterface
```php
interface RateLimiterInterface
{
    public function attempt(string $key, int $maxAttempts, int $decayMinutes): bool;
    public function remaining(string $key): int;
    public function availableIn(string $key): int;
    public function clear(string $key): void;
}
```

### ResponseTransformerInterface
```php
interface ResponseTransformerInterface
{
    public function transform(Response $response): array;
    public function shouldTransform(Response $response): bool;
    public function getMetadata(Response $response): array;
}
```

## Type Definitions

### RequestContext
```php
type RequestContext = array{
    method: string,
    path: string,
    headers: array<string, string>,
    body: array|string|null,
    timestamp: string,
};
```

### ResponseContext
```php
type ResponseContext = array{
    status: int,
    headers: array<string, string>,
    body: array|string|null,
    duration_ms: float,
    cache_hit: bool,
};
```

### ErrorContext
```php
type ErrorContext = array{
    type: string,
    message: string,
    code: int,
    details?: array<string, mixed>,
    trace?: array<int, array{
        file: string,
        line: int,
        function: string,
        args: array
    }>,
};
