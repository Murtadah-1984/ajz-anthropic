<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Exceptions;

final class AuthenticationException extends AnthropicException
{
    public function __construct(string $message, ?string $requestId = null)
    {
        parent::__construct($message, 'authentication_error', $requestId, 401);
    }
}
