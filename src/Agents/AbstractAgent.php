<?php

namespace Ajz\Anthropic\Agents;

use Ajz\Anthropic\Contracts\AgentInterface;
use Ajz\Anthropic\Models\Task;
use Ajz\Anthropic\Models\Session;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

abstract class AbstractAgent implements AgentInterface
{
    /**
     * The agent's unique identifier.
     *
     * @var string
     */
    protected string $id;

    /**
     * The agent's name.
     *
     * @var string
     */
    protected string $name;

    /**
     * The agent's description.
     *
     * @var string
     */
    protected string $description;

    /**
     * The agent's capabilities.
     *
     * @var array
     */
    protected array $capabilities = [];

    /**
     * The agent's current state.
     *
     * @var array
     */
    protected array $state = [];

    /**
     * The agent's configuration.
     *
     * @var array
     */
    protected array $config = [];

    /**
     * The agent's metadata.
     *
     * @var array
     */
    protected array $metadata = [];

    /**
     * Create a new agent instance.
     *
     * @param string $name
     * @param string $description
     */
    public function __construct(string $name, string $description)
    {
        $this->id = Str::uuid()->toString();
        $this->name = $name;
        $this->description = $description;
    }

    /**
     * Get the agent's unique identifier.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the agent's type.
     *
     * @return string
     */
    public function getType(): string
    {
        return class_basename($this);
    }

    /**
     * Get the agent's name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the agent's description.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get the agent's capabilities.
     *
     * @return array
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * Check if the agent has a specific capability.
     *
     * @param string $capability
     * @return bool
     */
    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities);
    }

    /**
     * Initialize the agent with configuration.
     *
     * @param array $config
     * @return void
     */
    public function initialize(array $config): void
    {
        $this->validateConfiguration($config);
        $this->config = $config;
        $this->state = [];
        $this->initializeAgent();
    }

    /**
     * Get the agent's current state.
     *
     * @return array
     */
    public function getState(): array
    {
        return $this->state;
    }

    /**
     * Update the agent's state.
     *
     * @param array $state
     * @return void
     */
    public function setState(array $state): void
    {
        $this->state = array_merge($this->state, $state);
    }

    /**
     * Handle a task assigned to the agent.
     *
     * @param Task $task
     * @return mixed
     */
    abstract public function handleTask(Task $task): mixed;

    /**
     * Join a session.
     *
     * @param Session $session
     * @param string $role
     * @return bool
     */
    public function joinSession(Session $session, string $role): bool
    {
        try {
            Event::dispatch('agent.joining_session', [$this, $session, $role]);

            $success = $session->addParticipant($this->getModel(), $role);

            if ($success) {
                Event::dispatch('agent.joined_session', [$this, $session, $role]);
            }

            return $success;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    /**
     * Leave a session.
     *
     * @param Session $session
     * @return bool
     */
    public function leaveSession(Session $session): bool
    {
        try {
            Event::dispatch('agent.leaving_session', [$this, $session]);

            $success = $session->removeParticipant($this->getModel());

            if ($success) {
                Event::dispatch('agent.left_session', [$this, $session]);
            }

            return $success;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    /**
     * Handle a message in a session.
     *
     * @param Session $session
     * @param array $message
     * @return mixed
     */
    abstract public function handleMessage(Session $session, array $message): mixed;

    /**
     * Get the agent's active sessions.
     *
     * @return Collection
     */
    public function getActiveSessions(): Collection
    {
        return $this->getModel()->activeSessions;
    }

    /**
     * Get the agent's current tasks.
     *
     * @return Collection
     */
    public function getCurrentTasks(): Collection
    {
        return $this->getModel()->currentTasks;
    }

    /**
     * Get the agent's task history.
     *
     * @return Collection
     */
    public function getTaskHistory(): Collection
    {
        return $this->getModel()->taskHistory;
    }

    /**
     * Get the agent's performance metrics.
     *
     * @param string $timeframe
     * @return array
     */
    public function getPerformanceMetrics(string $timeframe = 'daily'): array
    {
        return $this->getModel()->getPerformanceMetrics($timeframe);
    }

    /**
     * Train the agent with new data.
     *
     * @param array $trainingData
     * @return bool
     */
    abstract public function train(array $trainingData): bool;

    /**
     * Get the agent's training history.
     *
     * @return Collection
     */
    public function getTrainingHistory(): Collection
    {
        return $this->getModel()->trainingHistory;
    }

    /**
     * Validate input for the agent.
     *
     * @param array $input
     * @return bool
     */
    public function validateInput(array $input): bool
    {
        $validator = validator($input, $this->getValidationRules());
        return !$validator->fails();
    }

    /**
     * Get the agent's input validation rules.
     *
     * @return array
     */
    abstract public function getValidationRules(): array;

    /**
     * Handle an error that occurred during agent operation.
     *
     * @param \Throwable $error
     * @return void
     */
    public function handleError(\Throwable $error): void
    {
        Log::error('Agent Error', [
            'agent_id' => $this->getId(),
            'agent_type' => $this->getType(),
            'error' => $error->getMessage(),
            'trace' => $error->getTraceAsString(),
            'state' => $this->getState(),
        ]);

        Event::dispatch('agent.error', [$this, $error]);
    }

    /**
     * Pause the agent's activities.
     *
     * @return bool
     */
    public function pause(): bool
    {
        try {
            Event::dispatch('agent.pausing', [$this]);

            $this->getModel()->updateStatus('paused');
            $this->setState(['status' => 'paused']);

            Event::dispatch('agent.paused', [$this]);

            return true;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    /**
     * Resume the agent's activities.
     *
     * @return bool
     */
    public function resume(): bool
    {
        try {
            Event::dispatch('agent.resuming', [$this]);

            $this->getModel()->updateStatus('idle');
            $this->setState(['status' => 'idle']);

            Event::dispatch('agent.resumed', [$this]);

            return true;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    /**
     * Check if the agent is available.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->getModel()->isAvailable();
    }

    /**
     * Get the agent's metadata.
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Update the agent's metadata.
     *
     * @param array $metadata
     * @return void
     */
    public function updateMetadata(array $metadata): void
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        $this->getModel()->updateMetadata($this->metadata);
    }

    /**
     * Get the agent's database model.
     *
     * @return \Ajz\Anthropic\Models\Agent
     */
    abstract protected function getModel();

    /**
     * Initialize the agent-specific functionality.
     *
     * @return void
     */
    abstract protected function initializeAgent(): void;

    /**
     * Validate the agent's configuration.
     *
     * @param array $config
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function validateConfiguration(array $config): void
    {
        $schema = static::getConfigurationSchema();
        $validator = validator($config, $schema);

        if ($validator->fails()) {
            throw new \InvalidArgumentException(
                'Invalid agent configuration: ' . implode(', ', $validator->errors()->all())
            );
        }
    }
}
