<?php

namespace Ajz\Anthropic\Teams\Agency;

use Ajz\Anthropic\Contracts\{KnowledgeTeamInterface, KnowledgeBaseServiceInterface};
use Ajz\Anthropic\Repositories\KnowledgeTeamRepository;
use Ajz\Anthropic\Factories\KnowledgeAgentFactory;
use Ajz\Anthropic\Services\AnthropicClaudeApiService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class KnowledgeBaseTeam implements KnowledgeTeamInterface
{
    protected Collection $agents;
    protected KnowledgeTeamRepository $repository;
    protected KnowledgeBaseServiceInterface $knowledgeService;
    protected KnowledgeAgentFactory $agentFactory;
    protected AnthropicClaudeApiService $claude;
    protected ?string $sessionId = null;

    public function __construct(
        KnowledgeTeamRepository $repository,
        KnowledgeBaseServiceInterface $knowledgeService,
        KnowledgeAgentFactory $agentFactory,
        AnthropicClaudeApiService $claude
    ) {
        $this->repository = $repository;
        $this->knowledgeService = $knowledgeService;
        $this->agentFactory = $agentFactory;
        $this->claude = $claude;
        $this->agents = collect();
    }

    public static function of(string|array $professions): self
    {
        $team = app(self::class);

        if (is_string($professions)) {
            $professions = [$professions];
        }

        foreach ($professions as $profession) {
            $team->addProfessionalAgent($profession);
        }

        return $team;
    }

    public function addProfessionalAgent(string $profession): void
    {
        $agent = $this->agentFactory->createFromProfession($profession);
        $this->agents->put($agent->getId(), $agent);

        // Initialize agent's knowledge base
        $this->initializeAgentKnowledge($agent, $profession);

        // If session exists, add agent to session
        if ($this->sessionId) {
            $this->repository->addAgentToSession($this->sessionId, $agent->getId());
        }
    }

    public function startSession(array $options = []): string
    {
        $this->sessionId = uniqid('kb_session_');

        // Create session record
        $session = $this->repository->createTeamSession([
            'session_id' => $this->sessionId,
            'team_agents' => $this->agents->pluck('id')->toArray(),
            'options' => $options
        ]);

        // Initialize each agent for the session
        $this->agents->each(function ($agent) use ($options) {
            $this->initializeAgentForSession($agent, $options);
        });

        // Log session start
        $this->repository->logTeamActivity($this->sessionId, [
            'type' => 'session_start',
            'agents' => $this->agents->pluck('id')->toArray(),
            'options' => $options
        ]);

        return $this->sessionId;
    }

    public function getSessionAgents(): Collection
    {
        if (!$this->sessionId) {
            return collect();
        }

        $agentIds = $this->repository->getSessionAgents($this->sessionId);
        return $this->agents->only($agentIds);
    }

    public function getAgent(string $agentId)
    {
        return $this->agents->get($agentId);
    }

    public function getCurrentSession(): ?string
    {
        return $this->sessionId;
    }

    public function initializeAgentKnowledge($agent, string $profession): void
    {
        // Get profession analysis from cache or analyze
        $cacheKey = "agent_profession:" . md5($profession);
        $attributes = Cache::remember($cacheKey, 86400, function () use ($profession) {
            return $this->analyzeProfession($profession);
        });

        // Create knowledge collection
        $collection = $this->knowledgeService->createCollection([
            'name' => "Knowledge Base for {$attributes['role']}",
            'slug' => str($attributes['role'])->slug(),
            'metadata' => $attributes
        ]);

        // Generate core knowledge
        $this->generateCoreKnowledge($collection->id, $attributes);

        // Assign collection to agent
        if (method_exists($agent, 'assignKnowledgeCollection')) {
            $agent->assignKnowledgeCollection($collection->id);
        }

        // Log initialization
        $this->repository->logTeamActivity($this->sessionId ?? 'initialization', [
            'type' => 'agent_initialization',
            'agent_id' => $agent->getId(),
            'profession' => $profession,
            'collection_id' => $collection->id
        ]);
    }

    protected function analyzeProfession(string $profession): array
    {
        $response = $this->claude->messages()->create([
            'model' => 'claude-3-5-sonnet-20241022',
            'messages' => [[
                'role' => 'user',
                'content' => "Analyze this professional role and return a JSON structure with role details: {$profession}"
            ]],
            'system' => "You are a professional role analyzer. Extract key aspects of professional roles into structured data."
        ]);

        return array_merge(
            json_decode($response->content, true),
            ['original_profession' => $profession]
        );
    }

    protected function generateCoreKnowledge(int $collectionId, array $attributes): void
    {
        // Generate role-specific knowledge
        $this->generateRoleKnowledge($collectionId, $attributes);

        // Generate specialization knowledge
        if (!empty($attributes['specializations'])) {
            foreach ($attributes['specializations'] as $specialization) {
                $this->generateSpecializationKnowledge($collectionId, $specialization);
            }
        }

        // Generate principles knowledge
        if (!empty($attributes['principles'])) {
            foreach ($attributes['principles'] as $principle) {
                $this->generatePrincipleKnowledge($collectionId, $principle);
            }
        }
    }

    protected function initializeAgentForSession($agent, array $options): void
    {
        if (method_exists($agent, 'initializeForSession')) {
            $agent->initializeForSession($this->sessionId, $options);
        }

        $this->repository->logTeamActivity($this->sessionId, [
            'type' => 'agent_session_init',
            'agent_id' => $agent->getId(),
            'options' => $options
        ]);
    }

    protected function generateRoleKnowledge(int $collectionId, array $attributes): void
    {
        $rolePrompt = $this->getRoleKnowledgePrompt($attributes);

        $response = $this->claude->messages()->create([
            'model' => 'claude-3-5-sonnet-20241022',
            'messages' => [[
                'role' => 'user',
                'content' => $rolePrompt
            ]],
            'system' => "You are an expert knowledge base generator."
        ]);

        $knowledge = json_decode($response->content, true);

        foreach ($knowledge as $entry) {
            $this->knowledgeService->addEntry(array_merge($entry, [
                'collection_id' => $collectionId
            ]));
        }
    }

    protected function getRoleKnowledgePrompt(array $attributes): string
    {
        return <<<EOT
Generate a comprehensive knowledge base for a {$attributes['seniority']} {$attributes['role']}.
Include entries covering:
1. Core responsibilities
2. Best practices
3. Common patterns and solutions
4. Key technologies and tools
5. Industry standards

Format each entry as a JSON object with:
- title
- content
- type
- metadata (optional)

Return an array of these knowledge entries.
EOT;
    }
}
