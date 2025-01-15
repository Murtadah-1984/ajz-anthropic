<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

final class ConversationMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
