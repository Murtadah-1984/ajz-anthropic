<?php

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class OrganizationUser extends Pivot
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'permissions' => 'json',
        'metadata' => 'json',
        'joined_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'role',
        'permissions',
        'metadata',
        'joined_at',
        'expires_at',
    ];

    /**
     * Get the organization that the user belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user that belongs to the organization.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Check if the membership has expired.
     *
     * @return bool
     */
    public function hasExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if the user has a specific permission.
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }

    /**
     * Check if the user has any of the given permissions.
     *
     * @param array $permissions
     * @return bool
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return !empty(array_intersect($permissions, $this->permissions ?? []));
    }

    /**
     * Check if the user has all of the given permissions.
     *
     * @param array $permissions
     * @return bool
     */
    public function hasAllPermissions(array $permissions): bool
    {
        return empty(array_diff($permissions, $this->permissions ?? []));
    }

    /**
     * Add permissions to the user.
     *
     * @param array|string $permissions
     * @return bool
     */
    public function givePermissions(array|string $permissions): bool
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];
        $this->permissions = array_unique(array_merge($this->permissions ?? [], $permissions));
        return $this->save();
    }

    /**
     * Remove permissions from the user.
     *
     * @param array|string $permissions
     * @return bool
     */
    public function revokePermissions(array|string $permissions): bool
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];
        $this->permissions = array_diff($this->permissions ?? [], $permissions);
        return $this->save();
    }

    /**
     * Sync the user's permissions.
     *
     * @param array $permissions
     * @return bool
     */
    public function syncPermissions(array $permissions): bool
    {
        $this->permissions = $permissions;
        return $this->save();
    }

    /**
     * Update the user's role.
     *
     * @param string $role
     * @return bool
     */
    public function updateRole(string $role): bool
    {
        $this->role = $role;
        return $this->save();
    }

    /**
     * Update the user's metadata.
     *
     * @param array $metadata
     * @param bool $merge
     * @return bool
     */
    public function updateMetadata(array $metadata, bool $merge = true): bool
    {
        $this->metadata = $merge
            ? array_merge($this->metadata ?? [], $metadata)
            : $metadata;
        return $this->save();
    }

    /**
     * Extend the membership expiration.
     *
     * @param \DateTimeInterface|\DateInterval|int|null $expires
     * @return bool
     */
    public function extend(mixed $expires = null): bool
    {
        if (is_int($expires)) {
            $expires = now()->addDays($expires);
        } elseif ($expires instanceof \DateInterval) {
            $expires = now()->add($expires);
        }

        $this->expires_at = $expires;
        return $this->save();
    }
}
