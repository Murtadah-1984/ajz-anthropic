<?php

declare(strict_types=1);

namespace Ajz\Anthropic\DTOs;

final class SystemPromptConfig
{
    public function __construct(
        public readonly AssistantRole $role,
        public readonly array $context = [],
        public readonly array $guidelines = [],
        public readonly array $examples = [],
        public readonly array $outputFormat = [],
        public readonly array $bestPractices = []
    ) {}

    public function addComponent(string $key, string $content): self
    {
        return new self(
            role: $this->role,
            context: array_merge($this->context, [$key => $content]),
            guidelines: $this->guidelines,
            examples: $this->examples,
            outputFormat: $this->outputFormat,
            bestPractices: $this->bestPractices
        );
    }
}
