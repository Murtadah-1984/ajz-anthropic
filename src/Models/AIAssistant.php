<?php

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AIAssistant extends BaseModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'type',
        'model',
        'capabilities',
        'configuration',
        'is_active',
        'user_id',
        'organization_id',
        'metadata',
        'last_used_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'capabilities' => 'json',
        'configuration' => 'json',
        'metadata' => 'json',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
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
            'model' => ['required', 'string', 'max:100'],
            'capabilities' => ['required', 'array'],
            'configuration' => ['required', 'array'],
            'is_active' => ['boolean'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'metadata' => ['array'],
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
            'model',
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
            'model',
            'is_active',
            'user_id',
            'organization_id',
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
            'model',
            'is_active',
            'last_used_at',
            'created_at',
        ];
    }

    /**
     * Get the user that owns the assistant.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Get the organization that owns the assistant.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(config('anthropic.models.organization'));
    }

    /**
     * Get the assistant's sessions.
     *
     * @return HasMany
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    /**
     * Get the assistant's training history.
     *
     * @return HasMany
     */
    public function trainingHistory(): HasMany
    {
        return $this->hasMany(TrainingRecord::class);
    }

    /**
     * Get all of the assistant's artifacts.
     *
     * @return MorphMany
     */
    public function artifacts(): MorphMany
    {
        return $this->morphMany(Artifact::class, 'artifactable');
    }

    /**
     * Get the assistant's performance metrics.
     *
     * @return HasMany
     */
    public function performanceMetrics(): HasMany
    {
        return $this->hasMany(PerformanceMetric::class);
    }

    /**
     * Scope a query to only include assistants with specific capabilities.
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
     * Scope a query to only include recently used assistants.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $days
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRecentlyUsed($query, int $days = 7)
    {
        return $query->whereNotNull('last_used_at')
            ->where('last_used_at', '>=', now()->subDays($days));
    }

    /**
     * Update the last used timestamp.
     *
     * @return bool
     */
    public function touch(): bool
    {
        $this->last_used_at = now();
        return $this->save();
    }
}
