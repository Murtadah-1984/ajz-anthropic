<?php

namespace Ajz\Anthropic\Contracts;

interface AIManagerInterface
{
    /**
     * Create a new AI agent instance.
     *
     * @param string $type The type of agent to create
     * @param array $config Configuration options for the agent
     * @return object The created agent instance
     */
    public function createAgent(string $type, array $config = []): object;

    /**
     * Get a registered agent by its identifier.
     *
     * @param string $identifier The agent's unique identifier
     * @return object|null The agent instance or null if not found
     */
    public function getAgent(string $identifier): ?object;

    /**
     * Register a new agent type.
     *
     * @param string $type The agent type identifier
     * @param string $class The agent class name
     * @return void
     */
    public function registerAgentType(string $type, string $class): void;

    /**
     * Create a team of agents.
     *
     * @param string $teamType The type of team to create
     * @param array $agents Array of agent configurations
     * @return object The created team instance
     */
    public function createTeam(string $teamType, array $agents): object;

    /**
     * Get all registered agent types.
     *
     * @return array List of registered agent types
     */
    public function getRegisteredAgentTypes(): array;
}
