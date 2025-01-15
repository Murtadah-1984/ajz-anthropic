<?php

declare(strict_types=1);

/**
 * @OA\Schema(
 *     schema="TextContent",
 *     title="Text Content",
 *     description="Represents text content in a message",
 *     required={"type", "text"},
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         description="Type of content, always 'text'",
 *         default="text"
 *     ),
 *     @OA\Property(
 *         property="text",
 *         type="string",
 *         description="The actual text content"
 *     )
 * )
 */

namespace Ajz\Anthropic\Services\Anthropic;

final class TextContent
{
    public readonly string $type = 'text';
    public readonly string $text;

    public function __construct(array $data)
    {
        $this->text = $data['text'];
    }
}
