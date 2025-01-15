<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Anthropic\Orgnization;


class ApiKeyService extends BaseAnthropicService
{
    /**
     * Get an API key
     *
     * @param string $apiKeyId
     * @return ApiKey
     * @throws AnthropicException
     */
    public function getApiKey(string $apiKeyId): ApiKey
    {
        try {
            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/organizations/api_keys/{$apiKeyId}");

            return new ApiKey($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * List API keys with filtering and pagination
     *
     * @param array $options (status, workspace_id, created_by_user_id, before_id, after_id, limit)
     * @return ApiKeyList
     * @throws AnthropicException
     */
    public function listApiKeys(array $options = []): ApiKeyList
    {
        if (isset($options['status']) && !in_array($options['status'], ApiKey::VALID_STATUSES)) {
            throw new \InvalidArgumentException(
                "Invalid status. Must be one of: " . implode(', ', ApiKey::VALID_STATUSES)
            );
        }

        try {
            $queryParams = array_filter([
                'status' => $options['status'] ?? null,
                'workspace_id' => $options['workspace_id'] ?? null,
                'created_by_user_id' => $options['created_by_user_id'] ?? null,
                'before_id' => $options['before_id'] ?? null,
                'after_id' => $options['after_id'] ?? null,
                'limit' => $options['limit'] ?? null,
            ]);

            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/organizations/api_keys", ['query' => $queryParams]);

            return new ApiKeyList($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * Update an API key
     *
     * @param string $apiKeyId
     * @param array $data (name, status)
     * @return ApiKey
     * @throws AnthropicException
     */
    public function updateApiKey(string $apiKeyId, array $data): ApiKey
    {
        if (isset($data['status']) && !in_array($data['status'], ApiKey::VALID_STATUSES)) {
            throw new \InvalidArgumentException(
                "Invalid status. Must be one of: " . implode(', ', ApiKey::VALID_STATUSES)
            );
        }

        if (isset($data['name']) && (strlen($data['name']) < 1 || strlen($data['name']) > 500)) {
            throw new \InvalidArgumentException('Name must be between 1 and 500 characters');
        }

        try {
            $response = $this->getHttpClient()
                ->post("{$this->baseUrl}/organizations/api_keys/{$apiKeyId}", $data);

            return new ApiKey($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * Get all API keys (handles pagination automatically)
     *
     * @param array $options (status, workspace_id, created_by_user_id)
     * @return ApiKey[]
     * @throws AnthropicException
     */
    public function getAllApiKeys(array $options = []): array
    {
        $keys = [];
        $lastId = null;

        do {
            $queryOptions = array_merge($options, [
                'after_id' => $lastId
            ]);

            $response = $this->listApiKeys($queryOptions);
            $keys = array_merge($keys, $response->data);
            $lastId = $response->last_id;
        } while ($response->has_more && $lastId);

        return $keys;
    }

    /**
     * Find API keys by name
     *
     * @param string $name
     * @param array $options Additional filters
     * @return ApiKey[]
     * @throws AnthropicException
     */
    public function findApiKeysByName(string $name, array $options = []): array
    {
        $allKeys = $this->getAllApiKeys($options);
        return array_filter(
            $allKeys,
            fn($key) => stripos($key->name, $name) !== false
        );
    }

    /**
     * Get active API keys for a workspace
     *
     * @param string $workspaceId
     * @return ApiKey[]
     * @throws AnthropicException
     */
    public function getActiveWorkspaceKeys(string $workspaceId): array
    {
        return $this->getAllApiKeys([
            'workspace_id' => $workspaceId,
            'status' => ApiKey::STATUS_ACTIVE
        ]);
    }
}
