<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Anthropic\Streaming;

final class ContentBlockStopEvent extends StreamEvent
{
    public int $index;

    public function __construct(array $data)
    {
        parent::__construct('content_block_stop', $data);
        $this->index = $data['index'];
    }
}
