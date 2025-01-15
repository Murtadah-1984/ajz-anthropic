<?php

namespace Tests\Unit\Http\Middleware;

use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Ajz\Anthropic\Http\Middleware\CacheAnthropicResponses;
use Illuminate\Foundation\Auth\User;

class CacheAnthropicResponsesTest extends TestCase
{
    protected CacheAnthropicResponses $middleware;
    protected Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new CacheAnthropicResponses();
        $this->request = Request::create('/api/test', 'GET', ['param' => 'value']);

        // Default config
        Config::set('anthropic.cache.enabled', true);
        Config::set('anthropic.cache.ttl', 3600);
        Config::set('anthropic.cache.store', 'array');
    }

    public function test_caches_successful_get_requests()
    {
        $responseData = ['status' => 'success', 'data' => ['key' => 'value']];

        Cache::shouldReceive('store')
            ->with('array')
            ->andReturnSelf();

        Cache::shouldReceive('has')
            ->once()
            ->andReturn(false);

        Cache::shouldReceive('put')
            ->once()
            ->withArgs(function ($key, $value, $ttl) use ($responseData) {
                return str_contains($key, 'anthropic:GET:/api/test:') &&
                    $value['content'] === $responseData &&
                    $value['status'] === 200 &&
                    $ttl === 3600;
            });

        $response = $this->middleware->handle($this->request, function ($request) use ($responseData) {
            return response()->json($responseData);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($responseData, json_decode($response->getContent(), true));
    }

    public function test_returns_cached_response_when_available()
    {
        $cachedData = [
            'content' => ['status' => 'success'],
            'status' => 200,
            'headers' => ['Content-Type' => ['application/json']],
        ];

        Cache::shouldReceive('store')
            ->with('array')
            ->andReturnSelf();

        Cache::shouldReceive('has')
            ->once()
            ->andReturn(true);

        Cache::shouldReceive('get')
            ->once()
            ->andReturn($cachedData);

        $response = $this->middleware->handle($this->request, function () {
            $this->fail('Closure should not be executed when cache hit');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($cachedData['content'], json_decode($response->getContent(), true));
    }

    public function test_skips_caching_when_disabled()
    {
        Config::set('anthropic.cache.enabled', false);

        Cache::shouldReceive('store')->never();
        Cache::shouldReceive('has')->never();
        Cache::shouldReceive('get')->never();
        Cache::shouldReceive('put')->never();

        $response = $this->middleware->handle($this->request, function ($request) {
            return response()->json(['status' => 'success']);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_skips_caching_non_get_requests()
    {
        $request = Request::create('/api/test', 'POST', ['data' => 'value']);

        Cache::shouldReceive('store')->never();
        Cache::shouldReceive('put')->never();

        $response = $this->middleware->handle($request, function ($request) {
            return response()->json(['status' => 'success']);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_generates_unique_cache_keys_for_different_requests()
    {
        $keys = [];

        Cache::shouldReceive('store')
            ->with('array')
            ->andReturnSelf();

        Cache::shouldReceive('has')
            ->andReturn(false);

        Cache::shouldReceive('put')
            ->withArgs(function ($key) use (&$keys) {
                $keys[] = $key;
                return true;
            });

        // Test different request scenarios
        $requests = [
            Request::create('/api/test', 'GET', ['a' => '1']),
            Request::create('/api/test', 'GET', ['a' => '2']),
            Request::create('/api/other', 'GET', ['a' => '1']),
        ];

        foreach ($requests as $request) {
            $this->middleware->handle($request, function ($request) {
                return response()->json(['status' => 'success']);
            });
        }

        // Verify unique keys
        $this->assertEquals(count($requests), count(array_unique($keys)));
    }

    public function test_includes_user_id_in_cache_key_when_authenticated()
    {
        $user = new User();
        $user->id = 123;

        $this->request->setUserResolver(function () use ($user) {
            return $user;
        });

        Cache::shouldReceive('store')
            ->with('array')
            ->andReturnSelf();

        Cache::shouldReceive('has')
            ->withArgs(function ($key) {
                return str_contains($key, 'user:123');
            })
            ->andReturn(false);

        Cache::shouldReceive('put');

        $this->middleware->handle($this->request, function ($request) {
            return response()->json(['status' => 'success']);
        });
    }

    public function test_respects_no_cache_headers()
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Cache-Control', 'no-cache');

        Cache::shouldReceive('store')->never();
        Cache::shouldReceive('put')->never();

        $response = $this->middleware->handle($request, function ($request) {
            return response()->json(['status' => 'success']);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_only_caches_successful_responses()
    {
        Cache::shouldReceive('store')
            ->with('array')
            ->andReturnSelf();

        Cache::shouldReceive('has')
            ->andReturn(false);

        Cache::shouldReceive('put')->never();

        $response = $this->middleware->handle($this->request, function ($request) {
            return response()->json(['error' => 'Not found'], 404);
        });

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_excludes_sensitive_headers_from_cache()
    {
        Cache::shouldReceive('store')
            ->with('array')
            ->andReturnSelf();

        Cache::shouldReceive('has')
            ->andReturn(false);

        Cache::shouldReceive('put')
            ->withArgs(function ($key, $value) {
                $headers = $value['headers'];
                return !isset($headers['set-cookie']) &&
                    !isset($headers['cache-control']) &&
                    !isset($headers['date']) &&
                    !isset($headers['expires']) &&
                    !isset($headers['pragma']);
            });

        $this->middleware->handle($this->request, function ($request) {
            return response()
                ->json(['status' => 'success'])
                ->withHeaders([
                    'Set-Cookie' => 'session=123',
                    'Cache-Control' => 'no-cache',
                    'X-Custom' => 'value'
                ]);
        });
    }
}
