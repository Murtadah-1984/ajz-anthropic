<?php

namespace Ajz\Anthropic\Contracts;

use Illuminate\Support\Collection;

interface KnowledgeTeamInterface
{
    /**
     * Create a team from professional descriptions
     */
    public static function of(string|array $professions): self;

    /**
     * Add a professional agent to the team
     */
    public function addProfessionalAgent(string $profession): void;

    /**
     * Start a new session
     */
    public function startSession(array $options = []): string;

    /**
     * Get all agents in the team
     */
    public function getSessionAgents(): Collection;

    /**
     * Get a specific agent by ID
     */
    public function getAgent(string $agentId): ?Agent;

    /**
     * Get the current session ID
     */
    public function getCurrentSession(): ?string;

    /**
     * Initialize agent knowledge
     */
    public function initializeAgentKnowledge(Agent $agent, array $attributes): void;
}

