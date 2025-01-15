<?php

declare(strict_types=1);

/**
 * @OA\Schema(
 *     schema="ToolUseContent",
 *     title="Tool Use Content",
 *     description="Represents tool usage content in a message",
 *     required={"type", "id", "name", "input"},
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         description="Type of content, always 'tool_use'",
 *         default="tool_use"
 *     ),
 *     @OA\Property(
 *         property="id",
 *         type="string",
 *         description="Unique identifier for the tool use"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Name of the tool being used"
 *     ),
 *     @OA\Property(
 *         property="input",
 *         type="object",
 *         description="Input parameters for the tool",
 *         additionalProperties=true
 *     )
 * )
 */

namespace Ajz\Anthropic\Services\Anthropic;

final class ToolUseContent
{
    public readonly string $type = 'tool_use';
    public readonly string $id;
    public readonly string $name;
    /** @var array<string, mixed> */
    public readonly array $input;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->name = $data['name'];
        $this->input = $data['input'];
    }
}
