<?php

namespace Ajz\Anthropic;

use Illuminate\Contracts\Container\Container;
use Ajz\Anthropic\Services\AnthropicClaudeApiService;
use Ajz\Anthropic\Services\Organization\{
    WorkspaceService,
    WorkspaceMemberService,
    OrganizationManagementService,
    OrganizationInviteService,
    ApiKeyService
};

class Anthropic
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function messages()
    {
        return $this->container->make(AnthropicClaudeApiService::class);
    }

    public function workspaces()
    {
        return $this->container->make(WorkspaceService::class);
    }

    public function workspaceMembers()
    {
        return $this->container->make(WorkspaceMemberService::class);
    }

    public function organization()
    {
        return $this->container->make(OrganizationManagementService::class);
    }

    public function invites()
    {
        return $this->container->make(OrganizationInviteService::class);
    }

    public function apiKeys()
    {
        return $this->container->make(ApiKeyService::class);
    }
}
