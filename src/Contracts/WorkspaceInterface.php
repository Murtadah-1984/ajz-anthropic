<?php

namespace Ajz\Anthropic\Contracts;

interface WorkspaceInterface
{
    /**
     * Create a new workspace.
     *
     * @param string $organizationId
     * @param array $data Workspace data
     * @return array The created workspace
     */
    public function createWorkspace(string $organizationId, array $data): array;

    /**
     * Update a workspace's details.
     *
     * @param string $workspaceId
     * @param array $data
     * @return array The updated workspace
     */
    public function updateWorkspace(string $workspaceId, array $data): array;

    /**
     * Get workspace details.
     *
     * @param string $workspaceId
     * @return array Workspace details
     */
    public function getWorkspace(string $workspaceId): array;

    /**
     * Delete a workspace.
     *
     * @param string $workspaceId
     * @return bool Success status
     */
    public function deleteWorkspace(string $workspaceId): bool;

    /**
     * List all workspaces in an organization.
     *
     * @param string $organizationId
     * @param array $filters Optional filters
     * @return array List of workspaces
     */
    public function listWorkspaces(string $organizationId, array $filters = []): array;

    /**
     * Add a member to a workspace.
     *
     * @param string $workspaceId
     * @param string $userId
     * @param string $role
     * @return array Member details
     */
    public function addMember(string $workspaceId, string $userId, string $role): array;

    /**
     * Remove a member from a workspace.
     *
     * @param string $workspaceId
     * @param string $userId
     * @return bool Success status
     */
    public function removeMember(string $workspaceId, string $userId): bool;

    /**
     * Update a member's role in a workspace.
     *
     * @param string $workspaceId
     * @param string $userId
     * @param string $newRole
     * @return array Updated member details
     */
    public function updateMemberRole(string $workspaceId, string $userId, string $newRole): array;

    /**
     * Get workspace settings.
     *
     * @param string $workspaceId
     * @return array Workspace settings
     */
    public function getSettings(string $workspaceId): array;

    /**
     * Update workspace settings.
     *
     * @param string $workspaceId
     * @param array $settings
     * @return array Updated settings
     */
    public function updateSettings(string $workspaceId, array $settings): array;

    /**
     * Get workspace usage statistics.
     *
     * @param string $workspaceId
     * @param array $filters Optional filters (date range, etc.)
     * @return array Usage statistics
     */
    public function getUsageStats(string $workspaceId, array $filters = []): array;
}
