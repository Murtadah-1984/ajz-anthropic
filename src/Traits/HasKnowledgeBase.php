<?php

namespace Ajz\Anthropic\Traits;

use Ajz\Anthropic\Services\KnowledgeBaseService;
use Illuminate\Support\Collection;

trait HasKnowledgeBase
{
    protected ?KnowledgeBaseService $knowledgeService = null;
    protected array $knowledgeContext = [];
    protected Collection $loadedKnowledge;

    /**
     * Initialize knowledge base access
     */
    public function initializeKnowledgeBase(): void
    {
        $this->knowledgeService = app(KnowledgeBaseService::class);
        $this->loadedKnowledge = collect();
    }

    /**
     * Set context for knowledge retrieval
     */
    public function setKnowledgeContext(array $context): self
    {
        $this->knowledgeContext = $context;
        return $this;
    }

    /**
     * Get relevant knowledge based on current context
     */
    public function getRelevantKnowledge(): Collection
    {
        if (!$this->knowledgeService) {
            $this->initializeKnowledgeBase();
        }

        $this->loadedKnowledge = $this->knowledgeService->getRelevantKnowledge(
            $this->getId(),
            $this->knowledgeContext
        );

        return $this->loadedKnowledge;
    }

    /**
     * Search knowledge base
     */
    public function searchKnowledge(string $query, array $options = []): Collection
    {
        if (!$this->knowledgeService) {
            $this->initializeKnowledgeBase();
        }

        return $this->knowledgeService->search($query, array_merge(
            $options,
            ['agent_id' => $this->getId()]
        ));
    }

    /**
     * Add knowledge to the base
     */
    public function addKnowledge(array $data): void
    {
        if (!$this->knowledgeService) {
            $this->initializeKnowledgeBase();
        }

        $this->knowledgeService->addEntry(array_merge(
            $data,
            ['added_by' => $this->getId()]
        ));
    }

    /**
     * Format loaded knowledge for prompts
     */
    public function formatKnowledgeForPrompt(): string
    {
        return $this->loadedKnowledge
            ->map(function ($entry) {
                return sprintf(
                    "Information about %s:\n%s",
                    $entry->title,
                    $entry->content
                );
            })
            ->join("\n\n");
    }

    /**
     * Get knowledge sources for citations
     */
    public function getKnowledgeSources(): array
    {
        return $this->loadedKnowledge
            ->map(function ($entry) {
                return [
                    'title' => $entry->title,
                    'references' => $entry->references->map(function ($ref) {
                        return [
                            'type' => $ref->reference_type,
                            'url' => $ref->reference_url,
                            'text' => $ref->reference_text
                        ];
                    })->toArray()
                ];
            })
            ->toArray();
    }

    /**
     * Check if knowledge exists
     */
    public function hasKnowledge(string $query): bool
    {
        return $this->searchKnowledge($query)->isNotEmpty();
    }

    /**
     * Update knowledge context and retrieve relevant information
     */
    public function withKnowledgeContext(array $context): Collection
    {
        return $this->setKnowledgeContext($context)->getRelevantKnowledge();
    }

    /**
     * Get knowledge statistics
     */
    public function getKnowledgeStats(): array
    {
        if (!$this->knowledgeService) {
            $this->initializeKnowledgeBase();
        }

        $collections = $this->knowledgeService->getAgentCollections($this->getId());

        return [
            'total_collections' => $collections->count(),
            'total_entries' => $collections->sum(fn($c) => $c->entries->count()),
            'knowledge_types' => $collections->flatMap(fn($c) => $c->entries->pluck('type'))
                ->unique()
                ->values()
                ->toArray(),
            'last_updated' => $collections->flatMap(fn($c) => $c->entries)
                ->max('updated_at')
                ->format('Y-m-d H:i:s')
        ];
    }
}
