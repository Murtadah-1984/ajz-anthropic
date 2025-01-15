<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

final class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ai_assistant_id',
        'user_id',
        'subject',
        'status',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_message_at' => 'datetime'
    ];

    public function assistant()
    {
        return $this->belongsTo(AIAssistant::class, 'ai_assistant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('created_at');
    }

    public function delegations(): HasMany
    {
        return $this->hasMany(TaskDelegation::class);
    }
}
