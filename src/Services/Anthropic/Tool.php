<?php

namespace Ajz\Anthropic\Services\Anthropic;

final class Tool
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
