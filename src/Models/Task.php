<?php

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Task extends BaseModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uuid',
        'type',
        'title',
        'description',
        'priority',
        'status',
        'progress',
        'context',
        'result',
        'metadata',
        'agent_id',
        'session_id',
        'user_id',
        'parent_task_id',
        'started_at',
        'completed_at',
        'due_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'priority' => 'integer',
        'progress' => 'integer',
        'context' => 'json',
        'result' => 'json',
        'metadata' => 'json',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'due_at' => 'datetime',
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
        'priority' => 0,
        'progress' => 0,
    ];

    /**
     * Get the model's validation rules.
     *
     * @return array
     */
    public static function validationRules(): array
    {
        return [
            'uuid' => ['required', 'string', 'uuid', 'unique:tasks,uuid'],
            'type' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', 'integer', 'min:0', 'max:10'],
            'status' => ['required', 'string', 'in:pending,in_progress,completed,failed,cancelled'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'context' => ['array'],
            'result' => ['array'],
            'metadata' => ['array'],
            'agent_id' => ['required', 'integer', 'exists:agents,id'],
            'session_id' => ['nullable', 'integer', 'exists:sessions,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'parent_task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'after:started_at'],
            'due_at' => ['nullable', 'date', 'after:now'],
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
            'title',
            'description',
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
            'priority',
            'status',
            'agent_id',
            'session_id',
            'user_id',
            'parent_task_id',
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
            'title',
            'priority',
            'status',
            'progress',
            'started_at',
            'completed_at',
            'due_at',
            'created_at',
        ];
    }

    /**
     * Get the agent that owns the task.
     *
     * @return BelongsTo
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Get the session that owns the task.
     *
     * @return BelongsTo
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    /**
     * Get the user that owns the task.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Get the parent task.
     *
     * @return BelongsTo
     */
    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_task_id');
    }

    /**
     * Get the subtasks.
     *
     * @return HasMany
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(static::class, 'parent_task_id');
    }

    /**
     * Get all of the task's artifacts.
     *
     * @return MorphMany
     */
    public function artifacts(): MorphMany
    {
        return $this->morphMany(Artifact::class, 'artifactable');
    }

    /**
     * Start the task.
     *
     * @return bool
     */
    public function start(): bool
    {
        $this->status = 'in_progress';
        $this->started_at = now();
        return $this->save();
    }

    /**
     * Complete the task.
     *
     * @param array $result
     * @return bool
     */
    public function complete(array $result = []): bool
    {
        $this->status = 'completed';
        $this->progress = 100;
        $this->result = $result;
        $this->completed_at = now();
        return $this->save();
    }

    /**
     * Fail the task.
     *
     * @param array $result
     * @return bool
     */
    public function fail(array $result = []): bool
    {
        $this->status = 'failed';
        $this->result = $result;
        $this->completed_at = now();
        return $this->save();
    }

    /**
     * Cancel the task.
     *
     * @return bool
     */
    public function cancel(): bool
    {
        $this->status = 'cancelled';
        $this->completed_at = now();
        return $this->save();
    }

    /**
     * Update the task's progress.
     *
     * @param int $progress
     * @return bool
     */
    public function updateProgress(int $progress): bool
    {
        $this->progress = max(0, min(100, $progress));
        return $this->save();
    }

    /**
     * Check if the task is overdue.
     *
     * @return bool
     */
    public function isOverdue(): bool
    {
        return $this->due_at && $this->due_at->isPast() && !$this->completed_at;
    }

    /**
     * Check if the task is completed.
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the task has failed.
     *
     * @return bool
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the task is cancelled.
     *
     * @return bool
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if the task is in progress.
     *
     * @return bool
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if the task is pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
