<?php

namespace Ajz\Anthropic\Repositories;

use Ajz\Anthropic\Models\{
    KnowledgeCollection,
    KnowledgeEntry,
    KnowledgeSession,
    KnowledgeUsageLog
};
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class KnowledgeBaseRepository
{
    /**
     * Create a new collection
     */
    public function createCollection(array $data): KnowledgeCollection
    {
        return KnowledgeCollection::create($data);
    }

    /**
     * Add entry to collection
     */
    public function addEntry(array $data): KnowledgeEntry
    {
        return KnowledgeEntry::create($data);
    }

    /**
     * Search entries
     */
    public function search(string $query, array $options = []): Collection
    {
        return KnowledgeEntry::query()
            ->when(!empty($options['collection_id']), function (Builder $query) use ($options) {
                $query->where('collection_id', $options['collection_id']);
            })
            ->when(!empty($options['type']), function (Builder $query) use ($options) {
                $query->where('type', $options['type']);
            })
            ->when(true, function (Builder $query) use ($query) {
                $query->whereFullText(['title', 'content'], $query);
            })
            ->when(!empty($options['limit']), function (Builder $query) use ($options) {
                $query->limit($options['limit']);
            })
            ->get();
    }

    /**
     * Get entries by collection
     */
    public function getEntriesByCollection(int $collectionId): Collection
    {
        return KnowledgeEntry::where('collection_id', $collectionId)->get();
    }

    /**
     * Create session
     */
    public function createSession(array $data): KnowledgeSession
    {
        return KnowledgeSession::create($data);
    }

    /**
     * Log usage
     */
    public function logUsage(array $data): void
    {
        KnowledgeUsageLog::create($data);
    }

    /**
     * Get collection statistics
     */
    public function getCollectionStats(int $collectionId): array
    {
        $collection = KnowledgeCollection::findOrFail($collectionId);
        $entries = $collection->entries;

        return [
            'total_entries' => $entries->count(),
            'entry_types' => $entries->pluck('type')->unique()->values()->toArray(),
            'content_size' => $entries->sum(fn($entry) => strlen($entry->content)),
            'avg_entry_size' => $entries->avg(fn($entry) => strlen($entry->content)),
            'last_updated' => $entries->max('updated_at')->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Update entry embeddings
     */
    public function updateEntryEmbeddings(int $entryId, array $embeddings): void
    {
        KnowledgeEntry::where('id', $entryId)
            ->update(['embeddings' => $embeddings]);
    }

    /**
     * Get entries without embeddings
     */
    public function getEntriesWithoutEmbeddings(): Collection
    {
        return KnowledgeEntry::whereNull('embeddings')->get();
    }
}
