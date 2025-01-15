<?php

namespace Ajz\Anthropic\Services\Contracts;

use Ajz\Anthropic\Models\Session;
use Illuminate\Support\Collection;

interface SessionServiceInterface
{
    /**
     * Create a new session.
     *
     * @param array $data
     * @return Session
     */
    public function create(array $data): Session;

    /**
     * Start a session with initial context.
     *
     * @param int $id
     * @param array $context
     * @return Session
     */
    public function start(int $id, array $context = []): Session;

    /**
     * End a session and save its state.
     *
     * @param int $id
     * @param array $summary
     * @return bool
     */
    public function end(int $id, array $summary = []): bool;

    /**
     * Pause a session, preserving its state.
     *
     * @param int $id
     * @return bool
     */
    public function pause(int $id): bool;

    /**
     * Resume a paused session.
     *
     * @param int $id
     * @return Session
     */
    public function resume(int $id): Session;

    /**
     * Add a message to the session.
     *
     * @param int $id
     * @param array $message
     * @return bool
     */
    public function addMessage(int $id, array $message): bool;

    /**
     * Get all messages in a session.
     *
     * @param int $id
     * @return Collection
     */
    public function getMessages(int $id): Collection;

    /**
     * Get session state.
     *
     * @param int $id
     * @return array
     */
    public function getState(int $id): array;

    /**
     * Update session state.
     *
     * @param int $id
     * @param array $state
     * @return bool
     */
    public function updateState(int $id, array $state): bool;

    /**
     * Get session artifacts.
     *
     * @param int $id
     * @return Collection
     */
    public function getArtifacts(int $id): Collection;

    /**
     * Add an artifact to the session.
     *
     * @param int $id
     * @param array $artifact
     * @return bool
     */
    public function addArtifact(int $id, array $artifact): bool;

    /**
     * Get session metrics.
     *
     * @param int $id
     * @return array
     */
    public function getMetrics(int $id): array;

    /**
     * Export session data.
     *
     * @param int $id
     * @param array $options
     * @return array
     */
    public function export(int $id, array $options = []): array;

    /**
     * Import session data.
     *
     * @param array $data
     * @return Session
     */
    public function import(array $data): Session;

    /**
     * Get active sessions.
     *
     * @param array $filters
     * @return Collection
     */
    public function getActiveSessions(array $filters = []): Collection;

    /**
     * Clean up expired sessions.
     *
     * @param int $threshold Days
     * @return int Number of sessions cleaned
     */
    public function cleanup(int $threshold = 30): int;
}
