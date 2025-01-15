<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

final class TaskDelegation extends Model
{
    protected $fillable = [
        'conversation_id',
        'from_assistant_id',
        'to_assistant_id',
        'reason',
        'context',
        'status'
    ];

    protected $casts = [
        'context' => 'array'
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function fromAssistant()
    {
        return $this->belongsTo(AIAssistant::class, 'from_assistant_id');
    }

    public function toAssistant()
    {
        return $this->belongsTo(AIAssistant::class, 'to_assistant_id');
    }
}
