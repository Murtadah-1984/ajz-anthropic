<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Anthropic\Streaming;

final class StreamEvent
{
    public string $type;
    public array $data;

    public function __construct(string $type, array $data)
    {
        $this->type = $type;
        $this->data = $data;
    }
}
