<?php

namespace Ajz\Anthropic\Services\Agency;

use Ajz\Anthropic\Contracts\KnowledgeBaseServiceInterface;
use Ajz\Anthropic\Repositories\KnowledgeBaseRepository;
use Ajz\Anthropic\Models\{KnowledgeCollection, KnowledgeEntry, KnowledgeSession};
use Illuminate\Support\Collection;
use OpenAI\Client as OpenAIClient;
use Illuminate\Support\Facades\Cache;

class KnowledgeBaseService implements KnowledgeBaseServiceInterface
{
    protected KnowledgeBaseRepository $repository;
    protected OpenAIClient $openai;

    public function __construct(
        KnowledgeBaseRepository $repository,
        OpenAIClient $openai
    ) {
        $this->repository = $repository;
        $this->openai = $openai;
    }

    public function createCollection(array $data): KnowledgeCollection
    {
        return $this->repository->createCollection($data);
    }

    public function addEntry(array $data): KnowledgeEntry
    {
        // Generate embeddings for vector search
        if (!isset($data['embeddings'])) {
            $data['embeddings'] = $this->generateEmbeddings($data['content']);
        }

        $entry = $this->repository->addEntry($data);

        // Clear relevant caches
        $this->clearKnowledgeCache($entry->collection_id);

        return $entry;
    }

    public function search(string $query, array $options = []): Collection
    {
        $cacheKey = "knowledge_search:" . md5($query . serialize($options));

        return Cache::tags(['knowledge_search'])->remember($cacheKey, 3600, function () use ($query, $options) {
            $results = collect();

            // Full-text search
            if (empty($options['search_type']) || $options['search_type'] === 'text') {
                $textResults = $this->repository->search($query, $options);
                $results = $results->merge($textResults);
            }

            // Vector search if needed
            if (empty($options['search_type']) || $options['search_type'] === 'vector') {
                $queryEmbeddings = $this->generateEmbeddings($query);
                $vectorResults = $this->vectorSearch($queryEmbeddings, $options);
                $results = $results->merge($vectorResults);
            }

            // Log the search
            $this->logKnowledgeAccess(
                entries: $results->pluck('id'),
                agentId: $options['agent_id'] ?? null,
                actionType: 'search',
                context: ['query' => $query, 'options' => $options]
            );

            return $results->unique('id')->sortByDesc('relevance');
        });
    }

    public function getRelevantKnowledge(string $agentId, array $context): Collection
    {
        $cacheKey = "relevant_knowledge:{$agentId}:" . md5(serialize($context));

        return Cache::tags(['knowledge_relevance'])->remember($cacheKey, 1800, function () use ($agentId, $context) {
            // Get agent's accessible collections
            $collections = $this->repository->getAgentCollections($agentId);

            // Generate context embeddings
            $contextEmbeddings = $this->generateEmbeddings(
                $this->formatContext($context)
            );

            // Search for relevant entries
            $results = $this->vectorSearch($contextEmbeddings, [
                'collection_ids' => $collections->pluck('id'),
                'limit' => 5
            ]);

            // Log access
            $this->logKnowledgeAccess(
                entries: $results->pluck('id'),
                agentId: $agentId,
                actionType: 'context_retrieval',
                context: $context
            );

            return $results;
        });
    }

    public function createSession(array $data): KnowledgeSession
    {
        return $this->repository->createSession($data);
    }

    public function logUsage(array $data): void
    {
        $this->repository->logUsage($data);
    }

    public function getCollectionStats(int $collectionId): array
    {
        $cacheKey = "collection_stats:{$collectionId}";

        return Cache::tags(['knowledge_stats'])->remember($cacheKey, 3600, function () use ($collectionId) {
            return $this->repository->getCollectionStats($collectionId);
        });
    }

    public function trainEmbeddings(array $collectionIds = [], callable $progressCallback = null): void
    {
        $entries = empty($collectionIds)
            ? $this->repository->getEntriesWithoutEmbeddings()
            : $this->repository->getEntriesByCollections($collectionIds);

        $total = $entries->count();
        $current = 0;

        foreach ($entries as $entry) {
            $embeddings = $this->generateEmbeddings($entry->content);
            $this->repository->updateEntryEmbeddings($entry->id, $embeddings);

            $current++;
            if ($progressCallback) {
                $progressCallback($current, $total);
            }
        }

        // Clear caches
        Cache::tags(['knowledge_search', 'knowledge_relevance'])->flush();
    }

    protected function generateEmbeddings(string $text): array
    {
        $response = $this->openai->embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => $text
        ]);

        return $response->embeddings[0]->embedding;
    }

    protected function vectorSearch(array $queryEmbeddings, array $options = []): Collection
    {
        return $this->repository->vectorSearch($queryEmbeddings, $options);
    }

    protected function logKnowledgeAccess(
        iterable $entries,
        ?string $agentId,
        string $actionType,
        array $context = []
    ): void {
        $this->repository->logKnowledgeAccess([
            'entries' => $entries,
            'agent_id' => $agentId,
            'action_type' => $actionType,
            'context' => $context
        ]);
    }

    protected function clearKnowledgeCache(int $collectionId): void
    {
        Cache::tags([
            'knowledge',
            "collection:{$collectionId}",
            'knowledge_search',
            'knowledge_relevance'
        ])->flush();
    }

    protected function formatContext(array $context): string
    {
        return collect($context)
            ->map(fn($value, $key) => "{$key}: {$value}")
            ->join("\n");
    }
}
