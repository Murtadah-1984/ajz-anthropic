<?php

declare(strict_types=1);

/**
 * @OA\Schema(
 *     schema="Anthropic",
 *     title="Anthropic",
 *     description="Main Anthropic service class that provides access to various Anthropic API services"
 * )
 */

namespace Ajz\Anthropic;

use Illuminate\Contracts\Container\Container;
use Ajz\Anthropic\Services\AnthropicClaudeApiService;
use Ajz\Anthropic\Services\Anthropic\ApiKey\ApiKeyService;
use Ajz\Anthropic\Services\Anthropic\Organization\{
    WorkspaceService,
    WorkspaceMemberService,
    OrganizationManagementService,
    OrganizationInviteService
};

final class Anthropic
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @OA\Property(
     *     property="messages",
     *     description="Access the Claude messaging API service",
     *     type="object",
     *     ref="#/components/schemas/AnthropicClaudeApiService"
     * )
     */
    public function messages()
    {
        return $this->container->make(AnthropicClaudeApiService::class);
    }

    /**
     * @OA\Property(
     *     property="workspaces",
     *     description="Access the workspace management service",
     *     type="object",
     *     ref="#/components/schemas/WorkspaceService"
     * )
     */
    public function workspaces()
    {
        return $this->container->make(WorkspaceService::class);
    }

    /**
     * @OA\Property(
     *     property="workspaceMembers",
     *     description="Access the workspace member management service",
     *     type="object",
     *     ref="#/components/schemas/WorkspaceMemberService"
     * )
     */
    public function workspaceMembers()
    {
        return $this->container->make(WorkspaceMemberService::class);
    }

    /**
     * @OA\Property(
     *     property="organization",
     *     description="Access the organization management service",
     *     type="object",
     *     ref="#/components/schemas/OrganizationManagementService"
     * )
     */
    public function organization()
    {
        return $this->container->make(OrganizationManagementService::class);
    }

    /**
     * @OA\Property(
     *     property="invites",
     *     description="Access the organization invite management service",
     *     type="object",
     *     ref="#/components/schemas/OrganizationInviteService"
     * )
     */
    public function invites()
    {
        return $this->container->make(OrganizationInviteService::class);
    }

    /**
     * @OA\Property(
     *     property="apiKeys",
     *     description="Access the API key management service",
     *     type="object",
     *     ref="#/components/schemas/ApiKeyService"
     * )
     */
    public function apiKeys()
    {
        return $this->container->make(ApiKeyService::class);
    }
}
