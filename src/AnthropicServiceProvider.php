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
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Ajz\Anthropic\Services\AnthropicClaudeApiService;
use Ajz\Anthropic\Services\Agency\AIManager;
use Ajz\Anthropic\Http\Middleware\{
    HandleAnthropicErrors,
    ValidateAnthropicConfig,
    RateLimitAnthropicRequests,
    LogAnthropicRequests,
    CacheAnthropicResponses,
    TransformAnthropicResponse
};
use Ajz\Anthropic\Services\Organization\{
    WorkspaceService,
    WorkspaceMemberService,
    OrganizationManagementService,
    OrganizationInviteService,
    ApiKeyService
};

final class AnthropicServiceProvider extends ServiceProvider
{
    /**
     * All of the container singletons that should be registered.
     *
     * @var array
     */
    public array $singletons = [
        AgentMessageBroker::class => AgentMessageBroker::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/anthropic.php', 'anthropic'
        );

        $this->registerInterfaces();
        $this->registerServices();
        $this->registerFacade();
    }

    /**
     * Register interface bindings.
     */
    protected function registerInterfaces(): void
    {
        // Bind API interfaces
        $this->app->bind(AnthropicClaudeApiInterface::class, AnthropicClaudeApiService::class);
        $this->app->bind(AIAssistantFactoryInterface::class, AIAssistantFactory::class);
        $this->app->bind(AIManagerInterface::class, AIManager::class);

        // Bind organization interfaces
        $this->app->bind(WorkspaceInterface::class, WorkspaceService::class);
        $this->app->bind(OrganizationManagementInterface::class, OrganizationManagementService::class);
    }

    /**
     * Register service singletons.
     */
    protected function registerServices(): void
    {
        // Register main services
        $this->app->singleton(AnthropicClaudeApiService::class, function ($app) {
            return new AnthropicClaudeApiService();
        });

        $this->app->singleton(AIAssistantFactory::class, function ($app) {
            return new AIAssistantFactory(
                $app->make(AnthropicClaudeApiInterface::class)
            );
        });

        $this->app->singleton(AIManager::class, function ($app) {
            return new AIManager($app->make(AgentMessageBroker::class));
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
    }

    /**
     * Register the facade.
     */
    protected function registerFacade(): void
    {
        $this->app->singleton('anthropic', function ($app) {
            return new Anthropic($app);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerMiddleware();
        $this->registerPublishing();
        $this->registerRouteMiddleware();
        $this->registerEvents();
        $this->registerCommands();
    }

    /**
     * Register event subscribers.
     */
    protected function registerEvents(): void
    {
        $events = $this->app->make('events');

        // Register request lifecycle events
        $events->listen(AnthropicRequestStarted::class, function ($event) {
            // Log request start
            $this->app->make('log')->info('Anthropic request started', [
                'request' => $event->getRequest(),
                'timestamp' => $event->getTimestamp(),
            ]);
        });

        $events->listen(AnthropicRequestCompleted::class, function ($event) {
            // Log request completion
            $this->app->make('log')->info('Anthropic request completed', [
                'request' => $event->getRequest(),
                'response' => $event->getResponse(),
                'duration' => $event->getDuration(),
            ]);
        });

        $events->listen(AnthropicRequestFailed::class, function ($event) {
            // Log request failure
            $this->app->make('log')->error('Anthropic request failed', [
                'request' => $event->getRequest(),
                'error' => $event->getErrorMessage(),
                'code' => $event->getErrorCode(),
                'duration' => $event->getDuration(),
            ]);
        });
    }

    /**
     * Register console commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheCleanCommand::class,
                GenerateApiKeyCommand::class,
                ListAgentsCommand::class,
                MonitorUsageCommand::class,
            ]);
        }
    }

    /**
     * Register the middleware.
     */
    protected function registerMiddleware(): void
    {
        $kernel = $this->app->make(Kernel::class);
        $kernel->pushMiddleware(ValidateAnthropicConfig::class);
    }

    /**
     * Register the publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/anthropic.php' => config_path('anthropic.php'),
            ], 'anthropic-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'anthropic-migrations');
        }
    }

    /**
     * Register the route middleware.
     */
    protected function registerRouteMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        // Register middleware group with error handling first and response transformation last
        $router->middlewareGroup('anthropic', [
            HandleAnthropicErrors::class,
            ValidateAnthropicConfig::class,
            RateLimitAnthropicRequests::class,
            LogAnthropicRequests::class,
            CacheAnthropicResponses::class,
            TransformAnthropicResponse::class,
        ]);

        // Register individual middleware aliases
        $router->aliasMiddleware('anthropic.errors', HandleAnthropicErrors::class);
        $router->aliasMiddleware('anthropic.config', ValidateAnthropicConfig::class);
        $router->aliasMiddleware('anthropic.rate-limit', RateLimitAnthropicRequests::class);
        $router->aliasMiddleware('anthropic.log', LogAnthropicRequests::class);
        $router->aliasMiddleware('anthropic.cache', CacheAnthropicResponses::class);
        $router->aliasMiddleware('anthropic.transform', TransformAnthropicResponse::class);
    }

    /**
     * Register the middleware priority.
     *
     * @return void
     */
    protected function registerMiddlewarePriority(): void
    {
        $kernel = $this->app->make(Kernel::class);

        // Set middleware priority with error handling first and response transformation last
        $kernel->prependMiddlewareToGroup('anthropic', HandleAnthropicErrors::class);
        $kernel->appendMiddlewareToGroup('anthropic', ValidateAnthropicConfig::class);
        $kernel->appendMiddlewareToGroup('anthropic', RateLimitAnthropicRequests::class);
        $kernel->appendMiddlewareToGroup('anthropic', LogAnthropicRequests::class);
        $kernel->appendMiddlewareToGroup('anthropic', CacheAnthropicResponses::class);
        $kernel->appendMiddlewareToGroup('anthropic', TransformAnthropicResponse::class);
    }
}
