<?php

namespace Tests\Unit\Exceptions;

use Tests\TestCase;
use Illuminate\Http\Response;
use Ajz\Anthropic\Exceptions\RateLimitExceededException;

class RateLimitExceededExceptionTest extends TestCase
{
    public function test_exception_has_correct_properties()
    {
        $exception = new RateLimitExceededException(
            'Test message',
            'test-key',
            60
        );

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals('test-key', $exception->getKey());
        $this->assertEquals(60, $exception->getRetryAfter());
        $this->assertEquals(Response::HTTP_TOO_MANY_REQUESTS, $exception->getCode());
    }

    public function test_render_returns_correct_json_response()
    {
        $exception = new RateLimitExceededException(
            'Rate limit exceeded',
            'test-key',
            60
        );

        $response = $exception->render();
        $content = $response->getData(true);

        $this->assertEquals('Rate limit exceeded', $content['message']);
        $this->assertEquals('rate_limit_exceeded', $content['error']);
        $this->assertEquals(60, $content['retry_after']);
        $this->assertEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
    }

    public function test_headers_are_correctly_set()
    {
        $exception = new RateLimitExceededException(
            'Rate limit exceeded',
            'test-key',
            60
        );

        $headers = $exception->getHeaders();
        $response = $exception->render();

        $this->assertArrayHasKey('Retry-After', $headers);
        $this->assertArrayHasKey('X-RateLimit-Reset', $headers);
        $this->assertEquals(60, $headers['Retry-After']);
        $this->assertGreaterThan(time(), $headers['X-RateLimit-Reset']);

        $this->assertTrue($response->headers->has('Retry-After'));
        $this->assertTrue($response->headers->has('X-RateLimit-Reset'));
    }

    public function test_to_string_conversion()
    {
        $exception = new RateLimitExceededException(
            'Rate limit exceeded',
            'test-key',
            60
        );

        $string = (string) $exception;

        $this->assertStringContainsString('RateLimitExceededException', $string);
        $this->assertStringContainsString('test-key', $string);
        $this->assertStringContainsString('Rate limit exceeded', $string);
        $this->assertStringContainsString('60 seconds', $string);
    }

    public function test_static_factory_methods()
    {
        // Test forKey
        $exception = RateLimitExceededException::forKey('api-key', 60);
        $this->assertStringContainsString('api-key', $exception->getMessage());
        $this->assertEquals('api-key', $exception->getKey());

        // Test forUser
        $exception = RateLimitExceededException::forUser(123, 60);
        $this->assertStringContainsString('user: 123', $exception->getMessage());
        $this->assertEquals('user:123', $exception->getKey());

        // Test forIp
        $exception = RateLimitExceededException::forIp('127.0.0.1', 60);
        $this->assertStringContainsString('IP: 127.0.0.1', $exception->getMessage());
        $this->assertEquals('ip:127.0.0.1', $exception->getKey());
    }

    public function test_response_includes_correct_content_type()
    {
        $exception = new RateLimitExceededException(
            'Rate limit exceeded',
            'test-key',
            60
        );

        $response = $exception->render();

        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
    }

    public function test_retry_after_is_positive_integer()
    {
        $exception = new RateLimitExceededException(
            'Rate limit exceeded',
            'test-key',
            60
        );

        $this->assertIsInt($exception->getRetryAfter());
        $this->assertGreaterThan(0, $exception->getRetryAfter());
    }

    public function test_reset_timestamp_is_in_future()
    {
        $exception = new RateLimitExceededException(
            'Rate limit exceeded',
            'test-key',
            60
        );

        $headers = $exception->getHeaders();
        $resetTime = $headers['X-RateLimit-Reset'];

        $this->assertGreaterThan(time(), $resetTime);
        $this->assertLessThanOrEqual(time() + 60, $resetTime);
    }
}
