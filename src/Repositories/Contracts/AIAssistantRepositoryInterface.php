<?php

namespace Ajz\Anthropic\Repositories\Contracts;

use Ajz\Anthropic\Models\AIAssistant;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AIAssistantRepositoryInterface
{
    /**
     * Find an AI assistant by ID.
     *
     * @param int $id
     * @return AIAssistant|null
     */
    public function find(int $id): ?AIAssistant;

    /**
     * Get all AI assistants.
     *
     * @param array $columns
     * @return Collection
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Get paginated AI assistants.
     *
     * @param int $perPage
     * @param array $columns
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /**
     * Create a new AI assistant.
     *
     * @param array $data
     * @return AIAssistant
     */
    public function create(array $data): AIAssistant;

    /**
     * Update an AI assistant.
     *
     * @param int $id
     * @param array $data
     * @return AIAssistant
     */
    public function update(int $id, array $data): AIAssistant;

    /**
     * Delete an AI assistant.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Find AI assistants by type.
     *
     * @param string $type
     * @return Collection
     */
    public function findByType(string $type): Collection;

    /**
     * Find AI assistants by capability.
     *
     * @param string $capability
     * @return Collection
     */
    public function findByCapability(string $capability): Collection;

    /**
     * Get AI assistants with specific training status.
     *
     * @param string $status
     * @return Collection
     */
    public function findByTrainingStatus(string $status): Collection;

    /**
     * Get AI assistant's training history.
     *
     * @param int $id
     * @return Collection
     */
    public function getTrainingHistory(int $id): Collection;

    /**
     * Add training record to AI assistant.
     *
     * @param int $id
     * @param array $trainingData
     * @return bool
     */
    public function addTrainingRecord(int $id, array $trainingData): bool;

    /**
     * Get AI assistant's performance metrics.
     *
     * @param int $id
     * @param string $timeframe
     * @return array
     */
    public function getPerformanceMetrics(int $id, string $timeframe = 'daily'): array;

    /**
     * Update AI assistant's configuration.
     *
     * @param int $id
     * @param array $config
     * @return bool
     */
    public function updateConfiguration(int $id, array $config): bool;

    /**
     * Get AI assistant's configuration.
     *
     * @param int $id
     * @return array
     */
    public function getConfiguration(int $id): array;

    /**
     * Find AI assistants by criteria.
     *
     * @param array $criteria
     * @return Collection
     */
    public function findByCriteria(array $criteria): Collection;

    /**
     * Get recently active AI assistants.
     *
     * @param int $limit
     * @return Collection
     */
    public function getRecentlyActive(int $limit = 10): Collection;

    /**
     * Get AI assistants requiring maintenance.
     *
     * @return Collection
     */
    public function getNeedingMaintenance(): Collection;

    /**
     * Attach a model to an AI assistant.
     *
     * @param int $assistantId
     * @param string $modelType
     * @param int $modelId
     * @param array $attributes
     * @return bool
     */
    public function attachModel(int $assistantId, string $modelType, int $modelId, array $attributes = []): bool;

    /**
     * Detach a model from an AI assistant.
     *
     * @param int $assistantId
     * @param string $modelType
     * @param int $modelId
     * @return bool
     */
    public function detachModel(int $assistantId, string $modelType, int $modelId): bool;
}
