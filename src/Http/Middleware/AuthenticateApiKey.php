<?php

namespace Ajz\Anthropic\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Ajz\Anthropic\Exceptions\AuthenticationException;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    /**
     * Cache key prefix for API keys.
     *
     * @var string
     */
    protected const CACHE_PREFIX = 'anthropic_api_key:';

    /**
     * Cache duration in minutes.
     *
     * @var int
     */
    protected const CACHE_DURATION = 60;

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $this->getApiKey($request);

        if (!$apiKey) {
            throw new AuthenticationException('API key is missing');
        }

        // Validate API key
        $keyData = $this->validateApiKey($apiKey);

        // Add API key data to request for downstream use
        $request->attributes->set('api_key_data', $keyData);

        // Add rate limit tier to request if available
        if (isset($keyData['tier'])) {
            $request->attributes->set('rate_limit_tier', $keyData['tier']);
        }

        return $next($request);
    }

    /**
     * Get API key from request.
     *
     * @param Request $request
     * @return string|null
     */
    protected function getApiKey(Request $request): ?string
    {
        // Check header first
        $key = $request->header('X-API-Key');
        if ($key) {
            return $key;
        }

        // Check query parameter
        $key = $request->query('api_key');
        if ($key) {
            return $key;
        }

        // Check bearer token
        $token = $request->bearerToken();
        if ($token && str_starts_with($token, 'ak_')) {
            return $token;
        }

        return null;
    }

    /**
     * Validate API key and return key data.
     *
     * @param string $apiKey
     * @return array
     * @throws AuthenticationException
     */
    protected function validateApiKey(string $apiKey): array
    {
        // Check cache first
        $cacheKey = self::CACHE_PREFIX . hash('sha256', $apiKey);
        $cachedData = Cache::get($cacheKey);

        if ($cachedData !== null) {
            return $this->validateCachedKeyData($cachedData, $apiKey);
        }

        // Validate against database
        $keyData = $this->lookupApiKey($apiKey);

        // Cache valid key data
        Cache::put($cacheKey, $keyData, self::CACHE_DURATION);

        return $keyData;
    }

    /**
     * Validate cached key data.
     *
     * @param array $data
     * @param string $apiKey
     * @return array
     * @throws AuthenticationException
     */
    protected function validateCachedKeyData(array $data, string $apiKey): array
    {
        // Check if key is revoked
        if (!empty($data['revoked'])) {
            $this->logFailedAuthentication('API key has been revoked', $apiKey);
            throw new AuthenticationException('Invalid API key');
        }

        // Check if key has expired
        if (!empty($data['expires_at']) && now()->isAfter($data['expires_at'])) {
            $this->logFailedAuthentication('API key has expired', $apiKey);
            throw new AuthenticationException('Invalid API key');
        }

        // Check if key is active
        if (empty($data['is_active'])) {
            $this->logFailedAuthentication('API key is inactive', $apiKey);
            throw new AuthenticationException('Invalid API key');
        }

        return $data;
    }

    /**
     * Look up API key in database.
     *
     * @param string $apiKey
     * @return array
     * @throws AuthenticationException
     */
    protected function lookupApiKey(string $apiKey): array
    {
        try {
            // Implementation depends on your API key storage
            // This is just an example structure
            $keyData = [
                'key' => $apiKey,
                'user_id' => 1,
                'organization_id' => 1,
                'name' => 'Example Key',
                'tier' => 'standard',
                'permissions' => ['read', 'write'],
                'is_active' => true,
                'created_at' => now(),
                'expires_at' => null,
                'last_used_at' => now(),
            ];

            // Update last used timestamp
            $this->updateKeyUsage($apiKey);

            return $keyData;
        } catch (\Throwable $e) {
            $this->logFailedAuthentication('Error looking up API key', $apiKey, $e);
            throw new AuthenticationException('Invalid API key');
        }
    }

    /**
     * Update API key usage timestamp.
     *
     * @param string $apiKey
     * @return void
     */
    protected function updateKeyUsage(string $apiKey): void
    {
        try {
            // Implementation depends on your storage mechanism
            // This might update a database record, for example
        } catch (\Throwable $e) {
            // Log error but don't fail request
            Log::error('Failed to update API key usage', [
                'key_hash' => hash('sha256', $apiKey),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log failed authentication attempt.
     *
     * @param string $reason
     * @param string $apiKey
     * @param \Throwable|null $exception
     * @return void
     */
    protected function logFailedAuthentication(string $reason, string $apiKey, ?\Throwable $exception = null): void
    {
        $context = [
            'reason' => $reason,
            'key_hash' => hash('sha256', $apiKey),
            'ip' => request()->ip(),
        ];

        if ($exception) {
            $context['error'] = $exception->getMessage();
            $context['trace'] = $exception->getTraceAsString();
        }

        Log::warning('API authentication failed', $context);
    }
}
