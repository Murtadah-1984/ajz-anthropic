<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Exceptions;

final class NotFoundException extends AnthropicException
{
    public function __construct(string $message, ?string $requestId = null)
    {
        parent::__construct($message, 'not_found_error', $requestId, 404);
    }
}

