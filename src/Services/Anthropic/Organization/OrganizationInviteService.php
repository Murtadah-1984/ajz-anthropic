<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Anthropic\Orgnization;


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
