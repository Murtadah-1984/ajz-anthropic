<?php

namespace App\Exceptions\Anthropic;

class AnthropicException extends \Exception
{
    protected string $anthropicType;
    protected ?string $requestId;

    public function __construct(string $message, string $type, ?string $requestId = null, int $code = 0)
    {
        parent::__construct($message, $code);
        $this->anthropicType = $type;
        $this->requestId = $requestId;
    }

    public function getAnthropicType(): string
    {
        return $this->anthropicType;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}

class InvalidRequestException extends AnthropicException
{
    public function __construct(string $message, ?string $requestId = null)
    {
        parent::__construct($message, 'invalid_request_error', $requestId, 400);
    }
}

class AuthenticationException extends AnthropicException
{
    public function __construct(string $message, ?string $requestId = null)
    {
        parent::__construct($message, 'authentication_error', $requestId, 401);
    }
}

class PermissionException extends AnthropicException
{
    public function __construct(string $message, ?string $requestId = null)
    {
        parent::__construct($message, 'permission_error', $requestId, 403);
    }
}

class NotFoundException extends AnthropicException
{
    public function __construct(string $message, ?string $requestId = null)
    {
        parent::__construct($message, 'not_found_error', $requestId, 404);
    }
}

class RequestTooLargeException extends AnthropicException
{
    public function __construct(string $message, ?string $requestId = null)
    {
        parent::__construct($message, 'request_too_large', $requestId, 413);
    }
}

class RateLimitException extends AnthropicException
{
    public function __construct(string $message, ?string $requestId = null)
    {
        parent::__construct($message, 'rate_limit_error', $requestId, 429);
    }
}

class ApiException extends AnthropicException
{
    public function __construct(string $message, ?string $requestId = null)
    {
        parent::__construct($message, 'api_error', $requestId, 500);
    }
}

class OverloadedException extends AnthropicException
{
    public function __construct(string $message, ?string $requestId = null)
    {
        parent::__construct($message, 'overloaded_error', $requestId, 529);
    }
}