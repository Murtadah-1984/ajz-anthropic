<?php

namespace App\Services\Anthropic\Organization;

use DateTime;

class Workspace
{
    public string $id;
    public string $type = 'workspace';
    public string $name;
    public DateTime $created_at;
    public ?DateTime $archived_at;
    public string $display_color;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->name = $data['name'];
        $this->created_at = new DateTime($data['created_at']);
        $this->archived_at = isset($data['archived_at']) ? new DateTime($data['archived_at']) : null;
        $this->display_color = $data['display_color'];
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}

class WorkspaceList
{
    /** @var Workspace[] */
    public array $data;
    public bool $has_more;
    public ?string $first_id;
    public ?string $last_id;

    public function __construct(array $data)
    {
        $this->data = array_map(fn($workspace) => new Workspace($workspace), $data['data']);
        $this->has_more = $data['has_more'];
        $this->first_id = $data['first_id'];
        $this->last_id = $data['last_id'];
    }
}

class WorkspaceService extends BaseAnthropicService
{
    /**
     * Create a new workspace
     *
     * @param string $name Name of the workspace (1-40 characters)
     * @return Workspace
     * @throws AnthropicException
     */
    public function createWorkspace(string $name): Workspace
    {
        if (strlen($name) < 1 || strlen($name) > 40) {
            throw new \InvalidArgumentException('Workspace name must be between 1 and 40 characters');
        }

        try {
            $response = $this->getHttpClient()
                ->post("{$this->baseUrl}/organizations/workspaces", [
                    'name' => $name
                ]);

            return new Workspace($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * Get a workspace by ID
     *
     * @param string $workspaceId
     * @return Workspace
     * @throws AnthropicException
     */
    public function getWorkspace(string $workspaceId): Workspace
    {
        try {
            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/organizations/workspaces/{$workspaceId}");

            return new Workspace($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * List workspaces with pagination
     *
     * @param array $options (before_id, after_id, limit, include_archived)
     * @return WorkspaceList
     * @throws AnthropicException
     */
    public function listWorkspaces(array $options = []): WorkspaceList
    {
        try {
            $queryParams = array_filter([
                'before_id' => $options['before_id'] ?? null,
                'after_id' => $options['after_id'] ?? null,
                'limit' => $options['limit'] ?? null,
                'include_archived' => $options['include_archived'] ?? null,
            ]);

            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/organizations/workspaces", ['query' => $queryParams]);

            return new WorkspaceList($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * Update a workspace
     *
     * @param string $workspaceId
     * @param string $name New name (1-40 characters)
     * @return Workspace
     * @throws AnthropicException
     */
    public function updateWorkspace(string $workspaceId, string $name): Workspace
    {
        if (strlen($name) < 1 || strlen($name) > 40) {
            throw new \InvalidArgumentException('Workspace name must be between 1 and 40 characters');
        }

        try {
            $response = $this->getHttpClient()
                ->post("{$this->baseUrl}/organizations/workspaces/{$workspaceId}", [
                    'name' => $name
                ]);

            return new Workspace($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * Archive a workspace
     *
     * @param string $workspaceId
     * @return Workspace
     * @throws AnthropicException
     */
    public function archiveWorkspace(string $workspaceId): Workspace
    {
        try {
            $response = $this->getHttpClient()
                ->post("{$this->baseUrl}/organizations/workspaces/{$workspaceId}/archive");

            return new Workspace($this->handleResponse($response));
        } catch (AnthropicException $e) {
            throw $e;
        }
    }

    /**
     * Get all active workspaces (handles pagination automatically)
     *
     * @return Workspace[]
     * @throws AnthropicException
     */
    public function getAllActiveWorkspaces(): array
    {
        $workspaces = [];
        $lastId = null;
        
        do {
            $options = $lastId ? ['after_id' => $lastId] : [];
            $response = $this->listWorkspaces($options);
            
            // Filter out archived workspaces
            $activeWorkspaces = array_filter(
                $response->data,
                fn($workspace) => !$workspace->isArchived()
            );
            
            $workspaces = array_merge($workspaces, $activeWorkspaces);
            $lastId = $response->last_id;
        } while ($response->has_more && $lastId);

        return $workspaces;
    }

    /**
     * Find a workspace by name
     *
     * @param string $name
     * @param bool $includeArchived
     * @return Workspace|null
     * @throws AnthropicException
     */
    public function findWorkspaceByName(string $name, bool $includeArchived = false): ?Workspace
    {
        $lastId = null;
        
        do {
            $options = array_filter([
                'after_id' => $lastId,
                'include_archived' => $includeArchived,
            ]);
            
            $response = $this->listWorkspaces($options);
            
            foreach ($response->data as $workspace) {
                if ($workspace->name === $name) {
                    return $workspace;
                }
            }
            
            $lastId = $response->last_id;
        } while ($response->has_more && $lastId);

        return null;
    }
}