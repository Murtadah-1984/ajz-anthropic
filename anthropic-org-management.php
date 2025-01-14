<?php

namespace App\Services\Anthropic\Organization;

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

class UserList
{
    /** @var User[] */
    public array $data;
    public bool $has_more;
    public ?string $first_id;
    public ?string $last_id;

    public function __construct(array $data)
    {
        $this->data = array_map(fn($user) => new User($user), $data['data']);
        $this->has_more = $data['has_more'];
        $this->first_id = $data['first_id'];
        $this->last_id = $data['last_id'];
    }
}

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