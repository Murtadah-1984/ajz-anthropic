<?php

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TeamInvitation extends BaseModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'team_id',
        'email',
        'role',
        'token',
        'invited_by',
        'accepted_at',
        'rejected_at',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'token',
    ];

    /**
     * Get the model's validation rules.
     *
     * @return array
     */
    public static function validationRules(): array
    {
        return [
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', 'max:50'],
            'token' => ['required', 'string', 'max:100'],
            'invited_by' => ['required', 'integer', 'exists:users,id'],
            'accepted_at' => ['nullable', 'date'],
            'rejected_at' => ['nullable', 'date'],
            'expires_at' => ['required', 'date', 'after:now'],
        ];
    }

    /**
     * Get the team that owns the invitation.
     *
     * @return BelongsTo
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who sent the invitation.
     *
     * @return BelongsTo
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'invited_by');
    }

    /**
     * Check if the invitation has expired.
     *
     * @return bool
     */
    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the invitation is pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return !$this->accepted_at && !$this->rejected_at && !$this->hasExpired();
    }

    /**
     * Check if the invitation has been accepted.
     *
     * @return bool
     */
    public function isAccepted(): bool
    {
        return (bool) $this->accepted_at;
    }

    /**
     * Check if the invitation has been rejected.
     *
     * @return bool
     */
    public function isRejected(): bool
    {
        return (bool) $this->rejected_at;
    }

    /**
     * Accept the invitation.
     *
     * @return bool
     */
    public function accept(): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->accepted_at = now();
        return $this->save();
    }

    /**
     * Reject the invitation.
     *
     * @return bool
     */
    public function reject(): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->rejected_at = now();
        return $this->save();
    }

    /**
     * Cancel the invitation.
     *
     * @return bool
     */
    public function cancel(): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        return $this->delete();
    }

    /**
     * Extend the invitation expiration.
     *
     * @param \DateTimeInterface|\DateInterval|int $expires
     * @return bool
     */
    public function extend(mixed $expires): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        if (is_int($expires)) {
            $expires = now()->addDays($expires);
        } elseif ($expires instanceof \DateInterval) {
            $expires = now()->add($expires);
        }

        $this->expires_at = $expires;
        return $this->save();
    }

    /**
     * Generate a new invitation token.
     *
     * @return string
     */
    public static function generateToken(): string
    {
        return Str::random(100);
    }

    /**
     * Find an invitation by its token.
     *
     * @param string $token
     * @return static|null
     */
    public static function findByToken(string $token): ?static
    {
        return static::where('token', $token)->first();
    }

    /**
     * Get pending invitations for an email address.
     *
     * @param string $email
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getPendingForEmail(string $email)
    {
        return static::where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('rejected_at')
            ->where('expires_at', '>', now())
            ->get();
    }

    /**
     * Get pending invitations for a team.
     *
     * @param int $teamId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getPendingForTeam(int $teamId)
    {
        return static::where('team_id', $teamId)
            ->whereNull('accepted_at')
            ->whereNull('rejected_at')
            ->where('expires_at', '>', now())
            ->get();
    }

    /**
     * Check if a user already has a pending invitation to the team.
     *
     * @param string $email
     * @param int $teamId
     * @return bool
     */
    public static function hasPendingInvitation(string $email, int $teamId): bool
    {
        return static::where('email', $email)
            ->where('team_id', $teamId)
            ->whereNull('accepted_at')
            ->whereNull('rejected_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        parent::booted();

        static::creating(function ($invitation) {
            if (empty($invitation->token)) {
                $invitation->token = static::generateToken();
            }
        });
    }
}
