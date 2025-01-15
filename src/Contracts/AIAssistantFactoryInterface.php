<?php

namespace Ajz\Anthropic\Contracts;

interface AIAssistantFactoryInterface
{
    /**
     * Create a new AI assistant instance.
     *
     * @param array $config Configuration options for the assistant
     * @return object The created assistant instance
     */
    public function create(array $config = []): object;

    /**
     * Create a specialized AI assistant instance.
     *
     * @param string $type The type of specialized assistant
     * @param array $config Configuration options for the assistant
     * @return object The created specialized assistant instance
     */
    public function createSpecialized(string $type, array $config = []): object;

    /**
     * Register a new assistant type.
     *
     * @param string $type The assistant type identifier
     * @param string $class The assistant class name
     * @param array $defaultConfig Default configuration for this type
     * @return void
     */
    public function registerType(string $type, string $class, array $defaultConfig = []): void;

    /**
     * Get the configuration for a specific assistant type.
     *
     * @param string $type The assistant type
     * @return array The configuration for the assistant type
     * @throws \InvalidArgumentException If the type is not registered
     */
    public function getTypeConfig(string $type): array;

    /**
     * Check if an assistant type is registered.
     *
     * @param string $type The assistant type to check
     * @return bool Whether the type is registered
     */
    public function hasType(string $type): bool;

    /**
     * Get all registered assistant types.
     *
     * @return array List of registered assistant types and their configurations
     */
    public function getRegisteredTypes(): array;
}
