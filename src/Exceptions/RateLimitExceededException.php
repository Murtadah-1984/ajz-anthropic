<?php

namespace Ajz\Anthropic\Exceptions;

use Exception;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

class RateLimitExceededException extends Exception
{
    /**
     * The key that was rate limited.
     *
     * @var string
     */
    protected string $key;

    /**
     * The number of seconds remaining until the rate limit is reset.
     *
     * @var int
     */
    protected int $retryAfter;

    /**
     * Create a new rate limit exceeded exception.
     *
     * @param string $message
     * @param string $key
     * @param int $retryAfter
     */
    public function __construct(string $message, string $key, int $retryAfter)
    {
        parent::__construct($message, Response::HTTP_TOO_MANY_REQUESTS);

        $this->key = $key;
        $this->retryAfter = $retryAfter;
    }

    /**
     * Get the key that was rate limited.
     *
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get the number of seconds remaining until the rate limit is reset.
     *
     * @return int
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    /**
     * Render the exception into an HTTP response.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function render(): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'rate_limit_exceeded',
            'retry_after' => $this->retryAfter,
        ], Response::HTTP_TOO_MANY_REQUESTS, [
            'Retry-After' => $this->retryAfter,
            'X-RateLimit-Reset' => time() + $this->retryAfter,
        ]);
    }

    /**
     * Get the headers that should be sent with the response.
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return [
            'Retry-After' => $this->retryAfter,
            'X-RateLimit-Reset' => time() + $this->retryAfter,
        ];
    }

    /**
     * Convert the exception to a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return sprintf(
            '%s: [%s] %s (Retry after %d seconds)',
            static::class,
            $this->key,
            $this->getMessage(),
            $this->retryAfter
        );
    }

    /**
     * Create an instance for a specific rate limit key.
     *
     * @param string $key
     * @param int $retryAfter
     * @return static
     */
    public static function forKey(string $key, int $retryAfter): self
    {
        return new static(
            "Rate limit exceeded for key: {$key}",
            $key,
            $retryAfter
        );
    }

    /**
     * Create an instance for a specific user.
     *
     * @param int|string $userId
     * @param int $retryAfter
     * @return static
     */
    public static function forUser(int|string $userId, int $retryAfter): self
    {
        return new static(
            "Rate limit exceeded for user: {$userId}",
            "user:{$userId}",
            $retryAfter
        );
    }

    /**
     * Create an instance for an IP address.
     *
     * @param string $ip
     * @param int $retryAfter
     * @return static
     */
    public static function forIp(string $ip, int $retryAfter): self
    {
        return new static(
            "Rate limit exceeded for IP: {$ip}",
            "ip:{$ip}",
            $retryAfter
        );
    }
}
