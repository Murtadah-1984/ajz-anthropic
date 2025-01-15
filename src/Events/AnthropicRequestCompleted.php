<?php

namespace Ajz\Anthropic\Events;

class AnthropicRequestCompleted
{
    /**
     * The request data.
     *
     * @var array
     */
    public array $request;

    /**
     * The response data.
     *
     * @var array
     */
    public array $response;

    /**
     * The duration of the request in seconds.
     *
     * @var float
     */
    public float $duration;

    /**
     * Create a new event instance.
     *
     * @param array $request
     * @param array $response
     * @param float $startTime
     */
    public function __construct(array $request, array $response, float $startTime)
    {
        $this->request = $request;
        $this->response = $response;
        $this->duration = microtime(true) - $startTime;
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
     * Get the response data.
     *
     * @return array
     */
    public function getResponse(): array
    {
        return $this->response;
    }

    /**
     * Get the request duration in seconds.
     *
     * @return float
     */
    public function getDuration(): float
    {
        return $this->duration;
    }

    /**
     * Get the request duration in milliseconds.
     *
     * @return float
     */
    public function getDurationInMs(): float
    {
        return $this->duration * 1000;
    }
}
