<?php

namespace Ajz\Anthropic\Contracts;

use Illuminate\Support\Collection;

interface KnowledgeAgentFactoryInterface
{
    /**
     * Create an agent from a profession description
     */
    public function createFromProfession(string $profession): Agent;

    /**
     * Analyze a profession description
     */
    public function analyzeProfession(string $profession): array;

    /**
     * Create a new agent class
     */
    public function createAgentClass(array $attributes): string;
}
