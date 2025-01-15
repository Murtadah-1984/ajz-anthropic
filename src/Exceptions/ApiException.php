<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Exceptions;

final class ApiException extends AnthropicException
{
    public function __construct(string $message, ?string $requestId = null)
    {
        parent::__construct($message, 'api_error', $requestId, 500);
    }
}

