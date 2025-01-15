<?php

namespace Ajz\Anthropic\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheAnthropicResponses
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('anthropic.cache.enabled', true)) {
            return $next($request);
        }

        // Only cache GET requests
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        $key = $this->getCacheKey($request);
        $ttl = config('anthropic.cache.ttl', 3600);
        $store = config('anthropic.cache.store', config('cache.default'));

        // Check if we have a cached response
        if (Cache::store($store)->has($key)) {
            $cachedResponse = Cache::store($store)->get($key);
            return response()->json(
                $cachedResponse['content'],
                $cachedResponse['status'],
                $cachedResponse['headers']
            );
        }

        $response = $next($request);

        // Only cache successful responses
        if ($response->isSuccessful()) {
            $content = json_decode($response->getContent(), true);

            Cache::store($store)->put($key, [
                'content' => $content,
                'status' => $response->getStatusCode(),
                'headers' => $this->getCacheableHeaders($response),
            ], $ttl);
        }

        return $response;
    }

    /**
     * Generate a cache key for the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function getCacheKey(Request $request): string
    {
        $parts = [
            'anthropic',
            $request->method(),
            $request->path(),
            md5(json_encode($request->query())),
        ];

        // Add user-specific key if authenticated
        if ($user = $request->user()) {
            $parts[] = "user:{$user->getAuthIdentifier()}";
        }

        return implode(':', array_filter($parts));
    }

    /**
     * Get headers that should be cached.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return array
     */
    protected function getCacheableHeaders(Response $response): array
    {
        $headers = $response->headers->all();

        // Remove headers that shouldn't be cached
        $excludedHeaders = [
            'set-cookie',
            'cache-control',
            'date',
            'expires',
            'pragma',
        ];

        return array_filter(
            $headers,
            fn($key) => !in_array(strtolower($key), $excludedHeaders),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Determine if the request should be cached.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function shouldCache(Request $request): bool
    {
        // Don't cache if disabled in config
        if (!config('anthropic.cache.enabled', true)) {
            return false;
        }

        // Only cache GET requests
        if (!$request->isMethod('GET')) {
            return false;
        }

        // Don't cache requests with specific headers
        $noCacheHeaders = ['x-no-cache', 'cache-control'];
        foreach ($noCacheHeaders as $header) {
            if ($request->headers->has($header)) {
                $value = strtolower($request->headers->get($header));
                if (str_contains($value, 'no-cache') || str_contains($value, 'no-store')) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Add cache headers to the response.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  int  $ttl
     * @return void
     */
    protected function addCacheHeaders(Response $response, int $ttl): void
    {
        $response->headers->add([
            'X-Cache' => 'HIT',
            'Cache-Control' => "public, max-age={$ttl}",
            'Expires' => gmdate('D, d M Y H:i:s', time() + $ttl) . ' GMT',
        ]);
    }
}
