<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Anthropic\Organization;

use DateTime;

class User
{
    public string $id;
    public string $type = 'user';
    public string $email;
    public string $name;
    public string $role;
    public DateTime $added_at;

    public const ROLE_USER = 'user';
    public const ROLE_DEVELOPER = 'developer';
    public const ROLE_BILLING = 'billing';
    public const ROLE_ADMIN = 'admin';

    public const VALID_ROLES = [
        self::ROLE_USER,
        self::ROLE_DEVELOPER,
        self::ROLE_BILLING,
        self::ROLE_ADMIN
    ];

    public const UPDATABLE_ROLES = [
        self::ROLE_USER,
        self::ROLE_DEVELOPER,
        self::ROLE_BILLING
    ];

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->email = $data['email'];
        $this->name = $data['name'];
        $this->role = $data['role'];
        $this->added_at = new DateTime($data['added_at']);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isDeveloper(): bool
    {
        return $this->role === self::ROLE_DEVELOPER;
    }
}
