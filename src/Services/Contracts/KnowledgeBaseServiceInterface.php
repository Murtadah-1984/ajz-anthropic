<?php

namespace Ajz\Anthropic\Contracts;

use Illuminate\Support\Collection;

interface KnowledgeBaseServiceInterface
{
    /**
     * Create a new knowledge collection
     */
    public function createCollection(array $data): \Ajz\Anthropic\Models\KnowledgeCollection;

    /**
     * Add a new entry to the knowledge base
     */
    public function addEntry(array $data): \Ajz\Anthropic\Models\KnowledgeEntry;

    /**
     * Search the knowledge base
     */
    public function search(string $query, array $options = []): Collection;

    /**
     * Get relevant knowledge based on context
     */
    public function getRelevantKnowledge(string $agentId, array $context): Collection;

    /**
     * Create a new session
     */
    public function createSession(array $data): \Ajz\Anthropic\Models\KnowledgeSession;

    /**
     * Log knowledge usage
     */
    public function logUsage(array $data): void;

    /**
     * Get collection statistics
     */
    public function getCollectionStats(int $collectionId): array;

    /**
     * Train embeddings for knowledge entries
     */
    public function trainEmbeddings(array $collectionIds = [], callable $progressCallback = null): void;
}

