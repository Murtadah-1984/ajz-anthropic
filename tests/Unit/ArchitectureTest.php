<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Illuminate\Container\Container;
use Ajz\Anthropic\Repositories\Contracts\{
    AIAssistantRepositoryInterface,
    SessionRepositoryInterface,
    AgentRepositoryInterface
};
use Ajz\Anthropic\Services\Contracts\{
    AIAssistantServiceInterface,
    SessionServiceInterface,
    AgentServiceInterface
};

class ArchitectureTest extends TestCase
{
    protected Container $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Container();
    }

    public function test_service_interfaces_are_properly_implemented(): void
    {
        // Test AI Assistant Service
        $this->assertTrue(
            interface_exists(AIAssistantServiceInterface::class),
            'AIAssistantServiceInterface does not exist'
        );
        $this->assertServiceImplementsInterface(
            AIAssistantServiceInterface::class,
            'Ajz\Anthropic\Services\AIAssistantService'
        );

        // Test Session Service
        $this->assertTrue(
            interface_exists(SessionServiceInterface::class),
            'SessionServiceInterface does not exist'
        );
        $this->assertServiceImplementsInterface(
            SessionServiceInterface::class,
            'Ajz\Anthropic\Services\SessionService'
        );

        // Test Agent Service
        $this->assertTrue(
            interface_exists(AgentServiceInterface::class),
            'AgentServiceInterface does not exist'
        );
        $this->assertServiceImplementsInterface(
            AgentServiceInterface::class,
            'Ajz\Anthropic\Services\AgentService'
        );
    }

    public function test_repository_pattern_implementation(): void
    {
        // Test AI Assistant Repository
        $this->assertTrue(
            interface_exists(AIAssistantRepositoryInterface::class),
            'AIAssistantRepositoryInterface does not exist'
        );
        $this->assertRepositoryImplementsInterface(
            AIAssistantRepositoryInterface::class,
            'Ajz\Anthropic\Repositories\AIAssistantRepository'
        );

        // Test Session Repository
        $this->assertTrue(
            interface_exists(SessionRepositoryInterface::class),
            'SessionRepositoryInterface does not exist'
        );
        $this->assertRepositoryImplementsInterface(
            SessionRepositoryInterface::class,
            'Ajz\Anthropic\Repositories\SessionRepository'
        );

        // Test Agent Repository
        $this->assertTrue(
            interface_exists(AgentRepositoryInterface::class),
            'AgentRepositoryInterface does not exist'
        );
        $this->assertRepositoryImplementsInterface(
            AgentRepositoryInterface::class,
            'Ajz\Anthropic\Repositories\AgentRepository'
        );
    }

    public function test_dependency_injection_works_correctly(): void
    {
        // Test service dependency injection
        $this->assertDependencyInjection(
            AIAssistantServiceInterface::class,
            AIAssistantRepositoryInterface::class
        );
        $this->assertDependencyInjection(
            SessionServiceInterface::class,
            SessionRepositoryInterface::class
        );
        $this->assertDependencyInjection(
            AgentServiceInterface::class,
            AgentRepositoryInterface::class
        );

        // Test repository dependencies
        $this->assertRepositoryDependencies(AIAssistantRepositoryInterface::class);
        $this->assertRepositoryDependencies(SessionRepositoryInterface::class);
        $this->assertRepositoryDependencies(AgentRepositoryInterface::class);
    }

    /**
     * Assert that a service implements its interface.
     */
    private function assertServiceImplementsInterface(string $interface, string $concrete): void
    {
        $this->assertTrue(
            class_exists($concrete),
            "{$concrete} does not exist"
        );

        $reflection = new \ReflectionClass($concrete);
        $this->assertTrue(
            $reflection->implementsInterface($interface),
            "{$concrete} does not implement {$interface}"
        );
    }

    /**
     * Assert that a repository implements its interface.
     */
    private function assertRepositoryImplementsInterface(string $interface, string $concrete): void
    {
        $this->assertTrue(
            class_exists($concrete),
            "{$concrete} does not exist"
        );

        $reflection = new \ReflectionClass($concrete);
        $this->assertTrue(
            $reflection->implementsInterface($interface),
            "{$concrete} does not implement {$interface}"
        );
    }

    /**
     * Assert that dependency injection works for a service.
     */
    private function assertDependencyInjection(string $service, string $dependency): void
    {
        $this->app->bind($dependency, function () {
            return $this->createMock($dependency);
        });

        $instance = $this->app->make($service);
        $this->assertInstanceOf($service, $instance);

        $reflection = new \ReflectionClass($instance);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor, "Service {$service} should have a constructor");

        $parameters = $constructor->getParameters();
        $hasDependency = false;

        foreach ($parameters as $parameter) {
            if ($parameter->getType() && $parameter->getType()->getName() === $dependency) {
                $hasDependency = true;
                break;
            }
        }

        $this->assertTrue($hasDependency, "Service {$service} should depend on {$dependency}");
    }

    /**
     * Assert that a repository has the required dependencies.
     */
    private function assertRepositoryDependencies(string $repository): void
    {
        $instance = $this->app->make($repository);
        $reflection = new \ReflectionClass($instance);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor, "Repository {$repository} should have a constructor");

        $parameters = $constructor->getParameters();
        $this->assertGreaterThan(
            0,
            count($parameters),
            "Repository {$repository} should have dependencies"
        );

        foreach ($parameters as $parameter) {
            $this->assertTrue(
                $parameter->getType() && !$parameter->getType()->isBuiltin(),
                "Repository {$repository} should have object dependencies"
            );
        }
    }
}
