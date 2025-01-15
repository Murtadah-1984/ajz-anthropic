<?php

namespace Ajz\Anthropic\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Ajz\Anthropic\Exceptions\AuthenticationException;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateToken
{
    /**
     * Cache key prefix for tokens.
     *
     * @var string
     */
    protected const CACHE_PREFIX = 'anthropic_token:';

    /**
     * Cache duration in minutes.
     *
     * @var int
     */
    protected const CACHE_DURATION = 15;

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string|null $scope
     * @return Response
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $token = $this->getToken($request);

        if (!$token) {
            throw new AuthenticationException('Access token is missing');
        }

        // Validate token
        $tokenData = $this->validateToken($token);

        // Check scope if required
        if ($scope && !$this->hasScope($tokenData, $scope)) {
            throw new AuthenticationException('Insufficient scope');
        }

        // Add token data to request for downstream use
        $request->attributes->set('token_data', $tokenData);

        // Add user data if available
        if (isset($tokenData['user_id'])) {
            $request->attributes->set('user_id', $tokenData['user_id']);
        }

        return $next($request);
    }

    /**
     * Get token from request.
     *
     * @param Request $request
     * @return string|null
     */
    protected function getToken(Request $request): ?string
    {
        // Check Authorization header first
        $token = $request->bearerToken();
        if ($token && str_starts_with($token, 'tk_')) {
            return $token;
        }

        // Check custom header
        $token = $request->header('X-Access-Token');
        if ($token) {
            return $token;
        }

        // Check query parameter
        return $request->query('access_token');
    }

    /**
     * Validate token and return token data.
     *
     * @param string $token
     * @return array
     * @throws AuthenticationException
     */
    protected function validateToken(string $token): array
    {
        // Check cache first
        $cacheKey = self::CACHE_PREFIX . hash('sha256', $token);
        $cachedData = Cache::get($cacheKey);

        if ($cachedData !== null) {
            return $this->validateCachedTokenData($cachedData, $token);
        }

        // Validate against database
        $tokenData = $this->lookupToken($token);

        // Cache valid token data
        Cache::put($cacheKey, $tokenData, self::CACHE_DURATION);

        return $tokenData;
    }

    /**
     * Validate cached token data.
     *
     * @param array $data
     * @param string $token
     * @return array
     * @throws AuthenticationException
     */
    protected function validateCachedTokenData(array $data, string $token): array
    {
        // Check if token is revoked
        if (!empty($data['revoked'])) {
            $this->logFailedAuthentication('Token has been revoked', $token);
            throw new AuthenticationException('Invalid token');
        }

        // Check if token has expired
        if (!empty($data['expires_at']) && now()->isAfter($data['expires_at'])) {
            $this->logFailedAuthentication('Token has expired', $token);
            throw new AuthenticationException('Invalid token');
        }

        // Check if token is active
        if (empty($data['is_active'])) {
            $this->logFailedAuthentication('Token is inactive', $token);
            throw new AuthenticationException('Invalid token');
        }

        return $data;
    }

    /**
     * Look up token in database.
     *
     * @param string $token
     * @return array
     * @throws AuthenticationException
     */
    protected function lookupToken(string $token): array
    {
        try {
            // Implementation depends on your token storage
            // This is just an example structure
            $tokenData = [
                'token' => $token,
                'user_id' => 1,
                'client_id' => 'example_client',
                'scopes' => ['read', 'write'],
                'is_active' => true,
                'created_at' => now(),
                'expires_at' => now()->addDay(),
                'last_used_at' => now(),
            ];

            // Update last used timestamp
            $this->updateTokenUsage($token);

            return $tokenData;
        } catch (\Throwable $e) {
            $this->logFailedAuthentication('Error looking up token', $token, $e);
            throw new AuthenticationException('Invalid token');
        }
    }

    /**
     * Check if token has required scope.
     *
     * @param array $tokenData
     * @param string $requiredScope
     * @return bool
     */
    protected function hasScope(array $tokenData, string $requiredScope): bool
    {
        $scopes = $tokenData['scopes'] ?? [];

        // Check for wildcard scope
        if (in_array('*', $scopes)) {
            return true;
        }

        // Split scopes into parts (e.g., 'users:read' -> ['users', 'read'])
        $requiredParts = explode(':', $requiredScope);

        foreach ($scopes as $scope) {
            $scopeParts = explode(':', $scope);

            // Check if scope matches exactly or has wildcard
            if ($scope === $requiredScope || $scope === $requiredParts[0] . ':*') {
                return true;
            }

            // Check if all parts match
            if (count($scopeParts) === count($requiredParts)) {
                $matches = true;
                foreach ($scopeParts as $i => $part) {
                    if ($part !== '*' && $part !== $requiredParts[$i]) {
                        $matches = false;
                        break;
                    }
                }
                if ($matches) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Update token usage timestamp.
     *
     * @param string $token
     * @return void
     */
    protected function updateTokenUsage(string $token): void
    {
        try {
            // Implementation depends on your storage mechanism
            // This might update a database record, for example
        } catch (\Throwable $e) {
            // Log error but don't fail request
            Log::error('Failed to update token usage', [
                'token_hash' => hash('sha256', $token),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log failed authentication attempt.
     *
     * @param string $reason
     * @param string $token
     * @param \Throwable|null $exception
     * @return void
     */
    protected function logFailedAuthentication(string $reason, string $token, ?\Throwable $exception = null): void
    {
        $context = [
            'reason' => $reason,
            'token_hash' => hash('sha256', $token),
            'ip' => request()->ip(),
        ];

        if ($exception) {
            $context['error'] = $exception->getMessage();
            $context['trace'] = $exception->getTraceAsString();
        }

        Log::warning('Token authentication failed', $context);
    }
}
