<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Anthropic\Streaming;

final class MessageDeltaEvent extends StreamEvent
{
    public array $delta;
    public array $usage;

    public function __construct(array $data)
    {
        parent::__construct('message_delta', $data);
        $this->delta = $data['delta'];
        $this->usage = $data['usage'];
    }
}
