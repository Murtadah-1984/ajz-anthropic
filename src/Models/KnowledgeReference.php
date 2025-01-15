<?php

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KnowledgeReference extends Model
{
    protected $fillable = [
        'entry_id',
        'reference_type',
        'reference_url',
        'reference_text'
    ];

    public function entry()
    {
        return $this->belongsTo(KnowledgeEntry::class);
    }
}

