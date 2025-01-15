<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Exceptions;


final class RateLimitException extends AnthropicException
{
    public function __construct(string $message, ?string $requestId = null)
    {
        parent::__construct($message, 'rate_limit_error', $requestId, 429);
    }
}

