<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Illuminate\Support\Facades\Config;

class ConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Load the configuration file
        $config = require __DIR__ . '/../../config/anthropic.php';
        Config::set('anthropic', $config);
    }

    public function test_configuration_is_properly_loaded()
    {
        $this->assertNotNull(Config::get('anthropic'));
        $this->assertIsArray(Config::get('anthropic.api'));
        $this->assertIsArray(Config::get('anthropic.rate_limiting'));
        $this->assertIsArray(Config::get('anthropic.cache'));
    }

    public function test_environment_variables_are_properly_mapped()
    {
        // Test API configuration
        $this->assertEquals(env('ANTHROPIC_API_KEY'), Config::get('anthropic.api.key'));
        $this->assertEquals(
            env('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1'),
            Config::get('anthropic.api.base_url')
        );

        // Test rate limiting configuration
        $this->assertEquals(
            env('ANTHROPIC_RATE_LIMITING_ENABLED', true),
            Config::get('anthropic.rate_limiting.enabled')
        );
        $this->assertEquals(
            env('ANTHROPIC_RATE_LIMIT_MAX_REQUESTS', 60),
            Config::get('anthropic.rate_limiting.max_requests')
        );

        // Test cache configuration
        $this->assertEquals(
            env('ANTHROPIC_CACHE_ENABLED', true),
            Config::get('anthropic.cache.enabled')
        );
        $this->assertEquals(
            env('ANTHROPIC_CACHE_TTL', 3600),
            Config::get('anthropic.cache.ttl')
        );
    }

    public function test_rate_limiting_configuration()
    {
        $rateLimiting = Config::get('anthropic.rate_limiting');

        $this->assertArrayHasKey('enabled', $rateLimiting);
        $this->assertArrayHasKey('max_requests', $rateLimiting);
        $this->assertArrayHasKey('decay_minutes', $rateLimiting);
        $this->assertArrayHasKey('cache_driver', $rateLimiting);

        $this->assertIsInt($rateLimiting['max_requests']);
        $this->assertIsInt($rateLimiting['decay_minutes']);
        $this->assertIsString($rateLimiting['cache_driver']);
    }

    public function test_validation_rules_are_enforced()
    {
        $validation = Config::get('anthropic.validation');

        $this->assertArrayHasKey('max_prompt_length', $validation);
        $this->assertArrayHasKey('max_context_length', $validation);
        $this->assertArrayHasKey('allowed_mime_types', $validation);
        $this->assertArrayHasKey('max_file_size', $validation);

        $this->assertIsInt($validation['max_prompt_length']);
        $this->assertIsInt($validation['max_context_length']);
        $this->assertIsArray($validation['allowed_mime_types']);
        $this->assertIsInt($validation['max_file_size']);

        // Test specific validation rules
        $this->assertGreaterThan(0, $validation['max_prompt_length']);
        $this->assertGreaterThan(0, $validation['max_context_length']);
        $this->assertGreaterThan(0, $validation['max_file_size']);
        $this->assertNotEmpty($validation['allowed_mime_types']);
    }
}
