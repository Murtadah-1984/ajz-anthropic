<?php

namespace App\Services\Anthropic\Organization;

use DateTime;

class Invite
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

class InviteList
{
    /** @var Invite[] */
    public array $data;
    public bool $has_more;
    public ?string $first_id;
    public ?string $last_id;

    public function __construct(array $data)
    {
        $this->data = array_map(fn($invite) => new Invite($invite), $data['data']);
        $this->has_more = $data['has_more'];
        $this->first_id = $data['first_id'];
        $this->last_id = $data['last_id'];
    }
}

class OrganizationInviteService extends BaseAnthropicService
{
    /**
     * Create an invite
     *
     * @param string $email
     * @param string $role
     * @return Invite
     * @throws AnthropicException
     */
    public function createInvite(string $email, string $role): Invite
    {
        if (!in_array($role, Invite::VALID_ROLES)) {
            throw new \InvalidArgumentException(
                "Invalid role. Must be one of: " . implode(', ', Invite::VALID_ROLES)
            );
        }

        try {
            $response = $this->getHttpClient()
                ->post("{$this->baseUrl}/organizations/invites", [
                    'email' => $email,
                    'role' => $role
                ]);

            return new Invite($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * List invites with pagination
     *
     * @param array $options (before_id, after_id, limit)
     * @return InviteList
     * @throws AnthropicException
     */
    public function listInvites(array $options = []): InviteList
    {
        try {
            $queryParams = array_filter([
                'before_id' => $options['before_id'] ?? null,
                'after_id' => $options['after_id'] ?? null,
                'limit' => $options['limit'] ?? null,
            ]);

            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/organizations/invites", ['query' => $queryParams]);

            return new InviteList($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * Delete an invite
     *
     * @param string $inviteId
     * @return array
     * @throws AnthropicException
     */
    public function deleteInvite(string $inviteId): array
    {
        try {
            $response = $this->getHttpClient()
                ->delete("{$this->baseUrl}/organizations/invites/{$inviteId}");

            return $this->handleResponse($response);
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * Get all pending invites (handles pagination automatically)
     *
     * @return Invite[]
     * @throws AnthropicException
     */
    public function getAllPendingInvites(): array
    {
        $invites = [];
        $lastId = null;
        
        do {
            $options = $lastId ? ['after_id' => $lastId] : [];
            $response = $this->listInvites($options);
            
            // Filter for pending invites
            $pendingInvites = array_filter(
                $response->data,
                fn($invite) => $invite->isPending()
            );
            
            $invites = array_merge($invites, $pendingInvites);
            $lastId = $response->last_id;
        } while ($response->has_more && $lastId);

        return $invites;
    }

    /**
     * Clean up expired invites
     *
     * @return int Number of invites deleted
     * @throws AnthropicException
     */
    public function cleanupExpiredInvites(): int
    {
        $deleted = 0;
        $lastId = null;
        
        do {
            $options = $lastId ? ['after_id' => $lastId] : [];
            $response = $this->listInvites($options);
            
            foreach ($response->data as $invite) {
                if ($invite->isExpired()) {
                    $this->deleteInvite($invite->id);
                    $deleted++;
                }
            }
            
            $lastId = $response->last_id;
        } while ($response->has_more && $lastId);

        return $deleted;
    }
}