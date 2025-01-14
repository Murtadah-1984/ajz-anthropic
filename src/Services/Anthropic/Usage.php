<?php

namespace Ajz\Anthropic\Services\Anthropic;

final class Usage
{
    public int $input_tokens;
    public int $output_tokens;

    public function __construct(array $data)
    {
        $this->input_tokens = $data['input_tokens'];
        $this->output_tokens = $data['output_tokens'];
    }
}
