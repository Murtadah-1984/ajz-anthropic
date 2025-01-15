<?php

declare(strict_types=1);

/**
 * @OA\Schema(
 *     schema="Usage",
 *     title="Usage",
 *     description="Usage statistics for API calls",
 *     required={"input_tokens", "output_tokens"},
 *     @OA\Property(
 *         property="input_tokens",
 *         type="integer",
 *         description="Number of tokens in the input"
 *     ),
 *     @OA\Property(
 *         property="output_tokens",
 *         type="integer",
 *         description="Number of tokens in the output"
 *     )
 * )
 */

namespace Ajz\Anthropic\Services\Anthropic;

final class Usage
{
    public readonly int $input_tokens;
    public readonly int $output_tokens;

    public function __construct(array $data)
    {
        $this->input_tokens = $data['input_tokens'];
        $this->output_tokens = $data['output_tokens'];
    }

    public function total(): int
    {
        return $this->input_tokens + $this->output_tokens;
    }
}
