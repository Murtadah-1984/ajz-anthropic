<?php

declare(strict_types=1);

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Laravel Anthropic API",
 *     description="Laravel package for integrating with the Anthropic AI API",
 *     @OA\Contact(
 *         email="murtadah.haddad@gmail.com",
 *         name="Murtadah Haddad"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 */

namespace Ajz\Anthropic;

use Illuminate\Support\ServiceProvider;
use Ajz\Anthropic\Services\AnthropicClaudeApiService;
use Ajz\Anthropic\Services\Organization\{
    WorkspaceService,
    WorkspaceMemberService,
    OrganizationManagementService,
    OrganizationInviteService,
    ApiKeyService
};

final class AnthropicServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/anthropic.php', 'anthropic'
        );

        // Register main services
        $this->app->singleton(AnthropicClaudeApiService::class, function ($app) {
            return new AnthropicClaudeApiService();
        });

        $this->app->singleton(AIAssistantFactory::class, function ($app) {
            return new AIAssistantFactory(
                $app->make(AnthropicClaudeApiService::class)
            );
        });

        // Register organization services
        $this->app->singleton(WorkspaceService::class, function ($app) {
            return new WorkspaceService();
        });

        $this->app->singleton(WorkspaceMemberService::class, function ($app) {
            return new WorkspaceMemberService();
        });

        $this->app->singleton(OrganizationManagementService::class, function ($app) {
            return new OrganizationManagementService();
        });

        $this->app->singleton(OrganizationInviteService::class, function ($app) {
            return new OrganizationInviteService();
        });

        $this->app->singleton(ApiKeyService::class, function ($app) {
            return new ApiKeyService();
        });

        // Register facade
        $this->app->singleton('anthropic', function ($app) {
            return new Anthropic($app);
        });
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/anthropic.php' => config_path('anthropic.php'),
            ], 'anthropic-config');
        }
    }
}
