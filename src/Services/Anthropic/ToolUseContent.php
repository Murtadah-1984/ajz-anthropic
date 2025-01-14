<?php

namespace Ajz\Anthropic\Services\Anthropic;

final class ToolUseContent
{
    public string $type = 'tool_use';
    public string $id;
    public string $name;
    public array $input;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->name = $data['name'];
        $this->input = $data['input'];
    }
}
