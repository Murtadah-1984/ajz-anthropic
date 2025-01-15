<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Contracts\Http\Kernel;
use Ajz\Anthropic\AnthropicServiceProvider;
use Ajz\Anthropic\Http\Middleware\ValidateAnthropicConfig;
use Ajz\Anthropic\Services\AnthropicClaudeApiService;
use Ajz\Anthropic\Services\Agency\AIManager;
use Ajz\Anthropic\AgentMessageBroker;

class AnthropicServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->register(AnthropicServiceProvider::class);
    }

    public function test_config_is_loaded()
    {
        $this->assertNotNull(config('anthropic'));
        $this->assertIsArray(config('anthropic'));
        $this->assertArrayHasKey('api', config('anthropic'));
    }

    public function test_middleware_is_registered_globally()
    {
        $kernel = $this->app->make(Kernel::class);
        $middleware = $kernel->getMiddleware();

        $this->assertTrue(in_array(ValidateAnthropicConfig::class, $middleware));
    }

    public function test_middleware_group_is_registered()
    {
        $middlewareGroups = Route::getMiddlewareGroups();

        $this->assertArrayHasKey('anthropic', $middlewareGroups);
        $this->assertTrue(in_array(
            ValidateAnthropicConfig::class,
            $middlewareGroups['anthropic']
        ));
    }

    public function test_middleware_alias_is_registered()
    {
        $router = $this->app['router'];
        $middleware = $router->getMiddleware();

        $this->assertArrayHasKey('anthropic.config', $middleware);
        $this->assertEquals(ValidateAnthropicConfig::class, $middleware['anthropic.config']);
    }

    public function test_services_are_registered_as_singletons()
    {
        // Test main services
        $this->assertInstanceOf(
            AnthropicClaudeApiService::class,
            $this->app->make(AnthropicClaudeApiService::class)
        );

        $this->assertInstanceOf(
            AIManager::class,
            $this->app->make(AIManager::class)
        );

        // Test message broker
        $this->assertInstanceOf(
            AgentMessageBroker::class,
            $this->app->make(AgentMessageBroker::class)
        );

        // Test singleton behavior
        $instance1 = $this->app->make(AIManager::class);
        $instance2 = $this->app->make(AIManager::class);
        $this->assertSame($instance1, $instance2);
    }

    public function test_facade_is_registered()
    {
        $this->assertTrue($this->app->bound('anthropic'));
        $this->assertInstanceOf(
            \Ajz\Anthropic\Anthropic::class,
            $this->app->make('anthropic')
        );
    }

    public function test_organization_services_are_registered()
    {
        $services = [
            \Ajz\Anthropic\Services\Organization\WorkspaceService::class,
            \Ajz\Anthropic\Services\Organization\WorkspaceMemberService::class,
            \Ajz\Anthropic\Services\Organization\OrganizationManagementService::class,
            \Ajz\Anthropic\Services\Organization\OrganizationInviteService::class,
            \Ajz\Anthropic\Services\Organization\ApiKeyService::class,
        ];

        foreach ($services as $service) {
            $this->assertTrue($this->app->bound($service));
            $this->assertInstanceOf($service, $this->app->make($service));
        }
    }

    public function test_publishable_assets_are_registered()
    {
        $publishable = AnthropicServiceProvider::pathsToPublish();

        $this->assertArrayHasKey('anthropic-config', $publishable);
        $this->assertArrayHasKey('anthropic-migrations', $publishable);

        $configPath = base_path('config/anthropic.php');
        $migrationsPath = database_path('migrations');

        $this->assertContains($configPath, array_values($publishable['anthropic-config']));
        $this->assertContains($migrationsPath, array_values($publishable['anthropic-migrations']));
    }

    public function test_middleware_can_be_used_in_routes()
    {
        Route::middleware('anthropic')->group(function () {
            Route::get('/test', function () {
                return 'OK';
            });
        });

        $routes = Route::getRoutes();
        $route = $routes->getByName('test');

        $this->assertTrue($route->hasMiddleware('anthropic'));
        $this->assertTrue(in_array(ValidateAnthropicConfig::class, $route->middleware()));
    }

    public function test_singleton_registration_is_correct()
    {
        $provider = new AnthropicServiceProvider($this->app);
        $singletons = $provider->singletons;

        $this->assertIsArray($singletons);
        $this->assertArrayHasKey(AgentMessageBroker::class, $singletons);
        $this->assertEquals(AgentMessageBroker::class, $singletons[AgentMessageBroker::class]);
    }
}
