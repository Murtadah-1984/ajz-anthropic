<?php

namespace Ajz\Anthropic\Repositories\Contracts;

use Ajz\Anthropic\Models\Agent;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AgentRepositoryInterface
{
    /**
     * Find an agent by ID.
     *
     * @param int $id
     * @return Agent|null
     */
    public function find(int $id): ?Agent;

    /**
     * Get all agents.
     *
     * @param array $columns
     * @return Collection
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Get paginated agents.
     *
     * @param int $perPage
     * @param array $columns
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /**
     * Create a new agent.
     *
     * @param array $data
     * @return Agent
     */
    public function create(array $data): Agent;

    /**
     * Update an agent.
     *
     * @param int $id
     * @param array $data
     * @return Agent
     */
    public function update(int $id, array $data): Agent;

    /**
     * Delete an agent.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Find agents by type.
     *
     * @param string $type
     * @return Collection
     */
    public function findByType(string $type): Collection;

    /**
     * Find agents by capability.
     *
     * @param string $capability
     * @return Collection
     */
    public function findByCapability(string $capability): Collection;

    /**
     * Get agent's current task.
     *
     * @param int $id
     * @return array|null
     */
    public function getCurrentTask(int $id): ?array;

    /**
     * Assign task to agent.
     *
     * @param int $id
     * @param array $task
     * @return bool
     */
    public function assignTask(int $id, array $task): bool;

    /**
     * Get agent's task history.
     *
     * @param int $id
     * @return Collection
     */
    public function getTaskHistory(int $id): Collection;

    /**
     * Update agent's capabilities.
     *
     * @param int $id
     * @param array $capabilities
     * @return bool
     */
    public function updateCapabilities(int $id, array $capabilities): bool;

    /**
     * Get agent's capabilities.
     *
     * @param int $id
     * @return array
     */
    public function getCapabilities(int $id): array;

    /**
     * Get agent's performance metrics.
     *
     * @param int $id
     * @param string $timeframe
     * @return array
     */
    public function getPerformanceMetrics(int $id, string $timeframe = 'daily'): array;

    /**
     * Get agent's active sessions.
     *
     * @param int $id
     * @return Collection
     */
    public function getActiveSessions(int $id): Collection;

    /**
     * Connect agent to session.
     *
     * @param int $agentId
     * @param int $sessionId
     * @return bool
     */
    public function connectToSession(int $agentId, int $sessionId): bool;

    /**
     * Disconnect agent from session.
     *
     * @param int $agentId
     * @param int $sessionId
     * @return bool
     */
    public function disconnectFromSession(int $agentId, int $sessionId): bool;

    /**
     * Get available agent types.
     *
     * @return array
     */
    public function getAvailableTypes(): array;

    /**
     * Register new agent type.
     *
     * @param string $type
     * @param array $configuration
     * @return bool
     */
    public function registerType(string $type, array $configuration): bool;

    /**
     * Get agents by status.
     *
     * @param string $status
     * @return Collection
     */
    public function findByStatus(string $status): Collection;

    /**
     * Update agent's state.
     *
     * @param int $id
     * @param array $state
     * @return bool
     */
    public function updateState(int $id, array $state): bool;

    /**
     * Get agent's state.
     *
     * @param int $id
     * @return array
     */
    public function getState(int $id): array;
}
