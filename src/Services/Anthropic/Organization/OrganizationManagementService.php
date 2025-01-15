<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Anthropic\Organization;

class OrganizationManagementService extends BaseAnthropicService
{
    /**
     * Get a user by ID
     *
     * @param string $userId
     * @return User
     * @throws AnthropicException
     */
    public function getUser(string $userId): User
    {
        try {
            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/organizations/users/{$userId}");

            return new User($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * List users with pagination and filtering
     *
     * @param array $options (before_id, after_id, limit, email)
     * @return UserList
     * @throws AnthropicException
     */
    public function listUsers(array $options = []): UserList
    {
        try {
            $queryParams = array_filter([
                'before_id' => $options['before_id'] ?? null,
                'after_id' => $options['after_id'] ?? null,
                'limit' => $options['limit'] ?? null,
                'email' => $options['email'] ?? null,
            ]);

            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/organizations/users", ['query' => $queryParams]);

            return new UserList($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * Update a user's role
     *
     * @param string $userId
     * @param string $role
     * @return User
     * @throws AnthropicException
     */
    public function updateUser(string $userId, string $role): User
    {
        if (!in_array($role, User::UPDATABLE_ROLES)) {
            throw new \InvalidArgumentException(
                "Invalid role. Must be one of: " . implode(', ', User::UPDATABLE_ROLES)
            );
        }

        try {
            $response = $this->getHttpClient()
                ->post("{$this->baseUrl}/organizations/users/{$userId}", [
                    'role' => $role
                ]);

            return new User($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * Remove a user from the organization
     *
     * @param string $userId
     * @return array
     * @throws AnthropicException
     */
    public function removeUser(string $userId): array
    {
        try {
            $response = $this->getHttpClient()
                ->delete("{$this->baseUrl}/organizations/users/{$userId}");

            return $this->handleResponse($response);
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * Get all users (handles pagination automatically)
     *
     * @param string|null $email Optional email filter
     * @return User[]
     * @throws AnthropicException
     */
    public function getAllUsers(?string $email = null): array
    {
        $users = [];
        $lastId = null;

        do {
            $options = array_filter([
                'after_id' => $lastId,
                'email' => $email,
            ]);

            $response = $this->listUsers($options);
            $users = array_merge($users, $response->data);
            $lastId = $response->last_id;
        } while ($response->has_more && $lastId);

        return $users;
    }

    /**
     * Find a user by email
     *
     * @param string $email
     * @return User|null
     * @throws AnthropicException
     */
    public function findUserByEmail(string $email): ?User
    {
        $users = $this->listUsers(['email' => $email]);
        return !empty($users->data) ? $users->data[0] : null;
    }
}
