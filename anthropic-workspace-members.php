<?php

namespace App\Services\Anthropic\Organization;

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

class WorkspaceMemberList
{
    /** @var WorkspaceMember[] */
    public array $data;
    public bool $has_more;
    public ?string $first_id;
    public ?string $last_id;

    public function __construct(array $data)
    {
        $this->data = array_map(fn($member) => new WorkspaceMember($member), $data['data']);
        $this->has_more = $data['has_more'];
        $this->first_id = $data['first_id'];
        $this->last_id = $data['last_id'];
    }
}

class WorkspaceMemberService extends BaseAnthropicService
{
    /**
     * Add a member to a workspace
     *
     * @param string $workspaceId
     * @param string $userId
     * @param string $role
     * @return WorkspaceMember
     * @throws AnthropicException
     */
    public function addMember(string $workspaceId, string $userId, string $role): WorkspaceMember
    {
        if (!in_array($role, WorkspaceMember::ASSIGNABLE_ROLES)) {
            throw new \InvalidArgumentException(
                "Invalid role. Must be one of: " . implode(', ', WorkspaceMember::ASSIGNABLE_ROLES)
            );
        }

        try {
            $response = $this->getHttpClient()
                ->post("{$this->baseUrl}/organizations/workspaces/{$workspaceId}/members", [
                    'user_id' => $userId,
                    'workspace_role' => $role
                ]);

            return new WorkspaceMember($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * Get a workspace member
     *
     * @param string $workspaceId
     * @param string $userId
     * @return WorkspaceMember
     * @throws AnthropicException
     */
    public function getMember(string $workspaceId, string $userId): WorkspaceMember
    {
        try {
            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/organizations/workspaces/{$workspaceId}/members/{$userId}");

            return new WorkspaceMember($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * List workspace members with pagination
     *
     * @param string $workspaceId
     * @param array $options (before_id, after_id, limit)
     * @return WorkspaceMemberList
     * @throws AnthropicException
     */
    public function listMembers(string $workspaceId, array $options = []): WorkspaceMemberList
    {
        try {
            $queryParams = array_filter([
                'before_id' => $options['before_id'] ?? null,
                'after_id' => $options['after_id'] ?? null,
                'limit' => $options['limit'] ?? null,
            ]);

            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/organizations/workspaces/{$workspaceId}/members", 
                    ['query' => $queryParams]
                );

            return new WorkspaceMemberList($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * Update a workspace member's role
     *
     * @param string $workspaceId
     * @param string $userId
     * @param string $role
     * @return WorkspaceMember
     * @throws AnthropicException
     */
    public function updateMemberRole(
        string $workspaceId,
        string $userId,
        string $role
    ): WorkspaceMember {
        if (!in_array($role, WorkspaceMember::VALID_ROLES)) {
            throw new \InvalidArgumentException(
                "Invalid role. Must be one of: " . implode(', ', WorkspaceMember::VALID_ROLES)
            );
        }

        try {
            $response = $this->getHttpClient()
                ->post("{$this->baseUrl}/organizations/workspaces/{$workspaceId}/members/{$userId}", [
                    'workspace_role' => $role
                ]);

            return new WorkspaceMember($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * Remove a member from a workspace
     *
     * @param string $workspaceId
     * @param string $userId
     * @return array
     * @throws AnthropicException
     */
    public function removeMember(string $workspaceId, string $userId): array
    {
        try {
            $response = $this->getHttpClient()
                ->delete("{$this->baseUrl}/organizations/workspaces/{$workspaceId}/members/{$userId}");

            return $this->handleResponse($response);
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * Get all members of a workspace (handles pagination automatically)
     *
     * @param string $workspaceId
     * @return WorkspaceMember[]
     * @throws AnthropicException
     */
    public function getAllMembers(string $workspaceId): array
    {
        $members = [];
        $lastId = null;
        
        do {
            $options = $lastId ? ['after_id' => $lastId] : [];
            $response = $this->listMembers($workspaceId, $options);
            
            $members = array_merge($members, $response->data);
            $lastId = $response->last_id;
        } while ($response->has_more && $lastId);

        return $members;
    }

    /**
     * Get workspace admins
     *
     * @param string $workspaceId
     * @return WorkspaceMember[]
     * @throws AnthropicException
     */
    public function getWorkspaceAdmins(string $workspaceId): array
    {
        $members = $this->getAllMembers($workspaceId);
        return array_filter($members, fn($member) => $member->isAdmin());
    }
}