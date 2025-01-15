<?php

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Session extends BaseModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uuid',
        'type',
        'status',
        'context',
        'state',
        'metadata',
        'started_at',
        'ended_at',
        'user_id',
        'assistant_id',
        'parent_session_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'context' => 'json',
        'state' => 'json',
        'metadata' => 'json',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
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
            'uuid' => ['required', 'string', 'uuid', 'unique:sessions,uuid'],
            'type' => ['required', 'string', 'max:50'],
            'status' => ['required', 'string', 'in:pending,active,paused,completed,failed'],
            'context' => ['required', 'array'],
            'state' => ['array'],
            'metadata' => ['array'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'assistant_id' => ['required', 'integer', 'exists:ai_assistants,id'],
            'parent_session_id' => ['nullable', 'integer', 'exists:sessions,id'],
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
            'status',
            'user_id',
            'assistant_id',
            'parent_session_id',
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
            'status',
            'started_at',
            'ended_at',
            'created_at',
        ];
    }

    /**
     * Get the user that owns the session.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Get the assistant that owns the session.
     *
     * @return BelongsTo
     */
    public function assistant(): BelongsTo
    {
        return $this->belongsTo(AIAssistant::class);
    }

    /**
     * Get the parent session.
     *
     * @return BelongsTo
     */
    public function parentSession(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_session_id');
    }

    /**
     * Get the child sessions.
     *
     * @return HasMany
     */
    public function childSessions(): HasMany
    {
        return $this->hasMany(static::class, 'parent_session_id');
    }

    /**
     * Get the session's messages.
     *
     * @return HasMany
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get all of the session's artifacts.
     *
     * @return MorphMany
     */
    public function artifacts(): MorphMany
    {
        return $this->morphMany(Artifact::class, 'artifactable');
    }

    /**
     * Get the session's metrics.
     *
     * @return HasMany
     */
    public function metrics(): HasMany
    {
        return $this->hasMany(SessionMetric::class);
    }

    /**
     * Scope a query to only include active sessions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include completed sessions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Start the session.
     *
     * @return bool
     */
    public function start(): bool
    {
        $this->status = 'active';
        $this->started_at = now();
        return $this->save();
    }

    /**
     * End the session.
     *
     * @param string|null $status
     * @return bool
     */
    public function end(?string $status = 'completed'): bool
    {
        $this->status = $status;
        $this->ended_at = now();
        return $this->save();
    }

    /**
     * Pause the session.
     *
     * @return bool
     */
    public function pause(): bool
    {
        $this->status = 'paused';
        return $this->save();
    }

    /**
     * Resume the session.
     *
     * @return bool
     */
    public function resume(): bool
    {
        $this->status = 'active';
        return $this->save();
    }

    /**
     * Update the session state.
     *
     * @param array $state
     * @return bool
     */
    public function updateState(array $state): bool
    {
        $this->state = array_merge($this->state ?? [], $state);
        return $this->save();
    }
}
