<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Anthropic\Streaming;

final class ContentBlockStartEvent extends StreamEvent
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
