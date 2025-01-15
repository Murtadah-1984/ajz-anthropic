<?php

namespace Ajz\Anthropic\Factories;

use Ajz\Anthropic\Contracts\SessionInterface;
use Ajz\Anthropic\Models\Session as SessionModel;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SessionFactory
{
    /**
     * The registered session types.
     *
     * @var array
     */
    protected array $types = [];

    /**
     * The default session configuration.
     *
     * @var array
     */
    protected array $defaultConfig = [];

    /**
     * Register a session type.
     *
     * @param string $type
     * @param string $class
     * @param array $defaultConfig
     * @return void
     * @throws InvalidArgumentException
     */
    public function register(string $type, string $class, array $defaultConfig = []): void
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Session class {$class} does not exist");
        }

        if (!is_subclass_of($class, SessionInterface::class)) {
            throw new InvalidArgumentException("Session class {$class} must implement SessionInterface");
        }

        $this->types[$type] = [
            'class' => $class,
            'config' => $defaultConfig,
        ];
    }

    /**
     * Create a new session instance.
     *
     * @param string $type
     * @param array $config
     * @return SessionInterface
     * @throws InvalidArgumentException
     */
    public function create(string $type, array $config = []): SessionInterface
    {
        if (!isset($this->types[$type])) {
            throw new InvalidArgumentException("Unknown session type: {$type}");
        }

        $sessionInfo = $this->types[$type];
        $class = $sessionInfo['class'];

        // Merge configurations
        $finalConfig = array_merge(
            $this->defaultConfig,
            $sessionInfo['config'],
            $config
        );

        // Create session instance
        $session = new $class();

        // Initialize the session
        $session->initialize($finalConfig);

        return $session;
    }

    /**
     * Create a session from an existing model.
     *
     * @param SessionModel $model
     * @return SessionInterface
     * @throws InvalidArgumentException
     */
    public function createFromModel(SessionModel $model): SessionInterface
    {
        if (!isset($this->types[$model->type])) {
            throw new InvalidArgumentException("Unknown session type: {$model->type}");
        }

        $sessionInfo = $this->types[$model->type];
        $class = $sessionInfo['class'];

        // Merge configurations
        $finalConfig = array_merge(
            $this->defaultConfig,
            $sessionInfo['config'],
            $model->configuration
        );

        // Create session instance
        $session = new $class();

        // Initialize the session with model data
        $session->initialize(array_merge($finalConfig, [
            'model_id' => $model->id,
            'state' => $model->state,
            'context' => $model->context,
            'metadata' => $model->metadata,
        ]));

        return $session;
    }

    /**
     * Create multiple sessions of the same type.
     *
     * @param string $type
     * @param int $count
     * @param array $config
     * @return array
     */
    public function createMany(string $type, int $count, array $config = []): array
    {
        $sessions = [];
        for ($i = 0; $i < $count; $i++) {
            $sessions[] = $this->create($type, $config);
        }
        return $sessions;
    }

    /**
     * Get the registered session types.
     *
     * @return array
     */
    public function getTypes(): array
    {
        return array_keys($this->types);
    }

    /**
     * Get the configuration schema for a session type.
     *
     * @param string $type
     * @return array
     * @throws InvalidArgumentException
     */
    public function getConfigurationSchema(string $type): array
    {
        if (!isset($this->types[$type])) {
            throw new InvalidArgumentException("Unknown session type: {$type}");
        }

        $class = $this->types[$type]['class'];
        return $class::getConfigurationSchema();
    }

    /**
     * Set the default configuration for all sessions.
     *
     * @param array $config
     * @return void
     */
    public function setDefaultConfig(array $config): void
    {
        $this->defaultConfig = $config;
    }

    /**
     * Check if a session type is registered.
     *
     * @param string $type
     * @return bool
     */
    public function hasType(string $type): bool
    {
        return isset($this->types[$type]);
    }

    /**
     * Get the class for a session type.
     *
     * @param string $type
     * @return string|null
     */
    public function getClass(string $type): ?string
    {
        return $this->types[$type]['class'] ?? null;
    }

    /**
     * Get the default configuration for a session type.
     *
     * @param string $type
     * @return array
     */
    public function getTypeConfig(string $type): array
    {
        return $this->types[$type]['config'] ?? [];
    }

    /**
     * Remove a session type.
     *
     * @param string $type
     * @return void
     */
    public function unregister(string $type): void
    {
        unset($this->types[$type]);
    }

    /**
     * Clear all registered session types.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->types = [];
        $this->defaultConfig = [];
    }

    /**
     * Get active sessions of a specific type.
     *
     * @param string $type
     * @return array
     */
    public function getActiveSessions(string $type): array
    {
        $models = SessionModel::where('type', $type)
            ->where('status', 'active')
            ->get();

        return $models->map(fn ($model) => $this->createFromModel($model))->all();
    }

    /**
     * Get all active sessions.
     *
     * @return array
     */
    public function getAllActiveSessions(): array
    {
        $models = SessionModel::where('status', 'active')->get();
        return $models->map(fn ($model) => $this->createFromModel($model))->all();
    }

    /**
     * Get sessions by participant.
     *
     * @param int $participantId
     * @param string $participantType
     * @return array
     */
    public function getSessionsByParticipant(int $participantId, string $participantType): array
    {
        $models = SessionModel::whereHas('participants', function ($query) use ($participantId, $participantType) {
            $query->where('participant_id', $participantId)
                ->where('participant_type', $participantType);
        })->get();

        return $models->map(fn ($model) => $this->createFromModel($model))->all();
    }
}
