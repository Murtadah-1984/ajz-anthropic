<?php

namespace Tests\Unit\Http\Middleware;

use Tests\TestCase;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;
use Ajz\Anthropic\Http\Middleware\HandleAnthropicErrors;
use Ajz\Anthropic\Exceptions\InvalidConfigurationException;
use Ajz\Anthropic\Exceptions\RateLimitExceededException;

class HandleAnthropicErrorsTest extends TestCase
{
    protected HandleAnthropicErrors $middleware;
    protected Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new HandleAnthropicErrors();
        $this->request = Request::create('/api/test', 'POST', [
            'api_key' => 'secret-key',
            'password' => 'secret',
            'data' => 'test'
        ]);

        // Default config
        Config::set('anthropic.logging.enabled', true);
        Config::set('anthropic.logging.channel', 'anthropic');
        Config::set('app.debug', false);
    }

    public function test_handles_configuration_exception()
    {
        $exception = new InvalidConfigurationException(
            'Invalid API key',
            'api.key',
            ['required' => 'API key is required']
        );

        Log::shouldReceive('channel')
            ->once()
            ->with('anthropic')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Anthropic Error' &&
                    $context['exception'] === InvalidConfigurationException::class;
            });

        $response = $this->middleware->handle($this->request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertJsonStructure($response, ['message', 'error', 'details']);
        $this->assertStringContainsString('Invalid API key', $response->getData(true)['error']);
    }

    public function test_handles_rate_limit_exception()
    {
        $exception = new RateLimitExceededException(
            'Too many requests',
            'test-key',
            60
        );

        Log::shouldReceive('channel')
            ->once()
            ->with('anthropic')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once();

        $response = $this->middleware->handle($this->request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        $this->assertJsonStructure($response, ['message', 'error', 'retry_after']);
    }

    public function test_handles_anthropic_api_error()
    {
        $exception = new class('API Error') extends \Exception {
            public $anthropicCode = 'invalid_request';
        };

        Log::shouldReceive('channel')
            ->once()
            ->with('anthropic')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once();

        $response = $this->middleware->handle($this->request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertJsonStructure($response, ['message', 'error', 'type', 'code']);
        $this->assertEquals('api_error', $response->getData(true)['type']);
    }

    public function test_handles_unknown_exception()
    {
        $exception = new \RuntimeException('Unknown error');

        Log::shouldReceive('channel')
            ->once()
            ->with('anthropic')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once();

        $response = $this->middleware->handle($this->request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertJsonStructure($response, ['message', 'error', 'type']);
        $this->assertEquals('An unexpected error occurred', $response->getData(true)['error']);
    }

    public function test_includes_debug_information_when_enabled()
    {
        Config::set('app.debug', true);
        $exception = new \RuntimeException('Debug error');

        Log::shouldReceive('channel')
            ->once()
            ->with('anthropic')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once();

        $response = $this->middleware->handle($this->request, function () use ($exception) {
            throw $exception;
        });

        $data = $response->getData(true);
        $this->assertEquals('Debug error', $data['error']);
        $this->assertArrayHasKey('trace', $data);
    }

    public function test_sanitizes_sensitive_request_data()
    {
        $exception = new \Exception('Test error');

        Log::shouldReceive('channel')
            ->once()
            ->with('anthropic')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                $input = $context['request']['input'];
                return $input['api_key'] === '[REDACTED]' &&
                    $input['password'] === '[REDACTED]' &&
                    $input['data'] === 'test';
            });

        $this->middleware->handle($this->request, function () use ($exception) {
            throw $exception;
        });
    }

    public function test_sanitizes_sensitive_headers()
    {
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Bearer token');
        $request->headers->set('X-Api-Key', 'secret');
        $request->headers->set('Accept', 'application/json');

        $exception = new \Exception('Test error');

        Log::shouldReceive('channel')
            ->once()
            ->with('anthropic')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                $headers = $context['request']['headers'];
                return $headers['authorization'] === ['[REDACTED]'] &&
                    $headers['x-api-key'] === ['[REDACTED]'] &&
                    $headers['accept'] === ['application/json'];
            });

        $this->middleware->handle($request, function () use ($exception) {
            throw $exception;
        });
    }

    public function test_respects_logging_configuration()
    {
        Config::set('anthropic.logging.enabled', false);

        Log::shouldReceive('channel')->never();
        Log::shouldReceive('error')->never();

        $response = $this->middleware->handle($this->request, function () {
            throw new \Exception('Test error');
        });

        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    public function test_maps_http_status_codes_correctly()
    {
        $statusCodes = [
            400 => Response::HTTP_BAD_REQUEST,
            401 => Response::HTTP_UNAUTHORIZED,
            403 => Response::HTTP_FORBIDDEN,
            404 => Response::HTTP_NOT_FOUND,
            429 => Response::HTTP_TOO_MANY_REQUESTS,
            500 => Response::HTTP_INTERNAL_SERVER_ERROR,
        ];

        Log::shouldReceive('channel')
            ->times(count($statusCodes))
            ->with('anthropic')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->times(count($statusCodes));

        foreach ($statusCodes as $code => $expectedStatus) {
            $exception = new \Exception('Error', $code);

            $response = $this->middleware->handle($this->request, function () use ($exception) {
                throw $exception;
            });

            $this->assertEquals($expectedStatus, $response->getStatusCode());
        }
    }

    /**
     * Assert JSON response structure.
     *
     * @param  \Illuminate\Http\JsonResponse  $response
     * @param  array  $structure
     * @return void
     */
    protected function assertJsonStructure($response, array $structure): void
    {
        $data = $response->getData(true);
        foreach ($structure as $key) {
            $this->assertArrayHasKey($key, $data);
        }
    }
}
