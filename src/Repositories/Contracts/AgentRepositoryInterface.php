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
     * Get active agents.
     *
     * @return Collection
     */
    public function getActiveAgents(): Collection;

    /**
     * Get agents by status.
     *
     * @param string $status
     * @return Collection
     */
    public function findByStatus(string $status): Collection;

    /**
     * Get agent's current task.
     *
     * @param int $agentId
     * @return array|null
     */
    public function getCurrentTask(int $agentId): ?array;

    /**
     * Assign task to agent.
     *
     * @param int $agentId
     * @param array $task
     * @return bool
     */
