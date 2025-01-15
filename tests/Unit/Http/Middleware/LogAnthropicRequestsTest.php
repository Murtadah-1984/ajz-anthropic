<?php

namespace Tests\Unit\Http\Middleware;

use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Ajz\Anthropic\Http\Middleware\LogAnthropicRequests;
use Illuminate\Foundation\Auth\User;

class LogAnthropicRequestsTest extends TestCase
{
    protected LogAnthropicRequests $middleware;
    protected Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new LogAnthropicRequests();
        $this->request = Request::create('/api/test', 'POST', ['test' => 'data']);

        // Default config
        Config::set('anthropic.logging.enabled', true);
        Config::set('anthropic.logging.channel', 'anthropic');
        Config::set('anthropic.logging.level', 'info');
        Config::set('anthropic.logging.include_body', false);
    }

    public function test_logs_request_with_basic_information()
    {
        Log::shouldReceive('channel')
            ->once()
            ->with('anthropic')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->withArgs(function ($level, $message, $context) {
                return $level === 'info' &&
                    $message === 'Anthropic API Request' &&
                    $context['method'] === 'POST' &&
                    $context['url'] === 'http://localhost/api/test' &&
                    $context['status'] === 200 &&
                    is_float($context['duration']);
            });

        $response = $this->middleware->handle($this->request, function ($request) {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_skips_logging_when_disabled()
    {
        Config::set('anthropic.logging.enabled', false);

        Log::shouldReceive('channel')->never();
        Log::shouldReceive('log')->never();

        $response = $this->middleware->handle($this->request, function ($request) {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_sanitizes_sensitive_headers()
    {
        $request = Request::create('/api/test', 'POST');
        $request->headers->set('Authorization', 'Bearer secret-token');
        $request->headers->set('X-Api-Key', 'secret-key');
        $request->headers->set('Accept', 'application/json');

        Log::shouldReceive('channel')
            ->once()
            ->with('anthropic')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->withArgs(function ($level, $message, $context) {
                return $context['headers']['authorization'] === '[REDACTED]' &&
                    $context['headers']['x-api-key'] === '[REDACTED]' &&
                    $context['headers']['accept'] === ['application/json'];
            });

        $this->middleware->handle($request, function ($request) {
            return new Response('OK');
        });
    }

    public function test_includes_user_information_when_authenticated()
    {
        $user = new User();
        $user->id = 123;

        $this->request->setUserResolver(function () use ($user) {
            return $user;
        });

        Log::shouldReceive('channel')
            ->once()
            ->with('anthropic')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->withArgs(function ($level, $message, $context) {
                return $context['user_id'] === 123;
            });

        $this->middleware->handle($this->request, function ($request) {
            return new Response('OK');
        });
    }

    public function test_includes_request_body_when_configured()
    {
        Config::set('anthropic.logging.include_body', true);

        $request = Request::create('/api/test', 'POST', [
            'test' => 'data',
            'password' => 'secret',
            'api_key' => 'secret-key'
        ]);

        Log::shouldReceive('channel')
            ->once()
            ->with('anthropic')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->withArgs(function ($level, $message, $context) {
                return isset($context['request_body']) &&
                    $context['request_body']['test'] === 'data' &&
                    !isset($context['request_body']['password']) &&
                    !isset($context['request_body']['api_key']);
            });

        $this->middleware->handle($request, function ($request) {
            return new Response('OK');
        });
    }

    public function test_truncates_long_response_content()
    {
        Config::set('anthropic.logging.include_body', true);

        $longContent = str_repeat('a', 2000);

        Log::shouldReceive('channel')
            ->once()
            ->with('anthropic')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->withArgs(function ($level, $message, $context) {
                return strlen($context['response_body']) <= 1000 &&
                    str_ends_with($context['response_body'], '...');
            });

        $this->middleware->handle($this->request, function ($request) use ($longContent) {
            return new Response($longContent);
        });
    }

    public function test_handles_json_response_properly()
    {
        Config::set('anthropic.logging.include_body', true);

        $jsonResponse = ['status' => 'success', 'data' => ['key' => 'value']];

        Log::shouldReceive('channel')
            ->once()
            ->with('anthropic')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->withArgs(function ($level, $message, $context) use ($jsonResponse) {
                return $context['response_body'] === $jsonResponse;
            });

        $this->middleware->handle($this->request, function ($request) use ($jsonResponse) {
            return new Response(json_encode($jsonResponse), 200, ['Content-Type' => 'application/json']);
        });
    }

    public function test_respects_custom_log_channel_and_level()
    {
        Config::set('anthropic.logging.channel', 'custom');
        Config::set('anthropic.logging.level', 'debug');

        Log::shouldReceive('channel')
            ->once()
            ->with('custom')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->withArgs(function ($level, $message, $context) {
                return $level === 'debug';
            });

        $this->middleware->handle($this->request, function ($request) {
            return new Response('OK');
        });
    }

    public function test_handles_empty_response_content()
    {
        Config::set('anthropic.logging.include_body', true);

        Log::shouldReceive('channel')
            ->once()
            ->with('anthropic')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->withArgs(function ($level, $message, $context) {
                return $context['response_body'] === null;
            });

        $this->middleware->handle($this->request, function ($request) {
            return new Response();
        });
    }
}
