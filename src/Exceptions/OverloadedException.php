<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Exceptions;


final class OverloadedException extends AnthropicException
{
    public function __construct(string $message, ?string $requestId = null)
    {
        parent::__construct($message, 'overloaded_error', $requestId, 529);
    }
}
