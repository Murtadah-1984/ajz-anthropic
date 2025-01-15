<?php

declare(strict_types=1);

/**
 * @OA\Schema(
 *     schema="BaseAnthropicService",
 *     title="Base Anthropic Service",
 *     description="Base service class for Anthropic API integration"
 * )
 */

namespace Ajz\Anthropic\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

/**
 * @OA\Schema(
 *     schema="BaseAnthropicService",
 *     title="Base Anthropic Service",
 *     description="Base service class for Anthropic API integration",
 *     @OA\Property(
 *         property="baseUrl",
 *         type="string",
 *         description="Base URL for Anthropic API"
 *     ),
 *     @OA\Property(
 *         property="apiKey",
 *         type="string",
 *         description="Anthropic API key"
 *     ),
 *     @OA\Property(
 *         property="anthropicVersion",
 *         type="string",
 *         description="Anthropic API version"
 *     ),
 *     @OA\Property(
 *         property="ipRanges",
 *         type="array",
 *         description="Allowed IP ranges for Anthropic API"
 *     )
 * )
 */
abstract class BaseAnthropicService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $anthropicVersion;
    protected array $ipRanges;

    public function __construct()
    {
        $this->baseUrl = Config::get('anthropic.base_url', 'https://api.anthropic.com/v1');
        $this->anthropicVersion = Config::get('anthropic.version', '2023-06-01');
        $this->ipRanges = Config::get('anthropic.ip_ranges', [
            'ipv4' => ['160.79.104.0/23'],
            'ipv6' => ['2607:6bc0::/48']
        ]);
    }

    /**
     * Get configured HTTP client
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function getHttpClient(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->anthropicVersion,
            'content-type' => 'application/json',
        ])->withOptions([
            'verify' => true,
            'connect_timeout' => 30,
            'timeout' => 120,
            'proxy' => $this->getProxyConfiguration(),
        ]);
    }

    /**
     * Get proxy configuration based on IP ranges
     *
     * @return array|null
     */
    protected function getProxyConfiguration(): ?array
    {
        // If you're using a proxy to restrict egress traffic,
        // configure it here based on the IP ranges
        return null;
    }

    /**
     * Handle API response
     *
     * @param Response $response
     * @return array
     * @throws \Exception
     */
    protected function handleResponse(Response $response): array
    {
        if ($response->successful()) {
            return $response->json();
        }

        $error = $response->json();
        $errorMessage = $error['error']['message'] ?? 'Unknown error occurred';
        $errorType = $error['error']['type'] ?? 'unknown_error';
        $errorCode = $response->status();

        Log::error('Anthropic API Error', [
            'error' => $error,
            'status' => $errorCode,
            'type' => $errorType
        ]);

        switch ($errorCode) {
            case 401:
                throw new \Ajz\Anthropic\Exceptions\AuthenticationException($errorMessage);
            case 403:
                throw new \Ajz\Anthropic\Exceptions\PermissionException($errorMessage);
            case 404:
                throw new \Ajz\Anthropic\Exceptions\NotFoundException($errorMessage);
            case 413:
                throw new \Ajz\Anthropic\Exceptions\RequestTooLargeException($errorMessage);
            case 429:
                throw new \Ajz\Anthropic\Exceptions\RateLimitException($errorMessage);
            case 500:
                throw new \Ajz\Anthropic\Exceptions\ApiException($errorMessage);
            case 503:
                throw new \Ajz\Anthropic\Exceptions\OverloadedException($errorMessage);
            default:
                throw new \Ajz\Anthropic\Exceptions\AnthropicException($errorMessage);
        }
    }

    /**
     * Validate IP address against Anthropic ranges
     *
     * @param string $ip
     * @return bool
     */
    public function isAnthropicIp(string $ip): bool
    {
        foreach ($this->ipRanges['ipv4'] as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        foreach ($this->ipRanges['ipv6'] as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP is in CIDR range
     *
     * @param string $ip
     * @param string $range
     * @return bool
     */
    protected function ipInRange(string $ip, string $range): bool
    {
        list($range, $netmask) = explode('/', $range, 2);
        $range_decimal = ip2long($range);
        $ip_decimal = ip2long($ip);
        $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
        $netmask_decimal = ~ $wildcard_decimal;

        return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
    }
}
