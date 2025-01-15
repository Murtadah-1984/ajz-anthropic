<?php

declare(strict_types=1);

namespace App\Services\Anthropic;

use DateTime;

final class Model
{
    public string $type = 'model';
    public string $id;
    public string $display_name;
    public DateTime $created_at;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->display_name = $data['display_name'];
        $this->created_at = new DateTime($data['created_at']);
    }
}

class ModelList
{
    /** @var Model[] */
    public array $data;
    public bool $has_more;
    public ?string $first_id;
    public ?string $last_id;

    public function __construct(array $data)
    {
        $this->data = array_map(fn($model) => new Model($model), $data['data']);
        $this->has_more = $data['has_more'];
        $this->first_id = $data['first_id'];
        $this->last_id = $data['last_id'];
    }
}

class ModelService extends BaseAnthropicService
{
    /**
     * List available models with pagination support
     *
     * @param array $options Pagination options (before_id, after_id, limit)
     * @return ModelList
     * @throws AnthropicException
     */
    public function listModels(array $options = []): ModelList
    {
        try {
            $queryParams = array_filter([
                'before_id' => $options['before_id'] ?? null,
                'after_id' => $options['after_id'] ?? null,
                'limit' => $options['limit'] ?? null,
            ]);

            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/models", ['query' => $queryParams]);

            return new ModelList($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage());
        }
    }

    /**
     * Get a specific model by ID
     *
     * @param string $modelId
     * @return Model
     * @throws AnthropicException
     */
    public function getModel(string $modelId): Model
    {
        try {
            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/models/{$modelId}");

            return new Model($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage());
        }
    }

    /**
     * Get all available models (handles pagination automatically)
     *
     * @return Model[]
     * @throws AnthropicException
     */
    public function getAllModels(): array
    {
        $models = [];
        $lastId = null;

        do {
            $options = $lastId ? ['after_id' => $lastId] : [];
            $response = $this->listModels($options);

            $models = array_merge($models, $response->data);
            $lastId = $response->last_id;
        } while ($response->has_more && $lastId);

        return $models;
    }
}
