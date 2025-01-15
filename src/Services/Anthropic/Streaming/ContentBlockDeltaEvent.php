<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Anthropic\Streaming;

final class ContentBlockDeltaEvent extends StreamEvent
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
