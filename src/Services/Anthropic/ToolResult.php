<?php

namespace Ajz\Anthropic\Services\Anthropic;

final class ToolResult
{
    public string $type = 'tool_result';
    public string $tool_use_id;
    public string $content;

    public function __construct(string $toolUseId, string $content)
    {
        $this->tool_use_id = $toolUseId;
        $this->content = $content;
    }
}
