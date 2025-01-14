<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

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
    protected function getHttpClient()
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
    protected function getProxyConfiguration()
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
        Log::error('Anthropic API Error', $error);
        throw new \Exception($error['error']['message'] ?? 'Unknown error occurred');
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

class AnthropicClaudeApiService extends BaseAnthropicService
{
    public function __construct()
    {
        parent::__construct();
        $this->apiKey = Config::get('anthropic.api_key');
    }

    /**
     * Create a message using Claude
     *
     * @param string $model
     * @param array $messages
     * @param int $maxTokens
     * @return array
     * @throws \Exception
     */
    public function createMessage(
        string $model = 'claude-3-5-sonnet-20241022',
        array $messages = [],
        int $maxTokens = 1024
    ): array {
        try {
            $response = $this->getHttpClient()
                ->post("{$this->baseUrl}/messages", [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'messages' => $messages,
                ]);

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('Anthropic API Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * List all available models
     *
     * @return array
     * @throws \Exception
     */
    public function listModels(): array
    {
        try {
            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/models");

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('Anthropic API Error: ' . $e->getMessage());
            throw $e;
        }
    }
}

class AnthropicAdminApiService extends BaseAnthropicService
{
    public function __construct()
    {
        parent::__construct();
        $this->apiKey = Config::get('anthropic.admin_api_key');
    }

    /**
     * Get account information
     *
     * @return array
     * @throws \Exception
     */
    public function getAccountInfo(): array
    {
        try {
            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/admin/account");

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('Anthropic Admin API Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get usage statistics
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     * @throws \Exception
     */
    public function getUsageStats(string $startDate, string $endDate): array
    {
        try {
            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/admin/usage", [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]);

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('Anthropic Admin API Error: ' . $e->getMessage());
            throw $e;
        }
    }
}