<?php

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KnowledgeEntry extends Model
{
    protected $fillable = [
        'collection_id',
        'title',
        'content',
        'type',
        'metadata',
        'embeddings'
    ];

    protected $casts = [
        'metadata' => 'array',
        'embeddings' => 'array'
    ];

    public function collection()
    {
        return $this->belongsTo(KnowledgeCollection::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeCategory::class, 'knowledge_entry_categories');
    }

    public function references(): HasMany
    {
        return $this->hasMany(KnowledgeReference::class, 'entry_id');
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(KnowledgeUsageLog::class, 'entry_id');
    }

    public function scopeSearch($query, $search)
    {
        return $query->whereFullText(['title', 'content'], $search);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }
}



