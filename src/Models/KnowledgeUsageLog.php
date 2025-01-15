<?php

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KnowledgeUsageLog extends Model
{
    protected $fillable = [
        'entry_id',
        'agent_id',
        'action_type',
        'context'
    ];

    protected $casts = [
        'context' => 'array'
    ];

    public function entry()
    {
        return $this->belongsTo(KnowledgeEntry::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
