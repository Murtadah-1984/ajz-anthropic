<?php

declare(strict_types=1);

/**
 * @OA\Schema(
 *     schema="AnthropicClaudeApiService",
 *     title="Anthropic Claude API Service",
 *     description="Service for interacting with Claude AI models"
 * )
 */

namespace Ajz\Anthropic\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

final class AnthropicClaudeApiService extends BaseAnthropicService
{
    public function __construct()
    {
        parent::__construct();
        $this->apiKey = Config::get('anthropic.api_key');
    }

    /**
     * Create a message using Claude
     *
     * @OA\Post(
     *     path="/messages",
     *     summary="Create a message using Claude",
     *     @OA\Parameter(
     *         name="model",
     *         in="query",
     *         description="Claude model to use",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="messages",
     *         in="query",
     *         description="Messages to send",
     *         required=true,
     *         @OA\Schema(type="array", @OA\Items(type="object"))
     *     ),
     *     @OA\Parameter(
     *         name="maxTokens",
     *         in="query",
     *         description="Maximum tokens to generate",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     *
     * @param string $model
     * @param array $messages
     * @param int $maxTokens
     * @return array
     * @throws \Exception
     */
    public function createMessage(
        string $model = 'claude-3-sonnet-20240229',
        array $messages = [],
        int $maxTokens = 1024,
        ?array $options = null
    ): array {
        try {
            $payload = array_filter([
                'model' => $model,
                'max_tokens' => $maxTokens,
                'messages' => $messages,
                'temperature' => $options['temperature'] ?? null,
                'top_p' => $options['top_p'] ?? null,
                'top_k' => $options['top_k'] ?? null,
                'stream' => $options['stream'] ?? false,
                'system' => $options['system'] ?? null,
                'metadata' => $options['metadata'] ?? null,
            ], function ($value) {
                return !is_null($value);
            });

            $response = $this->getHttpClient()
                ->post("{$this->baseUrl}/messages", $payload);

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            if ($e instanceof \Ajz\Anthropic\Exceptions\AnthropicException) {
                throw $e;
            }
            Log::error('Anthropic API Error: ' . $e->getMessage(), [
                'model' => $model,
                'exception' => get_class($e)
            ]);
            throw new \Ajz\Anthropic\Exceptions\ApiException(
                'Failed to create message: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * List all available models
     *
     * @OA\Get(
     *     path="/models",
     *     summary="List all available Claude models",
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(type="object")
     *         )
     *     )
     * )
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
            if ($e instanceof \Ajz\Anthropic\Exceptions\AnthropicException) {
                throw $e;
            }
            Log::error('Anthropic API Error: ' . $e->getMessage(), [
                'exception' => get_class($e)
            ]);
            throw new \Ajz\Anthropic\Exceptions\ApiException(
                'Failed to list models: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get model details
     *
     * @param string $modelId
     * @return array
     * @throws \Exception
     */
    public function getModel(string $modelId): array
    {
        try {
            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/models/{$modelId}");

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            if ($e instanceof \Ajz\Anthropic\Exceptions\AnthropicException) {
                throw $e;
            }
            Log::error('Anthropic API Error: ' . $e->getMessage(), [
                'model_id' => $modelId,
                'exception' => get_class($e)
            ]);
            throw new \Ajz\Anthropic\Exceptions\ApiException(
                'Failed to get model details: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
