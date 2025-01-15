<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Anthropic\Streaming;

final class MessageStopEvent extends StreamEvent
{
    public function __construct(array $data)
    {
        parent::__construct('message_stop', $data);
    }
}
