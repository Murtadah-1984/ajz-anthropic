<?php

namespace Ajz\Anthropic\Services\Anthropic;

final class ImageContent
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
