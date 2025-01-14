<?php

namespace App\Services\Anthropic\Streaming;

class StreamEvent
{
    public string $type;
    public array $data;

    public function __construct(string $type, array $data)
    {
        $this->type = $type;
        $this->data = $data;
    }
}

class MessageStartEvent extends StreamEvent
{
    public Message $message;

    public function __construct(array $data)
    {
        parent::__construct('message_start', $data);
        $this->message = new Message($data['message']);
    }
}

class ContentBlockStartEvent extends StreamEvent
{
    public int $index;
    public array $contentBlock;

    public function __construct(array $data)
    {
        parent::__construct('content_block_start', $data);
        $this->index = $data['index'];
        $this->contentBlock = $data['content_block'];
    }
}

class ContentBlockDeltaEvent extends StreamEvent
{
    public int $index;
    public array $delta;

    public function __construct(array $data)
    {
        parent::__construct('content_block_delta', $data);
        $this->index = $data['index'];
        $this->delta = $data['delta'];
    }

    public function isTextDelta(): bool
    {
        return $this->delta['type'] === 'text_delta';
    }

    public function isInputJsonDelta(): bool
    {
        return $this->delta['type'] === 'input_json_delta';
    }

    public function getText(): ?string
    {
        return $this->isTextDelta() ? $this->delta['text'] : null;
    }

    public function getPartialJson(): ?string
    {
        return $this->isInputJsonDelta() ? $this->delta['partial_json'] : null;
    }
}

class ContentBlockStopEvent extends StreamEvent
{
    public int $index;

    public function __construct(array $data)
    {
        parent::__construct('content_block_stop', $data);
        $this->index = $data['index'];
    }
}

class MessageDeltaEvent extends StreamEvent
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

class MessageStopEvent extends StreamEvent
{
    public function __construct(array $data)
    {
        parent::__construct('message_stop', $data);
    }
}

class ErrorEvent extends StreamEvent
{
    public string $errorType;
    public string $errorMessage;

    public function __construct(array $data)
    {
        parent::__construct('error', $data);
        $this->errorType = $data['error']['type'];
        $this->errorMessage = $data['error']['message'];
    }
}

class Message
{
    public string $id;
    public string $type;
    public string $role;
    public string $model;
    public array $content;
    public ?string $stopReason;
    public ?string $stopSequence;
    public array $usage;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->type = $data['type'];
        $this->role = $data['role'];
        $this->model = $data['model'];
        $this->content = $data['content'];
        $this->stopReason = $data['stop_reason'];
        $this->stopSequence = $data['stop_sequence'];
        $this->usage = $data['usage'];
    }
}