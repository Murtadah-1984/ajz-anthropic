<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;

class AnthropicMiddlewareStackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up test route
        Route::middleware('anthropic')->get('/api/test', function () {
            return response()->json(['status' => 'success']);
        });

        // Default config
        Config::set('anthropic.api.key', 'test-key');
        Config::set('anthropic.rate_limiting.enabled', true);
        Config::set('anthropic.logging.enabled', true);
        Config::set('anthropic.cache.enabled', true);
        Config::set('anthropic.response.transform_enabled', true);
        Config::set('anthropic.response.envelope_enabled', true);
        Config::set('anthropic.response.include_metadata', true);
    }

    public function test_middleware_stack_processes_successful_request_with_transformation()
    {
        // Mock rate limiter
        RateLimiter::shouldReceive('attempt')
            ->once()
            ->andReturn(true);

        RateLimiter::shouldReceive('remaining')
            ->once()
            ->andReturn(59);

        RateLimiter::shouldReceive('availableIn')
            ->once()
            ->andReturn(60);

        // Mock logger
        Log::shouldReceive('channel')
            ->once()
            ->with('anthropic')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->withArgs(function ($level, $message, $context) {
                return $level === 'info' &&
                    $message === 'Anthropic API Request' &&
                    $context['method'] === 'GET' &&
                    $context['status'] === 200;
            });

        // Mock cache
        Cache::shouldReceive('store')
            ->andReturnSelf();

        Cache::shouldReceive('has')
            ->once()
            ->andReturn(false);

        Cache::shouldReceive('put')
            ->once()
            ->withArgs(function ($key, $value, $ttl) {
                return str_contains($key, 'anthropic:GET:/api/test:') &&
                    $value['status'] === 200 &&
                    $ttl === 3600;
            });

        // Make request
        $response = $this->getJson('/api/test');

        // Assert response
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 200,
                'message' => 'Request processed successfully',
                'data' => ['status' => 'success']
            ])
            ->assertJsonStructure([
                'metadata' => [
                    'timestamp',
                    'duration_ms',
                    'version',
                    'request_id'
                ]
            ])
            ->assertHeader('X-RateLimit-Limit')
            ->assertHeader('X-RateLimit-Remaining');
    }

    public function test_middleware_stack_handles_rate_limit_exceeded_with_transformation()
    {
        RateLimiter::shouldReceive('attempt')
            ->once()
            ->andReturn(false);

        RateLimiter::shouldReceive('availableIn')
            ->once()
            ->andReturn(60);

        // Mock logger for rate limit failure
        Log::shouldReceive('channel')
            ->once()
            ->with('anthropic')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->withArgs(function ($level, $message, $context) {
                return $level === 'info' &&
                    $context['status'] === 429;
            });

        // Cache should not be called
        Cache::shouldReceive('store')->never();
        Cache::shouldReceive('put')->never();

        $response = $this->getJson('/api/test');

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'status' => 429,
                'message' => 'Request failed',
                'error' => [
                    'type' => 'rate_limit_exceeded'
                ]
            ])
            ->assertJsonStructure([
                'metadata' => [
                    'timestamp',
                    'duration_ms',
                    'version'
                ]
            ]);
    }

    public function test_middleware_stack_returns_transformed_cached_response()
    {
        $cachedData = [
            'content' => ['status' => 'cached'],
            'status' => 200,
            'headers' => ['Content-Type' => ['application/json']],
        ];

        Cache::shouldReceive('store')
            ->andReturnSelf();

        Cache::shouldReceive('has')
            ->once()
            ->andReturn(true);

        Cache::shouldReceive('get')
            ->once()
            ->andReturn($cachedData);

        // Rate limiter should not be called
        RateLimiter::shouldReceive('attempt')->never();

        // Logger should still record the cached response
        Log::shouldReceive('channel')
            ->once()
            ->with('anthropic')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->withArgs(function ($level, $message, $context) {
                return $context['status'] === 200;
            });

        $response = $this->getJson('/api/test');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 200,
                'data' => ['status' => 'cached']
            ])
            ->assertJsonStructure([
                'metadata' => [
                    'timestamp',
                    'duration_ms',
                    'version'
                ]
            ]);
    }

    public function test_middleware_stack_handles_invalid_configuration_with_transformation()
    {
        Config::set('anthropic.api.key', null);

        // Error should be logged
        Log::shouldReceive('channel')
            ->once()
            ->with('anthropic')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Anthropic Error' &&
                    str_contains($context['exception'], 'InvalidConfigurationException');
            });

        // Other middleware should not be called
        RateLimiter::shouldReceive('attempt')->never();
        Cache::shouldReceive('store')->never();

        $response = $this->getJson('/api/test');

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'status' => 500,
                'message' => 'Invalid configuration'
            ])
            ->assertJsonStructure([
                'error' => ['type', 'message', 'details'],
                'metadata' => ['timestamp', 'duration_ms', 'version']
            ]);
    }

    public function test_middleware_stack_handles_custom_error_messages()
    {
        Config::set('anthropic.response.messages.error', 'Custom error message');

        Route::middleware('anthropic')->get('/api/custom-error', function () {
            return response()->json(['error' => 'Test error'], 400);
        });

        $response = $this->getJson('/api/custom-error');

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Custom error message'
            ]);
    }

    public function test_middleware_stack_handles_nested_json_responses()
    {
        Route::middleware('anthropic')->get('/api/nested', function () {
            return response()->json([
                'data' => [
                    'user' => [
                        'id' => 1,
                        'settings' => [
                            'theme' => 'dark'
                        ]
                    ]
                ]
            ]);
        });

        $response = $this->getJson('/api/nested');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'data' => [
                        'user' => [
                            'id' => 1,
                            'settings' => [
                                'theme' => 'dark'
                            ]
                        ]
                    ]
                ]
            ]);
    }

    public function test_middleware_stack_preserves_response_headers()
    {
        Route::middleware('anthropic')->get('/api/headers', function () {
            return response()
                ->json(['status' => 'success'])
                ->header('X-Custom-Header', 'test-value')
                ->header('X-Version', '1.0');
        });

        $response = $this->getJson('/api/headers');

        $response->assertStatus(200)
            ->assertHeader('X-Custom-Header', 'test-value')
            ->assertHeader('X-Version', '1.0');
    }

    public function test_middleware_stack_handles_empty_arrays()
    {
        Route::middleware('anthropic')->get('/api/empty', function () {
            return response()->json([]);
        });

        $response = $this->getJson('/api/empty');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 200,
                'data' => []
            ]);
    }

    public function test_middleware_stack_handles_null_values()
    {
        Route::middleware('anthropic')->get('/api/null', function () {
            return response()->json([
                'value' => null,
                'array' => [null, null],
                'nested' => ['key' => null]
            ]);
        });

        $response = $this->getJson('/api/null');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'value' => null,
                    'array' => [null, null],
                    'nested' => ['key' => null]
                ]
            ]);
    }

    public function test_middleware_stack_handles_non_json_responses()
    {
        Route::middleware('anthropic')->get('/api/text', function () {
            return response('Plain text', 200, ['Content-Type' => 'text/plain']);
        });

        $response = $this->get('/api/text');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/plain')
            ->assertSee('Plain text');
    }

    public function test_middleware_stack_handles_binary_responses()
    {
        Route::middleware('anthropic')->get('/api/binary', function () {
            return response('Binary content', 200, ['Content-Type' => 'application/octet-stream']);
        });

        $response = $this->get('/api/binary');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/octet-stream');
    }

    public function test_middleware_stack_includes_pagination_metadata()
    {
        Route::middleware('anthropic')->get('/api/paginated', function () {
            return response()->json([
                'data' => ['items' => []],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 15,
                    'total' => 45,
                    'last_page' => 3
                ]
            ]);
        });

        $response = $this->getJson('/api/paginated');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'metadata' => [
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                        'total_pages'
                    ]
                ]
            ]);
    }
}
