<?php

namespace Ajz\Anthropic\Facades;

use Illuminate\Support\Facades\Facade;
use Ajz\Anthropic\Contracts\{
    AnthropicClaudeApiInterface,
    AIManagerInterface,
    AIAssistantFactoryInterface
};

/**
 * @method static array sendMessage(string $message, array $options = [])
 * @method static \Generator streamMessage(string $message, array $options = [])
 * @method static object createAgent(string $type, array $config = [])
 * @method static object createAssistant(array $config = [])
 * @method static array getConfig()
 * @method static void setConfig(array $config)
 *
 * @see \Ajz\Anthropic\Anthropic
 */
class Anthropic extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'anthropic';
    }

    /**
     * Get the API service instance.
     *
     * @return AnthropicClaudeApiInterface
     */
    public static function api(): AnthropicClaudeApiInterface
    {
        return static::getFacadeRoot()->api();
    }

    /**
     * Get the AI manager instance.
     *
     * @return AIManagerInterface
     */
    public static function manager(): AIManagerInterface
    {
        return static::getFacadeRoot()->manager();
    }

    /**
     * Get the assistant factory instance.
     *
     * @return AIAssistantFactoryInterface
     */
    public static function factory(): AIAssistantFactoryInterface
    {
        return static::getFacadeRoot()->factory();
    }

    /**
     * Create a new agent instance.
     *
     * @param string $type
     * @param array $config
     * @return object
     */
    public static function agent(string $type, array $config = []): object
    {
        return static::manager()->createAgent($type, $config);
    }

    /**
     * Create a new assistant instance.
     *
     * @param array $config
     * @return object
     */
    public static function assistant(array $config = []): object
    {
        return static::factory()->create($config);
    }

    /**
     * Create a new specialized assistant instance.
     *
     * @param string $type
     * @param array $config
     * @return object
     */
    public static function specializedAssistant(string $type, array $config = []): object
    {
        return static::factory()->createSpecialized($type, $config);
    }

    /**
     * Get the configuration for a specific assistant type.
     *
     * @param string $type
     * @return array
     */
    public static function getAssistantConfig(string $type): array
    {
        return static::factory()->getTypeConfig($type);
    }

    /**
     * Register a new assistant type.
     *
     * @param string $type
     * @param string $class
     * @param array $defaultConfig
     * @return void
     */
    public static function registerAssistantType(string $type, string $class, array $defaultConfig = []): void
    {
        static::factory()->registerType($type, $class, $defaultConfig);
    }

    /**
     * Get all registered assistant types.
     *
     * @return array
     */
    public static function getRegisteredAssistantTypes(): array
    {
        return static::factory()->getRegisteredTypes();
    }
}
