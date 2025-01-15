<?php

namespace Ajz\Anthropic\Contracts;

interface AnthropicClaudeApiInterface
{
    /**
     * Send a message to Claude and get a response.
     *
     * @param string $message The message to send
     * @param array $options Additional options for the request
     * @return array The response from Claude
     */
    public function sendMessage(string $message, array $options = []): array;

    /**
     * Stream a response from Claude.
     *
     * @param string $message The message to send
     * @param array $options Additional options for the request
     * @return \Generator The streaming response
     */
    public function streamMessage(string $message, array $options = []): \Generator;

    /**
     * Get the current API configuration.
     *
     * @return array
     */
    public function getConfig(): array;

    /**
     * Set API configuration options.
     *
     * @param array $config
     * @return void
     */
    public function setConfig(array $config): void;
}
