<?php

namespace Ajz\Anthropic\Events;

class AnthropicRequestStarted
{
    /**
     * The request data.
     *
     * @var array
     */
    public array $request;

    /**
     * The timestamp when the request started.
     *
     * @var float
     */
    public float $timestamp;

    /**
     * Create a new event instance.
     *
     * @param array $request
     */
    public function __construct(array $request)
    {
        $this->request = $request;
        $this->timestamp = microtime(true);
    }

    /**
     * Get the request data.
     *
     * @return array
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    /**
     * Get the timestamp.
     *
     * @return float
     */
    public function getTimestamp(): float
    {
        return $this->timestamp;
    }
}
