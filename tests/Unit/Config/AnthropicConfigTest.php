<?php

namespace Tests\Unit\Config;

use Tests\TestCase;
use Illuminate\Support\Facades\Config;

class AnthropicConfigTest extends TestCase
{
    /**
     * Test API configuration loading
     */
    public function test_api_configuration_is_properly_loaded()
    {
        $this->assertNotNull(config('anthropic.api.key'));
        $this->assertEquals('https://api.anthropic.com/v1', config('anthropic.api.base_url'));
        $this->assertEquals('2024-01-01', config('anthropic.api.version'));
        $this->assertEquals(30, config('anthropic.api.timeout'));
        $this->assertEquals(3, config('anthropic.api.retry.times'));
    }

    /**
     * Test rate limiting configuration
     */
    public function test_rate_limiting_configuration_is_properly_loaded()
    {
        $this->assertTrue(config('anthropic.rate_limiting.enabled'));
        $this->assertEquals(60, config('anthropic.rate_limiting.max_requests'));
        $this->assertEquals(1, config('anthropic.rate_limiting.decay_minutes'));
        $this->assertEquals('redis', config('anthropic.rate_limiting.cache_driver'));
    }

    /**
     * Test cache configuration
     */
    public function test_cache_configuration_is_properly_loaded()
    {
        $this->assertTrue(config('anthropic.cache.enabled'));
        $this->assertEquals(3600, config('anthropic.cache.ttl'));
        $this->assertEquals('anthropic:', config('anthropic.cache.prefix'));
        $this->assertEquals('redis', config('anthropic.cache.store'));
        $this->assertTrue(config('anthropic.cache.tags_enabled'));
    }

    /**
     * Test default assistant configuration
     */
    public function test_default_assistant_configuration_is_properly_loaded()
    {
        $this->assertEquals('claude-3-5-sonnet-20241022', config('anthropic.defaults.model'));
        $this->assertEquals(1024, config('anthropic.defaults.max_tokens'));
        $this->assertEquals(0.7, config('anthropic.defaults.temperature'));
        $this->assertEquals(1.0, config('anthropic.defaults.top_p'));
        $this->assertEquals(60, config('anthropic.defaults.timeout'));
    }

    /**
     * Test logging configuration
     */
    public function test_logging_configuration_is_properly_loaded()
    {
        $this->assertTrue(config('anthropic.logging.enabled'));
        $this->assertEquals('anthropic', config('anthropic.logging.channel'));
        $this->assertEquals('info', config('anthropic.logging.level'));
        $this->assertTrue(config('anthropic.logging.separate_files'));
    }

    /**
     * Test agent configuration
     */
    public function test_agent_configuration_is_properly_loaded()
    {
        $this->assertArrayHasKey('developer', config('anthropic.agents'));
        $this->assertArrayHasKey('architect', config('anthropic.agents'));
        $this->assertArrayHasKey('security', config('anthropic.agents'));

        $developer = config('anthropic.agents.developer');
        $this->assertEquals(\Ajz\Anthropic\Agency\AiAgents\Specialized\DeveloperAgent::class, $developer['class']);
        $this->assertContains('code_generation', $developer['capabilities']);
        $this->assertEquals(2048, $developer['max_tokens']);
    }

    /**
     * Test team configuration
     */
    public function test_team_configuration_is_properly_loaded()
    {
        $this->assertArrayHasKey('development', config('anthropic.teams'));

        $team = config('anthropic.teams.development');
        $this->assertEquals(\Ajz\Anthropic\Agency\Teams\DevelopmentTeam::class, $team['class']);
        $this->assertContains('developer', $team['agents']);
        $this->assertEquals('sequential', $team['workflow']);
        $this->assertEquals(5, $team['max_rounds']);
    }

    /**
     * Test validation rules configuration
     */
    public function test_validation_rules_are_properly_loaded()
    {
        $this->assertEquals(4000, config('anthropic.validation.max_prompt_length'));
        $this->assertEquals(8000, config('anthropic.validation.max_context_length'));
        $this->assertContains('application/json', config('anthropic.validation.allowed_mime_types'));
        $this->assertEquals(1024 * 1024, config('anthropic.validation.max_file_size'));
    }

    /**
     * Test configuration values can be overridden
     */
    public function test_configuration_values_can_be_overridden()
    {
        // Override a config value
        Config::set('anthropic.api.timeout', 60);

        // Assert the new value is used
        $this->assertEquals(60, config('anthropic.api.timeout'));
    }

    /**
     * Test required configuration values are present
     */
    public function test_required_configuration_values_are_present()
    {
        $requiredKeys = [
            'api.key',
            'api.base_url',
            'api.version',
            'defaults.model',
            'cache.enabled',
            'logging.enabled',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertNotNull(config("anthropic.{$key}"), "Required config key 'anthropic.{$key}' is missing");
        }
    }

    /**
     * Test configuration type validation
     */
    public function test_configuration_types_are_correct()
    {
        $this->assertIsString(config('anthropic.api.key'));
        $this->assertIsString(config('anthropic.api.base_url'));
        $this->assertIsInt(config('anthropic.api.timeout'));
        $this->assertIsBool(config('anthropic.cache.enabled'));
        $this->assertIsArray(config('anthropic.agents'));
        $this->assertIsArray(config('anthropic.validation.allowed_mime_types'));
    }
}
