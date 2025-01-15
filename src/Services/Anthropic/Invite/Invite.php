<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Anthropic\Invite;

use DateTime;

final class Invite
{
    public string $id;
    public string $type = 'invite';
    public string $email;
    public string $role;
    public DateTime $invited_at;
    public DateTime $expires_at;
    public string $status;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_DELETED = 'deleted';

    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACCEPTED,
        self::STATUS_EXPIRED,
        self::STATUS_DELETED
    ];

    public const VALID_ROLES = [
        User::ROLE_USER,
        User::ROLE_DEVELOPER,
        User::ROLE_BILLING
    ];

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->email = $data['email'];
        $this->role = $data['role'];
        $this->invited_at = new DateTime($data['invited_at']);
        $this->expires_at = new DateTime($data['expires_at']);
        $this->status = $data['status'];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED ||
               $this->expires_at < new DateTime();
    }
}
