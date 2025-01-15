<?php

namespace Ajz\Anthropic\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Ajz\Anthropic\Exceptions\RateLimitExceededException;
use Symfony\Component\HttpFoundation\Response;

class RateLimitRequests
{
    /**
     * The rate limiter instance.
     *
     * @var \Illuminate\Cache\RateLimiter
     */
    protected $limiter;

    /**
     * Create a new middleware instance.
     *
     * @param \Illuminate\Cache\RateLimiter $limiter
     * @return void
     */
    public function __construct(\Illuminate\Cache\RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string|null $limiterName
     * @return Response
     * @throws RateLimitExceededException
     */
    public function handle(Request $request, Closure $next, ?string $limiterName = null): Response
    {
        // Get rate limit configuration
        $config = $this->getRateLimitConfig($request, $limiterName);

        // Generate unique key for this request
        $key = $this->resolveRequestSignature($request, $config);

        // Check rate limit
        $limit = $this->checkRateLimit($key, $config);

        if ($limit->exceeded) {
            // Log rate limit exceeded if enabled
            if (config('anthropic.logging.rate_limiting.enabled', false)) {
                $this->logRateLimitExceeded($request, $limit);
            }

            throw new RateLimitExceededException(
                'Too many requests',
                [
                    'retry_after' => $limit->retryAfter,
                    'limit' => $limit->maxAttempts,
                    'remaining' => 0,
                ]
            );
        }

        // Add rate limit headers to response
        $response = $next($request);
        return $this->addHeaders(
            $response,
            $limit->maxAttempts,
            $limit->remainingAttempts,
            $limit->retryAfter
        );
    }

    /**
     * Get rate limit configuration for the request.
     *
     * @param Request $request
     * @param string|null $limiterName
     * @return array
     */
    protected function getRateLimitConfig(Request $request, ?string $limiterName): array
    {
        // Get default config
        $config = config('anthropic.rate_limiting.default', [
            'max_attempts' => 60,
            'decay_minutes' => 1,
        ]);

        // Override with named limiter config if provided
        if ($limiterName) {
            $namedConfig = config("anthropic.rate_limiting.limiters.{$limiterName}");
            if ($namedConfig) {
                $config = array_merge($config, $namedConfig);
            }
        }

        // Apply user tier limits if available
        $userTier = $this->getUserTier($request);
        if ($userTier) {
            $tierConfig = config("anthropic.rate_limiting.tiers.{$userTier}");
            if ($tierConfig) {
                $config = array_merge($config, $tierConfig);
            }
        }

        return $config;
    }

    /**
     * Get the user's tier.
     *
     * @param Request $request
     * @return string|null
     */
    protected function getUserTier(Request $request): ?string
    {
        // Implementation depends on your user/authentication system
        return null;
    }

    /**
     * Resolve request signature for rate limiting.
     *
     * @param Request $request
     * @param array $config
     * @return string
     */
    protected function resolveRequestSignature(Request $request, array $config): string
    {
        $signature = [
            $request->method(),
            $request->path(),
        ];

        // Add API key if available
        if ($apiKey = $request->header('X-API-Key')) {
            $signature[] = $apiKey;
        }

        // Add IP address if configured
        if (!empty($config['include_ip'])) {
            $signature[] = $request->ip();
        }

        // Add user ID if authenticated and configured
        if (!empty($config['include_user']) && $request->user()) {
            $signature[] = $request->user()->id;
        }

        return sha1(implode('|', $signature));
    }

    /**
     * Check the rate limit for a key.
     *
     * @param string $key
     * @param array $config
     * @return object
     */
    protected function checkRateLimit(string $key, array $config): object
    {
        $maxAttempts = $config['max_attempts'] ?? 60;
        $decayMinutes = $config['decay_minutes'] ?? 1;

        // Check if limit is exceeded
        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return (object) [
                'exceeded' => true,
                'maxAttempts' => $maxAttempts,
                'remainingAttempts' => 0,
                'retryAfter' => $this->limiter->availableIn($key),
            ];
        }

        // Increment attempts
        $attempts = $this->limiter->hit($key, $decayMinutes * 60);

        return (object) [
            'exceeded' => false,
            'maxAttempts' => $maxAttempts,
            'remainingAttempts' => $maxAttempts - $attempts,
            'retryAfter' => null,
        ];
    }

    /**
     * Add rate limit headers to response.
     *
     * @param Response $response
     * @param int $limit
     * @param int $remaining
     * @param int|null $retryAfter
     * @return Response
     */
    protected function addHeaders(
        Response $response,
        int $limit,
        int $remaining,
        ?int $retryAfter
    ): Response {
        $response->headers->add([
            'X-RateLimit-Limit' => $limit,
            'X-RateLimit-Remaining' => $remaining,
        ]);

        if ($retryAfter !== null) {
            $response->headers->add([
                'Retry-After' => $retryAfter,
                'X-RateLimit-Reset' => time() + $retryAfter,
            ]);
        }

        return $response;
    }

    /**
     * Log rate limit exceeded event.
     *
     * @param Request $request
     * @param object $limit
     * @return void
     */
    protected function logRateLimitExceeded(Request $request, object $limit): void
    {
        Log::channel(config('anthropic.logging.rate_limiting.channel', 'daily'))
            ->warning('Rate limit exceeded', [
                'method' => $request->method(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'api_key' => $request->header('X-API-Key'),
                'limit' => $limit->maxAttempts,
                'retry_after' => $limit->retryAfter,
            ]);
    }
}
