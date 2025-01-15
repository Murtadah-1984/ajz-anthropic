<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Anthropic\WorkspaceMember;

class WorkspaceMember
{
    public string $type = 'workspace_member';
    public string $user_id;
    public string $workspace_id;
    public string $workspace_role;

    public const ROLE_USER = 'workspace_user';
    public const ROLE_DEVELOPER = 'workspace_developer';
    public const ROLE_ADMIN = 'workspace_admin';
    public const ROLE_BILLING = 'workspace_billing';

    public const VALID_ROLES = [
        self::ROLE_USER,
        self::ROLE_DEVELOPER,
        self::ROLE_ADMIN,
        self::ROLE_BILLING
    ];

    public const ASSIGNABLE_ROLES = [
        self::ROLE_USER,
        self::ROLE_DEVELOPER,
        self::ROLE_ADMIN
    ];

    public function __construct(array $data)
    {
        $this->user_id = $data['user_id'];
        $this->workspace_id = $data['workspace_id'];
        $this->workspace_role = $data['workspace_role'];
    }

    public function isAdmin(): bool
    {
        return $this->workspace_role === self::ROLE_ADMIN;
    }

    public function isDeveloper(): bool
    {
        return $this->workspace_role === self::ROLE_DEVELOPER;
    }

    public function isBilling(): bool
    {
        return $this->workspace_role === self::ROLE_BILLING;
    }
}
