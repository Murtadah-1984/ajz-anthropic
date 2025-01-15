<?php

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KnowledgeCategory extends Model
{
    use \Kalnoy\Nestedset\NodeTrait;

    protected $fillable = [
        'name',
        'slug',
        'description'
    ];

    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeEntry::class, 'knowledge_entry_categories');
    }
}

