<?php

namespace Ajz\Anthropic;

use Illuminate\Contracts\Container\Container;
use Ajz\Anthropic\Contracts\{
    AnthropicClaudeApiInterface,
    AIManagerInterface,
    AIAssistantFactoryInterface
};

class Anthropic
{
    /**
     * The container instance.
     *
     * @var Container
     */
    protected Container $container;

    /**
     * The API service instance.
     *
     * @var AnthropicClaudeApiInterface|null
     */
    protected ?AnthropicClaudeApiInterface $api = null;

    /**
     * The AI manager instance.
     *
     * @var AIManagerInterface|null
     */
    protected ?AIManagerInterface $manager = null;

    /**
     * The assistant factory instance.
     *
     * @var AIAssistantFactoryInterface|null
     */
    protected ?AIAssistantFactoryInterface $factory = null;

    /**
     * Create a new Anthropic instance.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Get the API service instance.
     *
     * @return AnthropicClaudeApiInterface
     */
    public function api(): AnthropicClaudeApiInterface
    {
        if (!$this->api) {
            $this->api = $this->container->make(AnthropicClaudeApiInterface::class);
        }

        return $this->api;
    }

    /**
     * Get the AI manager instance.
     *
     * @return AIManagerInterface
     */
    public function manager(): AIManagerInterface
    {
        if (!$this->manager) {
            $this->manager = $this->container->make(AIManagerInterface::class);
        }

        return $this->manager;
    }

    /**
     * Get the assistant factory instance.
     *
     * @return AIAssistantFactoryInterface
     */
    public function factory(): AIAssistantFactoryInterface
    {
        if (!$this->factory) {
            $this->factory = $this->container->make(AIAssistantFactoryInterface::class);
        }

        return $this->factory;
    }

    /**
     * Send a message to Claude and get a response.
     *
     * @param string $message
     * @param array $options
     * @return array
     */
    public function sendMessage(string $message, array $options = []): array
    {
        return $this->api()->sendMessage($message, $options);
    }

    /**
     * Stream a response from Claude.
     *
     * @param string $message
     * @param array $options
     * @return \Generator
     */
    public function streamMessage(string $message, array $options = []): \Generator
    {
        return $this->api()->streamMessage($message, $options);
    }

    /**
     * Create a new agent instance.
     *
     * @param string $type
     * @param array $config
     * @return object
     */
    public function createAgent(string $type, array $config = []): object
    {
        return $this->manager()->createAgent($type, $config);
    }

    /**
     * Create a new assistant instance.
     *
     * @param array $config
     * @return object
     */
    public function createAssistant(array $config = []): object
    {
        return $this->factory()->create($config);
    }

    /**
     * Get the current API configuration.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->api()->getConfig();
    }

    /**
     * Set API configuration options.
     *
     * @param array $config
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->api()->setConfig($config);
    }

    /**
     * Get the container instance.
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Set the container instance.
     *
     * @param Container $container
     * @return void
     */
    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }
}
