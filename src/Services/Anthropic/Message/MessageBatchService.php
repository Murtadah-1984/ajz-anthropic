<?php

declare(strict_types=1);

namespace App\Services\Message;

final class MessageBatchService extends BaseAnthropicService
{
    /**
     * Create a new message batch
     *
     * @param BatchRequest[] $requests
     * @return MessageBatch
     * @throws AnthropicException
     */
    public function createBatch(array $requests): MessageBatch
    {
        try {
            $response = $this->getHttpClient()
                ->post("{$this->baseUrl}/messages/batches", [
                    'requests' => array_map(fn($req) => [
                        'custom_id' => $req->custom_id,
                        'params' => $req->params
                    ], $requests)
                ]);

            return new MessageBatch($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * Get a message batch by ID
     *
     * @param string $batchId
     * @return MessageBatch
     * @throws AnthropicException
     */
    public function getBatch(string $batchId): MessageBatch
    {
        try {
            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/messages/batches/{$batchId}");

            return new MessageBatch($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * List message batches with pagination
     *
     * @param array $options (before_id, after_id, limit)
     * @return BatchList
     * @throws AnthropicException
     */
    public function listBatches(array $options = []): BatchList
    {
        try {
            $queryParams = array_filter([
                'before_id' => $options['before_id'] ?? null,
                'after_id' => $options['after_id'] ?? null,
                'limit' => $options['limit'] ?? null,
            ]);

            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/messages/batches", ['query' => $queryParams]);

            return new BatchList($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * Get batch results as a generator
     *
     * @param string $batchId
     * @return Generator|BatchResult[]
     * @throws AnthropicException
     */
    public function getBatchResults(string $batchId): Generator
    {
        try {
            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/messages/batches/{$batchId}/results");

            $lines = explode("\n", $response->body());
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $data = json_decode($line, true);
                if ($data) {
                    yield new BatchResult($data);
                }
            }
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * Cancel a message batch
     *
     * @param string $batchId
     * @return MessageBatch
     * @throws AnthropicException
     */
    public function cancelBatch(string $batchId): MessageBatch
    {
        try {
            $response = $this->getHttpClient()
                ->post("{$this->baseUrl}/messages/batches/{$batchId}/cancel");

            return new MessageBatch($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * Delete a message batch
     *
     * @param string $batchId
     * @return array
     * @throws AnthropicException
     */
    public function deleteBatch(string $batchId): array
    {
        try {
            $response = $this->getHttpClient()
                ->delete("{$this->baseUrl}/messages/batches/{$batchId}");

            return $this->handleResponse($response);
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * Wait for batch completion with polling
     *
     * @param string $batchId
     * @param int $pollInterval Polling interval in seconds
     * @return MessageBatch
     * @throws AnthropicException
     */
    public function waitForCompletion(string $batchId, int $pollInterval = 60): MessageBatch
    {
        while (true) {
            $batch = $this->getBatch($batchId);
            if ($batch->processing_status === 'ended') {
                return $batch;
            }
            sleep($pollInterval);
        }
    }
}
