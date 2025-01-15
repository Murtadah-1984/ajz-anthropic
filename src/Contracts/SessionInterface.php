<?php

namespace Ajz\Anthropic\Contracts;

use Ajz\Anthropic\Models\Message;
use Ajz\Anthropic\Models\Agent;
use Ajz\Anthropic\Models\User;
use Illuminate\Support\Collection;

interface SessionInterface
{
    /**
     * Get the session's unique identifier.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Get the session's type.
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Get the session's status.
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Get the session's context.
     *
     * @return array
     */
    public function getContext(): array;

    /**
     * Update the session's context.
     *
     * @param array $context
     * @return void
     */
    public function updateContext(array $context): void;

    /**
     * Get the session's state.
     *
     * @return array
     */
    public function getState(): array;

    /**
     * Update the session's state.
     *
     * @param array $state
     * @return void
     */
    public function updateState(array $state): void;

    /**
     * Start the session.
     *
     * @return bool
     */
    public function start(): bool;

    /**
     * End the session.
     *
     * @return bool
     */
    public function end(): bool;

    /**
     * Pause the session.
     *
     * @return bool
     */
    public function pause(): bool;

    /**
     * Resume the session.
     *
     * @return bool
     */
    public function resume(): bool;

    /**
     * Add a participant to the session.
     *
     * @param Agent|User $participant
     * @param string $role
     * @return bool
     */
    public function addParticipant(Agent|User $participant, string $role): bool;

    /**
     * Remove a participant from the session.
     *
     * @param Agent|User $participant
     * @return bool
     */
    public function removeParticipant(Agent|User $participant): bool;

    /**
     * Get all participants in the session.
     *
     * @return Collection
     */
    public function getParticipants(): Collection;

    /**
     * Get participants with a specific role.
     *
     * @param string $role
     * @return Collection
     */
    public function getParticipantsByRole(string $role): Collection;

    /**
     * Add a message to the session.
     *
     * @param Message $message
     * @return bool
     */
    public function addMessage(Message $message): bool;

    /**
     * Get all messages in the session.
     *
     * @return Collection
     */
    public function getMessages(): Collection;

    /**
     * Get messages from a specific participant.
     *
     * @param Agent|User $participant
     * @return Collection
     */
    public function getMessagesFromParticipant(Agent|User $participant): Collection;

    /**
     * Get the session's metadata.
     *
     * @return array
     */
    public function getMetadata(): array;

    /**
     * Update the session's metadata.
     *
     * @param array $metadata
     * @return void
     */
    public function updateMetadata(array $metadata): void;

    /**
     * Get the session's configuration schema.
     *
     * @return array
     */
    public static function getConfigurationSchema(): array;

    /**
     * Validate session configuration.
     *
     * @param array $config
     * @return bool
     */
    public function validateConfiguration(array $config): bool;

    /**
     * Get the session's validation rules.
     *
     * @return array
     */
    public function getValidationRules(): array;

    /**
     * Handle an error that occurred during the session.
     *
     * @param \Throwable $error
     * @return void
     */
    public function handleError(\Throwable $error): void;

    /**
     * Check if the session is active.
     *
     * @return bool
     */
    public function isActive(): bool;

    /**
     * Check if the session is paused.
     *
     * @return bool
     */
    public function isPaused(): bool;

    /**
     * Check if the session has ended.
     *
     * @return bool
     */
    public function hasEnded(): bool;

    /**
     * Get the session's start time.
     *
     * @return \DateTimeInterface|null
     */
    public function getStartTime(): ?\DateTimeInterface;

    /**
     * Get the session's end time.
     *
     * @return \DateTimeInterface|null
     */
    public function getEndTime(): ?\DateTimeInterface;

    /**
     * Get the session's duration in seconds.
     *
     * @return int|null
     */
    public function getDuration(): ?int;

    /**
     * Get the session's performance metrics.
     *
     * @return array
     */
    public function getPerformanceMetrics(): array;

    /**
     * Export the session data.
     *
     * @param array $options
     * @return array
     */
    public function export(array $options = []): array;

    /**
     * Import session data.
     *
     * @param array $data
     * @return bool
     */
    public function import(array $data): bool;

    /**
     * Create a snapshot of the session's current state.
     *
     * @return array
     */
    public function createSnapshot(): array;

    /**
     * Restore the session from a snapshot.
     *
     * @param array $snapshot
     * @return bool
     */
    public function restoreSnapshot(array $snapshot): bool;

    /**
     * Get the session's event history.
     *
     * @return Collection
     */
    public function getEventHistory(): Collection;

    /**
     * Add an event to the session's history.
     *
     * @param string $type
     * @param array $data
     * @return void
     */
    public function addEvent(string $type, array $data): void;
}
