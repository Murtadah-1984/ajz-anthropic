<?php


namespace Ajz\Anthropic\Services\Anthropic;

final class TextContent
{
    public string $type = 'text';
    public string $text;

    public function __construct(array $data)
    {
        $this->text = $data['text'];
    }
}
