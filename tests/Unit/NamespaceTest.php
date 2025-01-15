<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class NamespaceTest extends TestCase
{
    /**
     * Test that classes are in the correct namespace.
     */
    public function test_classes_are_in_correct_namespace(): void
    {
        $baseNamespace = 'Ajz\\Anthropic';

        // Core service classes
        $this->assertClassNamespace("{$baseNamespace}\\Services", [
            'AnthropicClaudeApiService',
            'AIAssistantFactory',
        ]);

        // Agency classes
        $this->assertClassNamespace("{$baseNamespace}\\Agency", [
            'AIManager',
            'AgentMessageBroker',
        ]);

        // Organization classes
        $this->assertClassNamespace("{$baseNamespace}\\Services\\Organization", [
            'WorkspaceService',
            'OrganizationManagementService',
            'ApiKeyService',
        ]);

        // Middleware classes
        $this->assertClassNamespace("{$baseNamespace}\\Http\\Middleware", [
            'HandleAnthropicErrors',
            'ValidateAnthropicConfig',
            'RateLimitAnthropicRequests',
        ]);

        // Event classes
        $this->assertClassNamespace("{$baseNamespace}\\Events", [
            'AnthropicRequestStarted',
            'AnthropicRequestCompleted',
            'AnthropicRequestFailed',
        ]);
    }

    /**
     * Test that interfaces are properly implemented.
     */
    public function test_interfaces_are_properly_implemented(): void
    {
        // API interfaces
        $this->assertInterfaceImplementation(
            'Ajz\\Anthropic\\Contracts\\AnthropicClaudeApiInterface',
            'Ajz\\Anthropic\\Services\\AnthropicClaudeApiService'
        );

        $this->assertInterfaceImplementation(
            'Ajz\\Anthropic\\Contracts\\AIManagerInterface',
            'Ajz\\Anthropic\\Agency\\AIManager'
        );

        $this->assertInterfaceImplementation(
            'Ajz\\Anthropic\\Contracts\\AIAssistantFactoryInterface',
            'Ajz\\Anthropic\\Services\\AIAssistantFactory'
        );

        // Organization interfaces
        $this->assertInterfaceImplementation(
            'Ajz\\Anthropic\\Contracts\\WorkspaceInterface',
            'Ajz\\Anthropic\\Services\\Organization\\WorkspaceService'
        );

        $this->assertInterfaceImplementation(
            'Ajz\\Anthropic\\Contracts\\OrganizationManagementInterface',
            'Ajz\\Anthropic\\Services\\Organization\\OrganizationManagementService'
        );
    }

    /**
     * Test that facade bindings work correctly.
     */
    public function test_facade_bindings_work_correctly(): void
    {
        $this->assertTrue(class_exists('Ajz\\Anthropic\\Facades\\Anthropic'));

        // Test that the facade is properly registered in config
        $aliases = config('app.aliases', []);
        $this->assertArrayHasKey('Anthropic', $aliases);
        $this->assertEquals('Ajz\\Anthropic\\Facades\\Anthropic', $aliases['Anthropic']);
    }

    /**
     * Assert that classes exist in the given namespace.
     */
    private function assertClassNamespace(string $namespace, array $classes): void
    {
        foreach ($classes as $class) {
            $fullClass = "{$namespace}\\{$class}";
            $this->assertTrue(
                class_exists($fullClass),
                "Class {$fullClass} does not exist in expected namespace"
            );
        }
    }

    /**
     * Assert that a class implements an interface.
     */
    private function assertInterfaceImplementation(string $interface, string $class): void
    {
        $this->assertTrue(
            interface_exists($interface),
            "Interface {$interface} does not exist"
        );

        $this->assertTrue(
            class_exists($class),
            "Class {$class} does not exist"
        );

        $reflection = new ReflectionClass($class);
        $this->assertTrue(
            $reflection->implementsInterface($interface),
            "Class {$class} does not implement {$interface}"
        );
    }
}
