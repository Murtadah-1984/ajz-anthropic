<?php

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

class AnthropicServiceProvider extends ServiceProvider
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
