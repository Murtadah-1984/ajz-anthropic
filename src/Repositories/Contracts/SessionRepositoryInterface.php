<?php

namespace Ajz\Anthropic\Repositories\Contracts;

use Ajz\Anthropic\Models\Session;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SessionRepositoryInterface
{
    /**
     * Find a session by ID.
     *
     * @param int $id
     * @return Session|null
     */
    public function find(int $id): ?Session;

    /**
     * Get all sessions.
     *
     * @param array $columns
     * @return Collection
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Get paginated sessions.
     *
     * @param int $perPage
     * @param array $columns
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /**
     * Create a new session.
     *
     * @param array $data
     * @return Session
     */
    public function create(array $data): Session;

    /**
     * Update a session.
     *
     * @param int $id
     * @param array $data
     * @return Session
     */
    public function update(int $id, array $data): Session;

    /**
     * Delete a session.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Get active sessions.
     *
     * @return Collection
     */
    public function getActiveSessions(): Collection;

    /**
     * Get sessions by status.
     *
     * @param string $status
     * @return Collection
     */
    public function findByStatus(string $status): Collection;

    /**
     * Get sessions by agent.
     *
     * @param int $agentId
     * @return Collection
     */
    public function findByAgent(int $agentId): Collection;

    /**
     * Get sessions by user.
     *
     * @param int $userId
     * @return Collection
     */
    public function findByUser(int $userId): Collection;

    /**
     * Add message to session.
     *
     * @param int $sessionId
     * @param array $message
     * @return bool
     */
    public function addMessage(int $sessionId, array $message): bool;

    /**
     * Get session messages.
     *
     * @param int $sessionId
     * @return Collection
     */
    public function getMessages(int $sessionId): Collection;

    /**
     * Add artifact to session.
     *
     * @param int $sessionId
     * @param array $artifact
     * @return bool
     */
    public function addArtifact(int $sessionId, array $artifact): bool;

    /**
     * Get session artifacts.
     *
     * @param int $sessionId
     * @return Collection
     */
    public function getArtifacts(int $sessionId): Collection;

    /**
     * Update session state.
     *
     * @param int $sessionId
     * @param array $state
     * @return bool
     */
    public function updateState(int $sessionId, array $state): bool;

    /**
     * Get session state.
     *
     * @param int $sessionId
     * @return array
     */
    public function getState(int $sessionId): array;

    /**
     * Get sessions requiring cleanup.
     *
     * @param int $threshold Days
     * @return Collection
     */
    public function getExpiredSessions(int $threshold): Collection;

    /**
     * Get session metrics.
     *
     * @param int $sessionId
     * @return array
     */
    public function getMetrics(int $sessionId): array;

    /**
     * Get sessions by date range.
     *
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    public function findByDateRange(string $startDate, string $endDate): Collection;

    /**
     * Get sessions by type.
     *
     * @param string $type
     * @return Collection
     */
    public function findByType(string $type): Collection;

    /**
     * Get sessions with specific tags.
     *
     * @param array $tags
     * @return Collection
     */
    public function findByTags(array $tags): Collection;
}
