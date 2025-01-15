<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Illuminate\Container\Container;
use Ajz\Anthropic\AnthropicServiceProvider;
use Ajz\Anthropic\Contracts\{
    AIAssistantFactoryInterface,
    AIManagerInterface,
    AnthropicClaudeApiInterface,
    OrganizationManagementInterface,
    WorkspaceInterface
};
use Ajz\Anthropic\Console\Commands\{
    CacheCleanCommand,
    GenerateApiKeyCommand,
    ListAgentsCommand,
    MonitorUsageCommand
};
use Ajz\Anthropic\Events\{
    AnthropicRequestStarted,
    AnthropicRequestCompleted,
    AnthropicRequestFailed
};
use Illuminate\Support\Facades\Event;

class ServiceProviderTest extends TestCase
{
    protected Container $app;
    protected AnthropicServiceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Container();
        $this->app->singleton('events', function () {
            return $this->createMock(\Illuminate\Contracts\Events\Dispatcher::class);
        });

        $this->provider = new AnthropicServiceProvider($this->app);
    }

    public function test_services_are_properly_bound()
    {
        $this->provider->register();

        // Test interface bindings
        $this->assertTrue($this->app->bound(AIAssistantFactoryInterface::class));
        $this->assertTrue($this->app->bound(AIManagerInterface::class));
        $this->assertTrue($this->app->bound(AnthropicClaudeApiInterface::class));
        $this->assertTrue($this->app->bound(OrganizationManagementInterface::class));
        $this->assertTrue($this->app->bound(WorkspaceInterface::class));

        // Test concrete implementations
        $this->assertInstanceOf(
            AIAssistantFactoryInterface::class,
            $this->app->make(AIAssistantFactoryInterface::class)
        );
        $this->assertInstanceOf(
            AIManagerInterface::class,
            $this->app->make(AIManagerInterface::class)
        );
    }

    public function test_events_are_properly_registered()
    {
        $events = $this->app->make('events');

        // Expect event listeners to be registered
        $events->expects($this->exactly(3))
               ->method('listen')
               ->withConsecutive(
                   [$this->equalTo(AnthropicRequestStarted::class)],
                   [$this->equalTo(AnthropicRequestCompleted::class)],
                   [$this->equalTo(AnthropicRequestFailed::class)]
               );

        $this->provider->boot();
    }

    public function test_middleware_is_properly_configured()
    {
        $kernel = $this->createMock(\Illuminate\Contracts\Http\Kernel::class);
        $this->app->instance(\Illuminate\Contracts\Http\Kernel::class, $kernel);

        $router = $this->createMock(\Illuminate\Routing\Router::class);
        $this->app->instance(\Illuminate\Routing\Router::class, $router);

        // Expect middleware group to be registered
        $router->expects($this->once())
               ->method('middlewareGroup')
               ->with('anthropic', $this->isType('array'));

        // Expect middleware aliases to be registered
        $router->expects($this->exactly(6))
               ->method('aliasMiddleware');

        $this->provider->boot();
    }

    public function test_console_commands_are_registered()
    {
        $this->provider->boot();

        // Test that commands are registered
        $commands = [
            CacheCleanCommand::class,
            GenerateApiKeyCommand::class,
            ListAgentsCommand::class,
            MonitorUsageCommand::class,
        ];

        foreach ($commands as $command) {
            $this->assertTrue($this->app->bound($command));
        }
    }
}
