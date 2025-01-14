<?php

namespace App\Services\Anthropic;

class Message
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

class Usage
{
    public int $input_tokens;
    public int $output_tokens;

    public function __construct(array $data)
    {
        $this->input_tokens = $data['input_tokens'];
        $this->output_tokens = $data['output_tokens'];
    }
}

class TextContent
{
    public string $type = 'text';
    public string $text;

    public function __construct(array $data)
    {
        $this->text = $data['text'];
    }
}

class ToolUseContent
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

class Tool
{
    public string $name;
    public ?string $description;
    public array $input_schema;

    public function __construct(string $name, ?string $description, array $input_schema)
    {
        $this->name = $name;
        $this->description = $description;
        $this->input_schema = $input_schema;
    }
}

class ImageContent
{
    public string $type = 'image';
    public array $source;

    public function __construct(string $base64Data, string $mediaType = 'image/jpeg')
    {
        $this->source = [
            'type' => 'base64',
            'media_type' => $mediaType,
            'data' => $base64Data
        ];
    }
}

class ToolResult
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