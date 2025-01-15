<?php

namespace Tests\Unit\Http\Middleware;

use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Ajz\Anthropic\Http\Middleware\RateLimitAnthropicRequests;
use Ajz\Anthropic\Exceptions\RateLimitExceededException;
use Illuminate\Foundation\Auth\User;

class RateLimitAnthropicRequestsTest extends TestCase
{
    protected RateLimitAnthropicRequests $middleware;
    protected Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new RateLimitAnthropicRequests();
        $this->request = new Request();

        // Default config
        Config::set('anthropic.rate_limiting.enabled', true);
        Config::set('anthropic.rate_limiting.max_requests', 60);
        Config::set('anthropic.rate_limiting.decay_minutes', 1);
    }

    public function test_allows_requests_within_limit()
    {
        RateLimiter::shouldReceive('attempt')
            ->once()
            ->andReturn(true);

        RateLimiter::shouldReceive('remaining')
            ->once()
            ->andReturn(59);

        RateLimiter::shouldReceive('availableIn')
            ->once()
            ->andReturn(60);

        $response = $this->middleware->handle($this->request, function ($request) {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));
        $this->assertTrue($response->headers->has('X-RateLimit-Reset'));
    }

    public function test_throws_exception_when_rate_limit_exceeded()
    {
        RateLimiter::shouldReceive('attempt')
            ->once()
            ->andReturn(false);

        RateLimiter::shouldReceive('availableIn')
            ->once()
            ->andReturn(60);

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Too many requests');

        $this->middleware->handle($this->request, function () {});
    }

    public function test_bypasses_rate_limiting_when_disabled()
    {
        Config::set('anthropic.rate_limiting.enabled', false);

        RateLimiter::shouldReceive('attempt')->never();

        $response = $this->middleware->handle($this->request, function ($request) {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_uses_user_id_for_authenticated_requests()
    {
        $user = new User();
        $user->id = 123;

        $request = Request::create('/');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        RateLimiter::shouldReceive('attempt')
            ->withArgs(function ($key) {
                return str_contains($key, '123');
            })
            ->once()
            ->andReturn(true);

        RateLimiter::shouldReceive('remaining')->once()->andReturn(59);
        RateLimiter::shouldReceive('availableIn')->once()->andReturn(60);

        $this->middleware->handle($request, function ($request) {
            return new Response('OK');
        });
    }

    public function test_uses_ip_address_for_guest_requests()
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        RateLimiter::shouldReceive('attempt')
            ->withArgs(function ($key) {
                return str_contains($key, '127.0.0.1');
            })
            ->once()
            ->andReturn(true);

        RateLimiter::shouldReceive('remaining')->once()->andReturn(59);
        RateLimiter::shouldReceive('availableIn')->once()->andReturn(60);

        $this->middleware->handle($request, function ($request) {
            return new Response('OK');
        });
    }

    public function test_rate_limit_headers_are_correct()
    {
        RateLimiter::shouldReceive('attempt')->once()->andReturn(true);
        RateLimiter::shouldReceive('remaining')->once()->andReturn(59);
        RateLimiter::shouldReceive('availableIn')->once()->andReturn(60);

        $response = $this->middleware->handle($this->request, function ($request) {
            return new Response('OK');
        });

        $this->assertEquals(60, $response->headers->get('X-RateLimit-Limit'));
        $this->assertEquals(59, $response->headers->get('X-RateLimit-Remaining'));
        $this->assertNotNull($response->headers->get('X-RateLimit-Reset'));
    }

    public function test_respects_custom_rate_limit_configuration()
    {
        Config::set('anthropic.rate_limiting.max_requests', 30);
        Config::set('anthropic.rate_limiting.decay_minutes', 2);

        RateLimiter::shouldReceive('attempt')
            ->withArgs(function ($key, $limit, $callback, $decay) {
                return $limit === 30 && $decay === 120;
            })
            ->once()
            ->andReturn(true);

        RateLimiter::shouldReceive('remaining')->once()->andReturn(29);
        RateLimiter::shouldReceive('availableIn')->once()->andReturn(120);

        $response = $this->middleware->handle($this->request, function ($request) {
            return new Response('OK');
        });

        $this->assertEquals(30, $response->headers->get('X-RateLimit-Limit'));
    }

    public function test_handles_different_request_signatures()
    {
        $requests = [
            Request::create('/api/v1/endpoint', 'GET'),
            Request::create('/api/v1/endpoint', 'POST'),
            Request::create('/api/v2/endpoint', 'GET'),
        ];

        $signatures = [];

        RateLimiter::shouldReceive('attempt')
            ->withArgs(function ($key) use (&$signatures) {
                $signatures[] = $key;
                return true;
            })
            ->times(count($requests))
            ->andReturn(true);

        RateLimiter::shouldReceive('remaining')->times(count($requests))->andReturn(59);
        RateLimiter::shouldReceive('availableIn')->times(count($requests))->andReturn(60);

        foreach ($requests as $request) {
            $this->middleware->handle($request, function ($request) {
                return new Response('OK');
            });
        }

        // Ensure each request got a unique signature
        $this->assertEquals(count($requests), count(array_unique($signatures)));
    }
}
