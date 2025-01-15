<?php

namespace Ajz\Anthropic\Contracts;

interface OrganizationManagementInterface
{
    /**
     * Create a new organization.
     *
     * @param array $data Organization data
     * @return array The created organization
     */
    public function createOrganization(array $data): array;

    /**
     * Update an organization's details.
     *
     * @param string $organizationId
     * @param array $data
     * @return array The updated organization
     */
    public function updateOrganization(string $organizationId, array $data): array;

    /**
     * Get organization details.
     *
     * @param string $organizationId
     * @return array Organization details
     */
    public function getOrganization(string $organizationId): array;

    /**
     * Delete an organization.
     *
     * @param string $organizationId
     * @return bool Success status
     */
    public function deleteOrganization(string $organizationId): bool;

    /**
     * List all organizations the authenticated user has access to.
     *
     * @param array $filters Optional filters
     * @return array List of organizations
     */
    public function listOrganizations(array $filters = []): array;

    /**
     * Add a member to an organization.
     *
     * @param string $organizationId
     * @param string $userId
     * @param string $role
     * @return array Member details
     */
    public function addMember(string $organizationId, string $userId, string $role): array;

    /**
     * Remove a member from an organization.
     *
     * @param string $organizationId
     * @param string $userId
     * @return bool Success status
     */
    public function removeMember(string $organizationId, string $userId): bool;

    /**
     * Update a member's role in an organization.
     *
     * @param string $organizationId
     * @param string $userId
     * @param string $newRole
     * @return array Updated member details
     */
    public function updateMemberRole(string $organizationId, string $userId, string $newRole): array;

    /**
     * Get organization usage statistics.
     *
     * @param string $organizationId
     * @param array $filters Optional filters (date range, etc.)
     * @return array Usage statistics
     */
    public function getUsageStats(string $organizationId, array $filters = []): array;
}
