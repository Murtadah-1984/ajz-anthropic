<?php

namespace App\Services\Anthropic;

use DateTime;
use Generator;
use JsonStreamingParser\Listener\InMemoryListener;
use JsonStreamingParser\Parser;

class MessageBatch
{
    public string $id;
    public string $type = 'message_batch';
    public string $processing_status;
    public RequestCounts $request_counts;
    public ?DateTime $ended_at;
    public DateTime $created_at;
    public DateTime $expires_at;
    public ?DateTime $archived_at;
    public ?DateTime $cancel_initiated_at;
    public ?string $results_url;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->processing_status = $data['processing_status'];
        $this->request_counts = new RequestCounts($data['request_counts']);
        $this->ended_at = isset($data['ended_at']) ? new DateTime($data['ended_at']) : null;
        $this->created_at = new DateTime($data['created_at']);
        $this->expires_at = new DateTime($data['expires_at']);
        $this->archived_at = isset($data['archived_at']) ? new DateTime($data['archived_at']) : null;
        $this->cancel_initiated_at = isset($data['cancel_initiated_at']) ? new DateTime($data['cancel_initiated_at']) : null;
        $this->results_url = $data['results_url'] ?? null;
    }
}

class RequestCounts
{
    public int $processing;
    public int $succeeded;
    public int $errored;
    public int $canceled;
    public int $expired;

    public function __construct(array $data)
    {
        $this->processing = $data['processing'];
        $this->succeeded = $data['succeeded'];
        $this->errored = $data['errored'];
        $this->canceled = $data['canceled'];
        $this->expired = $data['expired'];
    }
}

class BatchRequest
{
    public string $custom_id;
    public array $params;

    public function __construct(string $custom_id, array $params)
    {
        $this->custom_id = $custom_id;
        $this->params = $params;
    }
}

class BatchList
{
    /** @var MessageBatch[] */
    public array $data;
    public bool $has_more;
    public ?string $first_id;
    public ?string $last_id;

    public function __construct(array $data)
    {
        $this->data = array_map(fn($batch) => new MessageBatch($batch), $data['data']);
        $this->has_more = $data['has_more'];
        $this->first_id = $data['first_id'];
        $this->last_id = $data['last_id'];
    }
}

class BatchResult
{
    public string $custom_id;
    public array $result;

    public function __construct(array $data)
    {
        $this->custom_id = $data['custom_id'];
        $this->result = $data['result'];
    }
}

class MessageBatchService extends BaseAnthropicService
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