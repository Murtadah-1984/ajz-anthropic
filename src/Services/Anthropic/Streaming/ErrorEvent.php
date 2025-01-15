<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Anthropic\Streaming;

final class ErrorEvent extends StreamEvent
{
    public string $errorType;
    public string $errorMessage;

    public function __construct(array $data)
    {
        parent::__construct('error', $data);
        $this->errorType = $data['error']['type'];
        $this->errorMessage = $data['error']['message'];
    }
}
