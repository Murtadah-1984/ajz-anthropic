<?php

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KnowledgeCollection extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(KnowledgeEntry::class, 'collection_id');
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_knowledge_access')
            ->withPivot('access_permissions')
            ->withTimestamps();
    }
}

