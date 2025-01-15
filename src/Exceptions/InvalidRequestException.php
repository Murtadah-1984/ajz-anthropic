<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Exceptions;

final class InvalidRequestException extends AnthropicException
{
    public function __construct(string $message, ?string $requestId = null)
    {
        parent::__construct($message, 'invalid_request_error', $requestId, 400);
    }
}
