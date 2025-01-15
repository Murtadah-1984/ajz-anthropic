<?php

namespace Tests\Unit\Exceptions;

use Tests\TestCase;
use Ajz\Anthropic\Exceptions\InvalidConfigurationException;

class InvalidConfigurationExceptionTest extends TestCase
{
    public function test_missing_config_creates_correct_exception()
    {
        $exception = InvalidConfigurationException::missingConfig('api.key');

        $this->assertEquals("Missing required configuration value for 'api.key'", $exception->getMessage());
        $this->assertEquals('api.key', $exception->getConfigKey());
        $this->assertEquals(['required' => "The api.key configuration is required"], $exception->getValidationErrors());
    }

    public function test_invalid_config_creates_correct_exception()
    {
        $exception = InvalidConfigurationException::invalidConfig('api.timeout', 'must be a positive integer');

        $this->assertEquals("Invalid configuration value for 'api.timeout': must be a positive integer", $exception->getMessage());
        $this->assertEquals('api.timeout', $exception->getConfigKey());
        $this->assertEquals(['invalid' => 'must be a positive integer'], $exception->getValidationErrors());
    }

    public function test_invalid_agent_config_creates_correct_exception()
    {
        $exception = InvalidConfigurationException::invalidAgentConfig('developer', 'missing capabilities');

        $this->assertEquals("Invalid agent configuration for 'developer': missing capabilities", $exception->getMessage());
        $this->assertEquals('agents.developer', $exception->getConfigKey());
        $this->assertEquals(['invalid_agent' => 'missing capabilities'], $exception->getValidationErrors());
    }

    public function test_invalid_team_config_creates_correct_exception()
    {
        $exception = InvalidConfigurationException::invalidTeamConfig('development', 'invalid agent reference');

        $this->assertEquals("Invalid team configuration for 'development': invalid agent reference", $exception->getMessage());
        $this->assertEquals('teams.development', $exception->getConfigKey());
        $this->assertEquals(['invalid_team' => 'invalid agent reference'], $exception->getValidationErrors());
    }

    public function test_invalid_type_creates_correct_exception()
    {
        $exception = InvalidConfigurationException::invalidType('cache.enabled', 'boolean', 'string');

        $this->assertEquals("Invalid type for 'cache.enabled': expected boolean, got string", $exception->getMessage());
        $this->assertEquals('cache.enabled', $exception->getConfigKey());
        $this->assertEquals(['invalid_type' => 'Expected boolean, got string'], $exception->getValidationErrors());
    }

    public function test_invalid_range_creates_correct_exception()
    {
        $exception = InvalidConfigurationException::invalidRange('defaults.temperature', 0, 1, 1.5);

        $this->assertEquals("Invalid value for 'defaults.temperature': must be between 0 and 1, got 1.5", $exception->getMessage());
        $this->assertEquals('defaults.temperature', $exception->getConfigKey());
        $this->assertEquals(['invalid_range' => 'Must be between 0 and 1, got 1.5'], $exception->getValidationErrors());
    }

    public function test_readable_message_includes_validation_errors()
    {
        $exception = new InvalidConfigurationException(
            'Configuration error',
            'test.key',
            ['error1' => 'First error', 'error2' => 'Second error']
        );

        $expected = "Configuration error\nValidation errors:\n- First error\n- Second error\n";
        $this->assertEquals($expected, $exception->getReadableMessage());
    }

    public function test_readable_message_without_validation_errors()
    {
        $exception = new InvalidConfigurationException('Configuration error');
        $this->assertEquals('Configuration error', $exception->getReadableMessage());
    }

    public function test_exception_can_be_created_with_all_constructor_parameters()
    {
        $exception = new InvalidConfigurationException(
            'Test message',
            'test.key',
            ['test' => 'error'],
            123,
            new \Exception('Previous')
        );

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals('test.key', $exception->getConfigKey());
        $this->assertEquals(['test' => 'error'], $exception->getValidationErrors());
        $this->assertEquals(123, $exception->getCode());
        $this->assertInstanceOf(\Exception::class, $exception->getPrevious());
    }

    public function test_exception_handles_empty_constructor_parameters()
    {
        $exception = new InvalidConfigurationException();

        $this->assertEquals('', $exception->getMessage());
        $this->assertNull($exception->getConfigKey());
        $this->assertEquals([], $exception->getValidationErrors());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }
}
