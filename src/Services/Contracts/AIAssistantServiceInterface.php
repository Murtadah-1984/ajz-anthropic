<?php

namespace Ajz\Anthropic\Services\Contracts;

use Ajz\Anthropic\Models\AIAssistant;
use Illuminate\Support\Collection;

interface AIAssistantServiceInterface
{
    /**
     * Create a new AI assistant.
     *
     * @param array $data
     * @return AIAssistant
     */
    public function create(array $data): AIAssistant;

    /**
     * Update an existing AI assistant.
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
     * Find an AI assistant by ID.
     *
     * @param int $id
     * @return AIAssistant|null
     */
    public function find(int $id): ?AIAssistant;

    /**
     * Get all AI assistants.
     *
     * @param array $filters
     * @return Collection
     */
    public function all(array $filters = []): Collection;

    /**
     * Train an AI assistant.
     *
     * @param int $id
     * @param array $trainingData
     * @return bool
     */
    public function train(int $id, array $trainingData): bool;

    /**
     * Get an AI assistant's training history.
     *
     * @param int $id
     * @return Collection
     */
    public function getTrainingHistory(int $id): Collection;

    /**
     * Get an AI assistant's performance metrics.
     *
     * @param int $id
     * @return array
     */
    public function getPerformanceMetrics(int $id): array;

    /**
     * Clone an existing AI assistant.
     *
     * @param int $id
     * @param array $overrides
     * @return AIAssistant
     */
    public function clone(int $id, array $overrides = []): AIAssistant;

    /**
     * Export an AI assistant's configuration.
     *
     * @param int $id
     * @return array
     */
    public function export(int $id): array;

    /**
     * Import an AI assistant configuration.
     *
     * @param array $config
     * @return AIAssistant
     */
    public function import(array $config): AIAssistant;

    /**
     * Validate an AI assistant's configuration.
     *
     * @param array $config
     * @return bool
     */
    public function validateConfig(array $config): bool;
}
