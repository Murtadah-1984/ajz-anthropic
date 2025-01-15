<?php

namespace Ajz\Anthropic\Contracts;

use Ajz\Anthropic\Models\Task;
use Ajz\Anthropic\Models\Session;
use Illuminate\Support\Collection;

interface AgentInterface
{
    /**
     * Get the agent's unique identifier.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Get the agent's type.
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Get the agent's name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the agent's description.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Get the agent's capabilities.
     *
     * @return array
     */
    public function getCapabilities(): array;

    /**
     * Check if the agent has a specific capability.
     *
     * @param string $capability
     * @return bool
     */
    public function hasCapability(string $capability): bool;

    /**
     * Initialize the agent with configuration.
     *
     * @param array $config
     * @return void
     */
    public function initialize(array $config): void;

    /**
     * Get the agent's current state.
     *
     * @return array
     */
    public function getState(): array;

    /**
     * Update the agent's state.
     *
     * @param array $state
     * @return void
     */
    public function setState(array $state): void;

    /**
     * Handle a task assigned to the agent.
     *
     * @param Task $task
     * @return mixed
     */
    public function handleTask(Task $task): mixed;

    /**
     * Join a session.
     *
     * @param Session $session
     * @param string $role
     * @return bool
     */
    public function joinSession(Session $session, string $role): bool;

    /**
     * Leave a session.
     *
     * @param Session $session
     * @return bool
     */
    public function leaveSession(Session $session): bool;

    /**
     * Handle a message in a session.
     *
     * @param Session $session
     * @param array $message
     * @return mixed
     */
    public function handleMessage(Session $session, array $message): mixed;

    /**
     * Get the agent's active sessions.
     *
     * @return Collection
     */
    public function getActiveSessions(): Collection;

    /**
     * Get the agent's current tasks.
     *
     * @return Collection
     */
    public function getCurrentTasks(): Collection;

    /**
     * Get the agent's task history.
     *
     * @return Collection
     */
    public function getTaskHistory(): Collection;

    /**
     * Get the agent's performance metrics.
     *
     * @param string $timeframe
     * @return array
     */
    public function getPerformanceMetrics(string $timeframe = 'daily'): array;

    /**
     * Train the agent with new data.
     *
     * @param array $trainingData
     * @return bool
     */
    public function train(array $trainingData): bool;

    /**
     * Get the agent's training history.
     *
     * @return Collection
     */
    public function getTrainingHistory(): Collection;

    /**
     * Validate input for the agent.
     *
     * @param array $input
     * @return bool
     */
    public function validateInput(array $input): bool;

    /**
     * Get the agent's input validation rules.
     *
     * @return array
     */
    public function getValidationRules(): array;

    /**
     * Handle an error that occurred during agent operation.
     *
     * @param \Throwable $error
     * @return void
     */
    public function handleError(\Throwable $error): void;

    /**
     * Pause the agent's activities.
     *
     * @return bool
     */
    public function pause(): bool;

    /**
     * Resume the agent's activities.
     *
     * @return bool
     */
    public function resume(): bool;

    /**
     * Check if the agent is available.
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Get the agent's configuration schema.
     *
     * @return array
     */
    public static function getConfigurationSchema(): array;

    /**
     * Get the agent's metadata.
     *
     * @return array
     */
    public function getMetadata(): array;

    /**
     * Update the agent's metadata.
     *
     * @param array $metadata
     * @return void
     */
    public function updateMetadata(array $metadata): void;
}
