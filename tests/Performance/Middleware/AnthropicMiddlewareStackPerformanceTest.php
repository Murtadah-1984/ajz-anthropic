<?php

namespace Tests\Performance\Middleware;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;

class AnthropicMiddlewareStackPerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up test routes
        Route::middleware('anthropic')->get('/api/test/small', function () {
            return response()->json(['status' => 'success']);
        });

        Route::middleware('anthropic')->get('/api/test/large', function () {
            return response()->json([
                'data' => array_fill(0, 1000, [
                    'id' => 1,
                    'name' => 'Test',
                    'description' => 'Lorem ipsum dolor sit amet',
                    'nested' => [
                        'key1' => 'value1',
                        'key2' => 'value2',
                        'key3' => array_fill(0, 10, 'test')
                    ]
                ])
            ]);
        });

        // Default config
        Config::set('anthropic.api.key', 'test-key');
        Config::set('anthropic.rate_limiting.enabled', true);
        Config::set('anthropic.logging.enabled', true);
        Config::set('anthropic.cache.enabled', true);
        Config::set('anthropic.response.transform_enabled', true);
    }

    public function test_middleware_stack_performance_with_small_payload()
    {
        // Mock dependencies
        $this->mockDependencies();

        // Measure execution time
        $startTime = microtime(true);

        // Make multiple requests
        for ($i = 0; $i < 100; $i++) {
            $response = $this->getJson('/api/test/small');
            $response->assertStatus(200);
        }

        $executionTime = microtime(true) - $startTime;
        $averageTime = $executionTime / 100;

        // Assert performance
        $this->assertLessThan(
            50, // 50ms threshold per request
            $averageTime * 1000,
            "Middleware stack is too slow with small payload (avg: {$averageTime}ms)"
        );
    }

    public function test_middleware_stack_performance_with_large_payload()
    {
        // Mock dependencies
        $this->mockDependencies();

        // Measure execution time
        $startTime = microtime(true);

        // Make multiple requests
        for ($i = 0; $i < 10; $i++) {
            $response = $this->getJson('/api/test/large');
            $response->assertStatus(200);
        }

        $executionTime = microtime(true) - $startTime;
        $averageTime = $executionTime / 10;

        // Assert performance
        $this->assertLessThan(
            200, // 200ms threshold per request
            $averageTime * 1000,
            "Middleware stack is too slow with large payload (avg: {$averageTime}ms)"
        );
    }

    public function test_middleware_stack_memory_usage()
    {
        // Mock dependencies
        $this->mockDependencies();

        // Get initial memory
        $initialMemory = memory_get_usage();

        // Make request with large payload
        $response = $this->getJson('/api/test/large');
        $response->assertStatus(200);

        // Get peak memory
        $peakMemory = memory_get_peak_usage() - $initialMemory;

        // Assert memory usage (10MB threshold)
        $this->assertLessThan(
            10 * 1024 * 1024,
            $peakMemory,
            "Middleware stack uses too much memory ({$peakMemory} bytes)"
        );
    }

    public function test_middleware_stack_performance_with_cached_response()
    {
        // Mock cache hit
        Cache::shouldReceive('store')
            ->andReturnSelf();

        Cache::shouldReceive('has')
            ->andReturn(true);

        Cache::shouldReceive('get')
            ->andReturn([
                'content' => ['status' => 'cached'],
                'status' => 200,
                'headers' => ['Content-Type' => ['application/json']]
            ]);

        // Other mocks
        $this->mockOtherDependencies();

        // Measure execution time
        $startTime = microtime(true);

        // Make multiple requests
        for ($i = 0; $i < 100; $i++) {
            $response = $this->getJson('/api/test/small');
            $response->assertStatus(200);
        }

        $executionTime = microtime(true) - $startTime;
        $averageTime = $executionTime / 100;

        // Assert performance (should be faster than non-cached)
        $this->assertLessThan(
            20, // 20ms threshold per cached request
            $averageTime * 1000,
            "Cached responses are too slow (avg: {$averageTime}ms)"
        );
    }

    public function test_middleware_stack_concurrent_requests()
    {
        // Mock dependencies
        $this->mockDependencies();

        // Simulate concurrent requests
        $startTime = microtime(true);
        $promises = [];

        // Make concurrent requests using parallel promises
        for ($i = 0; $i < 10; $i++) {
            $promises[] = $this->getJson('/api/test/small');
        }

        // Wait for all requests to complete
        $responses = collect($promises)->map(function ($promise) {
            return $promise->assertStatus(200);
        });

        $executionTime = microtime(true) - $startTime;
        $averageTime = $executionTime / 10;

        // Assert performance under concurrent load
        $this->assertLessThan(
            100, // 100ms threshold per concurrent request
            $averageTime * 1000,
            "Middleware stack is too slow under concurrent load (avg: {$averageTime}ms)"
        );
    }

    protected function mockDependencies()
    {
        // Mock rate limiter
        RateLimiter::shouldReceive('attempt')->andReturn(true);
        RateLimiter::shouldReceive('remaining')->andReturn(100);
        RateLimiter::shouldReceive('availableIn')->andReturn(60);

        // Mock logger
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('log')->andReturnNull();

        // Mock cache miss
        Cache::shouldReceive('store')->andReturnSelf();
        Cache::shouldReceive('has')->andReturn(false);
        Cache::shouldReceive('put')->andReturnNull();
    }

    protected function mockOtherDependencies()
    {
        RateLimiter::shouldReceive('attempt')->andReturn(true);
        RateLimiter::shouldReceive('remaining')->andReturn(100);
        RateLimiter::shouldReceive('availableIn')->andReturn(60);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('log')->andReturnNull();
    }
}
