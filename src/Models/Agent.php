<?php

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Agent extends BaseModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'type',
        'capabilities',
        'configuration',
        'state',
        'status',
        'metadata',
        'is_active',
        'last_active_at',
        'user_id',
        'organization_id',
        'team_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'capabilities' => 'json',
        'configuration' => 'json',
        'state' => 'json',
        'metadata' => 'json',
        'is_active' => 'boolean',
        'last_active_at' => 'datetime',
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
        'status' => 'idle',
        'is_active' => true,
    ];

    /**
     * Get the model's validation rules.
     *
     * @return array
     */
    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
            'capabilities' => ['required', 'array'],
            'configuration' => ['required', 'array'],
            'state' => ['array'],
            'status' => ['required', 'string', 'in:idle,busy,paused,error'],
            'metadata' => ['array'],
            'is_active' => ['boolean'],
            'last_active_at' => ['nullable', 'date'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
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
            'name',
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
            'is_active',
            'user_id',
            'organization_id',
            'team_id',
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
            'name',
            'type',
            'status',
            'is_active',
            'last_active_at',
            'created_at',
        ];
    }

    /**
     * Get the user that owns the agent.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Get the organization that owns the agent.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(config('anthropic.models.organization'));
    }

    /**
     * Get the team that owns the agent.
     *
     * @return BelongsTo
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(config('anthropic.models.team'));
    }

    /**
     * Get the agent's tasks.
     *
     * @return HasMany
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the agent's sessions.
     *
     * @return BelongsToMany
     */
    public function sessions(): BelongsToMany
    {
        return $this->belongsToMany(Session::class)
            ->withPivot(['role', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    /**
     * Get all of the agent's artifacts.
     *
     * @return MorphMany
     */
    public function artifacts(): MorphMany
    {
        return $this->morphMany(Artifact::class, 'artifactable');
    }

    /**
     * Get the agent's performance metrics.
     *
     * @return HasMany
     */
    public function performanceMetrics(): HasMany
    {
        return $this->hasMany(AgentMetric::class);
    }

    /**
     * Scope a query to only include agents with specific capabilities.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array|string $capabilities
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithCapabilities($query, array|string $capabilities)
    {
        $capabilities = is_array($capabilities) ? $capabilities : [$capabilities];

        return $query->where(function ($query) use ($capabilities) {
            foreach ($capabilities as $capability) {
                $query->whereJsonContains('capabilities', $capability);
            }
        });
    }

    /**
     * Scope a query to only include available agents.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)
            ->where('status', 'idle');
    }

    /**
     * Update the agent's status.
     *
     * @param string $status
     * @return bool
     */
    public function updateStatus(string $status): bool
    {
        $this->status = $status;
        $this->last_active_at = now();
        return $this->save();
    }

    /**
     * Update the agent's state.
     *
     * @param array $state
     * @return bool
     */
    public function updateState(array $state): bool
    {
        $this->state = array_merge($this->state ?? [], $state);
        return $this->save();
    }

    /**
     * Check if the agent has a specific capability.
     *
     * @param string $capability
     * @return bool
     */
    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? []);
    }

    /**
     * Check if the agent is available.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->is_active && $this->status === 'idle';
    }
}
