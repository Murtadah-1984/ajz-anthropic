<?php

namespace Ajz\Anthropic\Events;

use Throwable;

class AnthropicRequestFailed
{
    /**
     * The request data.
     *
     * @var array
     */
    public array $request;

    /**
     * The error that occurred.
     *
     * @var Throwable
     */
    public Throwable $error;

    /**
     * The duration until the error occurred in seconds.
     *
     * @var float
     */
    public float $duration;

    /**
     * Create a new event instance.
     *
     * @param array $request
     * @param Throwable $error
     * @param float $startTime
     */
    public function __construct(array $request, Throwable $error, float $startTime)
    {
        $this->request = $request;
        $this->error = $error;
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
     * Get the error that occurred.
     *
     * @return Throwable
     */
    public function getError(): Throwable
    {
        return $this->error;
    }

    /**
     * Get the duration until the error occurred in seconds.
     *
     * @return float
     */
    public function getDuration(): float
    {
        return $this->duration;
    }

    /**
     * Get the duration until the error occurred in milliseconds.
     *
     * @return float
     */
    public function getDurationInMs(): float
    {
        return $this->duration * 1000;
    }

    /**
     * Get the error message.
     *
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->error->getMessage();
    }

    /**
     * Get the error code.
     *
     * @return int
     */
    public function getErrorCode(): int
    {
        return $this->error->getCode();
    }
}
