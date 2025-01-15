<?php

declare(strict_types=1);

/**
 * @OA\Schema(
 *     schema="Message",
 *     title="Message",
 *     description="Represents a message in the Anthropic API",
 *     required={"id", "type", "role", "model", "content"},
 *     @OA\Property(
 *         property="id",
 *         type="string",
 *         description="Unique identifier for the message"
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         description="Type of the message, always 'message'",
 *         default="message"
 *     ),
 *     @OA\Property(
 *         property="role",
 *         type="string",
 *         description="Role of the message sender, always 'assistant'",
 *         default="assistant"
 *     ),
 *     @OA\Property(
 *         property="model",
 *         type="string",
 *         description="The model used for generating the message"
 *     ),
 *     @OA\Property(
 *         property="content",
 *         type="array",
 *         description="Array of message content objects",
 *         @OA\Items(
 *             oneOf={
 *                 @OA\Schema(ref="#/components/schemas/TextContent"),
 *                 @OA\Schema(ref="#/components/schemas/ToolUseContent")
 *             }
 *         )
 *     ),
 *     @OA\Property(
 *         property="stop_reason",
 *         type="string",
 *         nullable=true,
 *         description="Reason why message generation stopped"
 *     ),
 *     @OA\Property(
 *         property="stop_sequence",
 *         type="string",
 *         nullable=true,
 *         description="Sequence that caused message generation to stop"
 *     ),
 *     @OA\Property(
 *         property="usage",
 *         ref="#/components/schemas/Usage",
 *         description="Usage statistics for the message"
 *     )
 * )
 */

namespace Ajz\Anthropic\Services\Anthropic;


final class Message
{
    public readonly string $id;
    public readonly string $type = 'message';
    public readonly string $role = 'assistant';
    public readonly string $model;
    /** @var array<int, TextContent|ToolUseContent> */
    public readonly array $content;
    public readonly ?string $stop_reason;
    public readonly ?string $stop_sequence;
    public readonly Usage $usage;

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
