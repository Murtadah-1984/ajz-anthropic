<?php

declare(strict_types=1);

/**
 * @OA\Schema(
 *     schema="AnthropicFacade",
 *     title="Anthropic Facade",
 *     description="Laravel Facade for accessing Anthropic services",
 *     @OA\Property(
 *         property="messages",
 *         ref="#/components/schemas/AnthropicClaudeApiService"
 *     ),
 *     @OA\Property(
 *         property="workspaces",
 *         ref="#/components/schemas/WorkspaceService"
 *     ),
 *     @OA\Property(
 *         property="workspaceMembers",
 *         ref="#/components/schemas/WorkspaceMemberService"
 *     ),
 *     @OA\Property(
 *         property="organization",
 *         ref="#/components/schemas/OrganizationManagementService"
 *     ),
 *     @OA\Property(
 *         property="invites",
 *         ref="#/components/schemas/OrganizationInviteService"
 *     ),
 *     @OA\Property(
 *         property="apiKeys",
 *         ref="#/components/schemas/ApiKeyService"
 *     )
 * )
 */

namespace Ajz\Anthropic\Facades;

use Illuminate\Support\Facades\Facade;

final class Anthropic extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'anthropic';
    }
}
