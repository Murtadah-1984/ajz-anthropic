<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Anthropic\Streaming;

final class MessageStartEvent extends StreamEvent
{
    public Message $message;

    public function __construct(array $data)
    {
        parent::__construct('message_start', $data);
        $this->message = new Message($data['message']);
    }
}
