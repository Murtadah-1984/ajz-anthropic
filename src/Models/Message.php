<?php

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Message extends BaseModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uuid',
        'type',
        'content',
        'metadata',
        'role',
        'status',
        'tokens',
        'session_id',
        'user_id',
        'agent_id',
        'parent_message_id',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'content' => 'json',
        'metadata' => 'json',
        'tokens' => 'integer',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the model's validation rules.
     *
     * @return array
     */
    public static function validationRules(): array
    {
        return [
            'uuid' => ['required', 'string', 'uuid', 'unique:messages,uuid'],
            'type' => ['required', 'string', 'max:50'],
            'content' => ['required', 'array'],
            'metadata' => ['array'],
            'role' => ['required', 'string', 'in:user,assistant,system'],
            'status' => ['required', 'string', 'in:pending,sent,delivered,read,failed'],
            'tokens' => ['integer', 'min:0'],
            'session_id' => ['required', 'integer', 'exists:sessions,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'parent_message_id' => ['nullable', 'integer', 'exists:messages,id'],
            'sent_at' => ['nullable', 'date'],
            'delivered_at' => ['nullable', 'date', 'after_or_equal:sent_at'],
            'read_at' => ['nullable', 'date', 'after_or_equal:delivered_at'],
        ];
    }

    /**
     * Get the model's searchable fields.
     *
     * @return array
     */
    public static function searchableFields(): array
    {
        return [
            'uuid',
            'type',
            'role',
            'status',
        ];
    }

    /**
     * Get the model's filterable fields.
     *
     * @return array
     */
    public static function filterableFields(): array
    {
        return [
            'type',
            'role',
            'status',
            'session_id',
            'user_id',
            'agent_id',
        ];
    }

    /**
     * Get the model's sortable fields.
     *
     * @return array
     */
    public static function sortableFields(): array
    {
        return [
            'type',
            'role',
            'status',
            'tokens',
            'sent_at',
            'delivered_at',
            'read_at',
            'created_at',
        ];
    }

    /**
     * Get the session that owns the message.
     *
     * @return BelongsTo
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    /**
     * Get the user that sent the message.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Get the agent that sent the message.
     *
     * @return BelongsTo
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Get the parent message.
     *
     * @return BelongsTo
     */
    public function parentMessage(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_message_id');
    }

    /**
     * Get all of the message's artifacts.
     *
     * @return MorphMany
     */
    public function artifacts(): MorphMany
    {
        return $this->morphMany(Artifact::class, 'artifactable');
    }

    /**
     * Mark the message as sent.
     *
     * @return bool
     */
    public function markAsSent(): bool
    {
        $this->status = 'sent';
        $this->sent_at = now();
        return $this->save();
    }

    /**
     * Mark the message as delivered.
     *
     * @return bool
     */
    public function markAsDelivered(): bool
    {
        $this->status = 'delivered';
        $this->delivered_at = now();
        return $this->save();
    }

    /**
     * Mark the message as read.
     *
     * @return bool
     */
    public function markAsRead(): bool
    {
        $this->status = 'read';
        $this->read_at = now();
        return $this->save();
    }

    /**
     * Mark the message as failed.
     *
     * @return bool
     */
    public function markAsFailed(): bool
    {
        $this->status = 'failed';
        return $this->save();
    }

    /**
     * Check if the message is from a user.
     *
     * @return bool
     */
    public function isFromUser(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Check if the message is from an assistant.
     *
     * @return bool
     */
    public function isFromAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    /**
     * Check if the message is a system message.
     *
     * @return bool
     */
    public function isSystemMessage(): bool
    {
        return $this->role === 'system';
    }
}
