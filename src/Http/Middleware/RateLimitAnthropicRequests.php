<?php

namespace Ajz\Anthropic\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Ajz\Anthropic\Exceptions\RateLimitExceededException;

class RateLimitAnthropicRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws \Ajz\Anthropic\Exceptions\RateLimitExceededException
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('anthropic.rate_limiting.enabled', true)) {
            return $next($request);
        }

        $key = $this->resolveRequestSignature($request);
        $maxAttempts = config('anthropic.rate_limiting.max_requests', 60);
        $decayMinutes = config('anthropic.rate_limiting.decay_minutes', 1);

        $limiter = RateLimiter::attempt(
            $key,
            $maxAttempts,
            function() {
                return true;
            },
            $decayMinutes * 60
        );

        if (!$limiter) {
            throw new RateLimitExceededException(
                "Too many requests. Please try again in {$decayMinutes} minute(s).",
                $key,
                RateLimiter::availableIn($key)
            );
        }

        $response = $next($request);

        return $this->addRateLimitHeaders(
            $response,
            $maxAttempts,
            RateLimiter::remaining($key),
            RateLimiter::availableIn($key)
        );
    }

    /**
     * Resolve request signature.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $user = $request->user();

        return sha1(implode('|', [
            $user ? $user->getAuthIdentifier() : $request->ip(),
            $request->method(),
            $request->path(),
            $request->getHost()
        ]));
    }

    /**
     * Add rate limit headers to the response.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  int  $maxAttempts
     * @param  int  $remainingAttempts
     * @param  int  $retryAfter
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function addRateLimitHeaders(
        Response $response,
        int $maxAttempts,
        int $remainingAttempts,
        int $retryAfter
    ): Response {
        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
            'X-RateLimit-Reset' => $retryAfter,
        ]);
    }
}
