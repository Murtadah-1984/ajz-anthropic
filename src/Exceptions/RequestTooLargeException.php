<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Exceptions;

final class RequestTooLargeException extends AnthropicException
{
    public function __construct(string $message, ?string $requestId = null)
    {
        parent::__construct($message, 'request_too_large', $requestId, 413);
    }
}

