<?php

namespace Ajz\Anthropic\Services\Contracts;

use Ajz\Anthropic\Models\Agent;
use Illuminate\Support\Collection;

interface AgentServiceInterface
{
    /**
     * Create a new agent.
     *
     * @param array $data
     * @return Agent
     */
    public function create(array $data): Agent;

    /**
     * Initialize an agent with specific capabilities.
     *
     * @param int $id
     * @param array $capabilities
     * @return Agent
     */
    public function initialize(int $id, array $capabilities = []): Agent;

    /**
     * Assign an agent to a task.
     *
     * @param int $id
     * @param array $task
     * @return bool
     */
    public function assignTask(int $id, array $task): bool;

    /**
     * Get agent's current task.
     *
     * @param int $id
     * @return array|null
     */
    public function getCurrentTask(int $id): ?array;

    /**
     * Update agent's capabilities.
     *
     * @param int $id
     * @param array $capabilities
     * @return Agent
     */
    public function updateCapabilities(int $id, array $capabilities): Agent;

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
     * @return array
     */
    public function getPerformanceMetrics(int $id): array;

    /**
     * Get agent's task history.
     *
     * @param int $id
     * @return Collection
     */
    public function getTaskHistory(int $id): Collection;

    /**
     * Connect agent to a session.
     *
     * @param int $agentId
     * @param int $sessionId
     * @return bool
     */
    public function connectToSession(int $agentId, int $sessionId): bool;

    /**
     * Disconnect agent from a session.
     *
     * @param int $agentId
     * @param int $sessionId
     * @return bool
     */
    public function disconnectFromSession(int $agentId, int $sessionId): bool;

    /**
     * Get agent's active sessions.
     *
     * @param int $id
     * @return Collection
     */
    public function getActiveSessions(int $id): Collection;

    /**
     * Update agent's state.
     *
     * @param int $id
     * @param array $state
     * @return bool
     */
    public function updateState(int $id, array $state): bool;

    /**
     * Get agent's current state.
     *
     * @param int $id
     * @return array
     */
    public function getState(int $id): array;

    /**
     * Pause agent's activities.
     *
     * @param int $id
     * @return bool
     */
    public function pause(int $id): bool;

    /**
     * Resume agent's activities.
     *
     * @param int $id
     * @return bool
     */
    public function resume(int $id): bool;

    /**
     * Get available agent types.
     *
     * @return array
     */
    public function getAvailableTypes(): array;

    /**
     * Register a new agent type.
     *
     * @param string $type
     * @param array $configuration
     * @return bool
     */
    public function registerType(string $type, array $configuration): bool;

    /**
     * Get agents by capability.
     *
     * @param string $capability
     * @return Collection
     */
    public function getByCapability(string $capability): Collection;

    /**
     * Export agent configuration.
     *
     * @param int $id
     * @return array
     */
    public function export(int $id): array;

    /**
     * Import agent configuration.
     *
     * @param array $config
     * @return Agent
     */
    public function import(array $config): Agent;
}
