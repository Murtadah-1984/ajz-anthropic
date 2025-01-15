<?php

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Organization extends BaseModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'domain',
        'settings',
        'metadata',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'settings' => 'json',
        'metadata' => 'json',
        'is_active' => 'boolean',
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
            'slug' => ['required', 'string', 'max:255', 'unique:organizations,slug'],
            'description' => ['nullable', 'string'],
            'domain' => ['nullable', 'string', 'max:255'],
            'settings' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
            'is_active' => ['boolean'],
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
            'slug',
            'description',
            'domain',
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
            'is_active',
            'domain',
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
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Get the teams for the organization.
     *
     * @return HasMany
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Get the users for the organization.
     *
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(config('auth.providers.users.model'))
            ->using(OrganizationUser::class)
            ->withPivot(['role', 'permissions', 'metadata', 'joined_at', 'expires_at'])
            ->withTimestamps();
    }

    /**
     * Get the AI assistants for the organization.
     *
     * @return HasMany
     */
    public function aiAssistants(): HasMany
    {
        return $this->hasMany(AIAssistant::class);
    }

    /**
     * Get the agents for the organization.
     *
     * @return HasMany
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /**
     * Get the invitations for the organization.
     *
     * @return HasMany
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /**
     * Get the pending invitations for the organization.
     *
     * @return HasMany
     */
    public function pendingInvitations(): HasMany
    {
        return $this->invitations()
            ->whereNull('accepted_at')
            ->whereNull('rejected_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Get users with a specific role.
     *
     * @param string $role
     * @return BelongsToMany
     */
    public function getUsersByRole(string $role): BelongsToMany
    {
        return $this->users()->wherePivot('role', $role);
    }

    /**
     * Check if a user is a member of the organization.
     *
     * @param int $userId
     * @return bool
     */
    public function hasMember(int $userId): bool
    {
        return $this->users()->where('user_id', $userId)->exists();
    }

    /**
     * Check if a user has a specific role in the organization.
     *
     * @param int $userId
     * @param string $role
     * @return bool
     */
    public function hasUserWithRole(int $userId, string $role): bool
    {
        return $this->users()
            ->where('user_id', $userId)
            ->wherePivot('role', $role)
            ->exists();
    }

    /**
     * Get the user's role in the organization.
     *
     * @param int $userId
     * @return string|null
     */
    public function getUserRole(int $userId): ?string
    {
        $pivot = $this->users()
            ->where('user_id', $userId)
            ->first()
            ?->pivot;

        return $pivot ? $pivot->role : null;
    }

    /**
     * Get the organization's settings.
     *
     * @param string|null $key
     * @param mixed|null $default
     * @return mixed
     */
    public function getSetting(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->settings;
        }

        return data_get($this->settings, $key, $default);
    }

    /**
     * Update the organization's settings.
     *
     * @param array $settings
     * @return bool
     */
    public function updateSettings(array $settings): bool
    {
        $this->settings = array_merge($this->settings ?? [], $settings);
        return $this->save();
    }
}
