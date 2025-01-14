<?php

namespace Ajz\Anthropic\Services\Anthropic;

use Ajz\Anthropic\Services\Anthropic\TextContent;
use Ajz\Anthropic\Services\Anthropic\ToolUseContent;
use Ajz\Anthropic\Services\Anthropic\Usage;


final class Message
{
    public string $id;
    public string $type = 'message';
    public string $role = 'assistant';
    public string $model;
    public array $content;
    public ?string $stop_reason;
    public ?string $stop_sequence;
    public Usage $usage;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->model = $data['model'];
        $this->content = array_map(function ($content) {
            return match ($content['type']) {
                'text' => new TextContent($content),
                'tool_use' => new ToolUseContent($content),
                default => throw new \InvalidArgumentException("Unknown content type: {$content['type']}")
            };
        }, $data['content']);
        $this->stop_reason = $data['stop_reason'];
        $this->stop_sequence = $data['stop_sequence'];
        $this->usage = new Usage($data['usage']);
    }
}
