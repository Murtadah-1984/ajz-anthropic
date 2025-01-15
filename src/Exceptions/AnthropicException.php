<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Exceptions;

final class AnthropicException extends \Exception
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
