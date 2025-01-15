<?php

namespace Ajz\Anthropic\Factories;

use Ajz\Anthropic\Contracts\AgentInterface;
use Ajz\Anthropic\Models\Agent as AgentModel;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AgentFactory
{
    /**
     * The registered agent types.
     *
     * @var array
     */
    protected array $types = [];

    /**
     * The default agent configuration.
     *
     * @var array
     */
    protected array $defaultConfig = [];

    /**
     * Register an agent type.
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
            throw new InvalidArgumentException("Agent class {$class} does not exist");
        }

        if (!is_subclass_of($class, AgentInterface::class)) {
            throw new InvalidArgumentException("Agent class {$class} must implement AgentInterface");
        }

        $this->types[$type] = [
            'class' => $class,
            'config' => $defaultConfig,
        ];
    }

    /**
     * Create a new agent instance.
     *
     * @param string $type
     * @param array $config
     * @return AgentInterface
     * @throws InvalidArgumentException
     */
    public function create(string $type, array $config = []): AgentInterface
    {
        if (!isset($this->types[$type])) {
            throw new InvalidArgumentException("Unknown agent type: {$type}");
        }

        $agentInfo = $this->types[$type];
        $class = $agentInfo['class'];

        // Merge configurations
        $finalConfig = array_merge(
            $this->defaultConfig,
            $agentInfo['config'],
            $config
        );

        // Create agent instance
        $agent = $this->createAgentInstance($class, $finalConfig);

        // Initialize the agent
        $agent->initialize($finalConfig);

        return $agent;
    }

    /**
     * Create an agent from an existing model.
     *
     * @param AgentModel $model
     * @return AgentInterface
     * @throws InvalidArgumentException
     */
    public function createFromModel(AgentModel $model): AgentInterface
    {
        if (!isset($this->types[$model->type])) {
            throw new InvalidArgumentException("Unknown agent type: {$model->type}");
        }

        $agentInfo = $this->types[$model->type];
        $class = $agentInfo['class'];

        // Merge configurations
        $finalConfig = array_merge(
            $this->defaultConfig,
            $agentInfo['config'],
            $model->configuration
        );

        // Create agent instance
        $agent = $this->createAgentInstance($class, $finalConfig);

        // Initialize the agent with model data
        $agent->initialize(array_merge($finalConfig, [
            'model_id' => $model->id,
            'state' => $model->state,
            'metadata' => $model->metadata,
        ]));

        return $agent;
    }

    /**
     * Create multiple agents of the same type.
     *
     * @param string $type
     * @param int $count
     * @param array $config
     * @return array
     */
    public function createMany(string $type, int $count, array $config = []): array
    {
        $agents = [];
        for ($i = 0; $i < $count; $i++) {
            $agents[] = $this->create($type, $config);
        }
        return $agents;
    }

    /**
     * Get the registered agent types.
     *
     * @return array
     */
    public function getTypes(): array
    {
        return array_keys($this->types);
    }

    /**
     * Get the configuration schema for an agent type.
     *
     * @param string $type
     * @return array
     * @throws InvalidArgumentException
     */
    public function getConfigurationSchema(string $type): array
    {
        if (!isset($this->types[$type])) {
            throw new InvalidArgumentException("Unknown agent type: {$type}");
        }

        $class = $this->types[$type]['class'];
        return $class::getConfigurationSchema();
    }

    /**
     * Set the default configuration for all agents.
     *
     * @param array $config
     * @return void
     */
    public function setDefaultConfig(array $config): void
    {
        $this->defaultConfig = $config;
    }

    /**
     * Create an agent instance with the given configuration.
     *
     * @param string $class
     * @param array $config
     * @return AgentInterface
     */
    protected function createAgentInstance(string $class, array $config): AgentInterface
    {
        $name = $config['name'] ?? 'Agent-' . Str::random(8);
        $description = $config['description'] ?? '';

        return new $class($name, $description);
    }

    /**
     * Check if an agent type is registered.
     *
     * @param string $type
     * @return bool
     */
    public function hasType(string $type): bool
    {
        return isset($this->types[$type]);
    }

    /**
     * Get the class for an agent type.
     *
     * @param string $type
     * @return string|null
     */
    public function getClass(string $type): ?string
    {
        return $this->types[$type]['class'] ?? null;
    }

    /**
     * Get the default configuration for an agent type.
     *
     * @param string $type
     * @return array
     */
    public function getTypeConfig(string $type): array
    {
        return $this->types[$type]['config'] ?? [];
    }

    /**
     * Remove an agent type.
     *
     * @param string $type
     * @return void
     */
    public function unregister(string $type): void
    {
        unset($this->types[$type]);
    }

    /**
     * Clear all registered agent types.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->types = [];
        $this->defaultConfig = [];
    }
}
