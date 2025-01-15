<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Exceptions;

final class PermissionException extends AnthropicException
{
    public function __construct(string $message, ?string $requestId = null)
    {
        parent::__construct($message, 'permission_error', $requestId, 403);
    }
}

