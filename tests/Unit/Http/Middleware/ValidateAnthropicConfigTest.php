<?php

namespace Tests\Unit\Http\Middleware;

use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Ajz\Anthropic\Http\Middleware\ValidateAnthropicConfig;
use Ajz\Anthropic\Exceptions\InvalidConfigurationException;

class ValidateAnthropicConfigTest extends TestCase
{
    protected ValidateAnthropicConfig $middleware;
    protected Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new ValidateAnthropicConfig();
        $this->request = new Request();
    }

    public function test_valid_configuration_passes_validation()
    {
        Config::set('anthropic', $this->getValidConfig());

        $response = $this->middleware->handle($this->request, function ($request) {
            return response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_missing_api_key_throws_exception()
    {
        $config = $this->getValidConfig();
        unset($config['api']['key']);
        Config::set('anthropic', $config);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Invalid configuration value for 'api.key'");

        $this->middleware->handle($this->request, function () {});
    }

    public function test_invalid_api_url_throws_exception()
    {
        $config = $this->getValidConfig();
        $config['api']['base_url'] = 'invalid-url';
        Config::set('anthropic', $config);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Invalid configuration value for 'api.base_url'");

        $this->middleware->handle($this->request, function () {});
    }

    public function test_invalid_cache_configuration_throws_exception()
    {
        $config = $this->getValidConfig();
        $config['cache']['ttl'] = -1;
        Config::set('anthropic', $config);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Invalid configuration value for 'cache.ttl'");

        $this->middleware->handle($this->request, function () {});
    }

    public function test_invalid_agent_configuration_throws_exception()
    {
        $config = $this->getValidConfig();
        $config['agents'] = 'not-an-array';
        Config::set('anthropic', $config);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Invalid type for 'agents': expected array");

        $this->middleware->handle($this->request, function () {});
    }

    public function test_invalid_agent_class_throws_exception()
    {
        $config = $this->getValidConfig();
        $config['agents']['developer']['class'] = 'NonExistentClass';
        Config::set('anthropic', $config);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Invalid agent configuration for 'developer'");

        $this->middleware->handle($this->request, function () {});
    }

    public function test_invalid_team_configuration_throws_exception()
    {
        $config = $this->getValidConfig();
        $config['teams'] = 'not-an-array';
        Config::set('anthropic', $config);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Invalid type for 'teams': expected array");

        $this->middleware->handle($this->request, function () {});
    }

    public function test_invalid_team_agents_reference_throws_exception()
    {
        $config = $this->getValidConfig();
        $config['teams']['development']['agents'][] = 'non_existent_agent';
        Config::set('anthropic', $config);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Referenced agent non_existent_agent does not exist");

        $this->middleware->handle($this->request, function () {});
    }

    public function test_invalid_temperature_range_throws_exception()
    {
        $config = $this->getValidConfig();
        $config['defaults']['temperature'] = 1.5;
        Config::set('anthropic', $config);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Invalid configuration value for 'defaults.temperature'");

        $this->middleware->handle($this->request, function () {});
    }

    protected function getValidConfig(): array
    {
        return [
            'api' => [
                'key' => 'test-key',
                'base_url' => 'https://api.anthropic.com/v1',
                'version' => '2024-01-01',
                'timeout' => 30,
                'retry' => [
                    'times' => 3,
                    'sleep' => 100,
                ],
            ],
            'rate_limiting' => [
                'enabled' => true,
                'max_requests' => 60,
                'decay_minutes' => 1,
                'cache_driver' => 'redis',
            ],
            'cache' => [
                'enabled' => true,
                'ttl' => 3600,
                'prefix' => 'anthropic:',
                'store' => 'redis',
                'tags_enabled' => true,
            ],
            'defaults' => [
                'model' => 'claude-3-5-sonnet-20241022',
                'max_tokens' => 1024,
                'temperature' => 0.7,
                'top_p' => 1.0,
            ],
            'logging' => [
                'enabled' => true,
                'channel' => 'anthropic',
                'level' => 'info',
                'separate_files' => true,
            ],
            'validation' => [
                'max_prompt_length' => 4000,
                'max_context_length' => 8000,
                'max_file_size' => 1024 * 1024,
            ],
            'agents' => [
                'developer' => [
                    'class' => \Ajz\Anthropic\Agency\AiAgents\Specialized\DeveloperAgent::class,
                    'capabilities' => ['code_generation'],
                ],
            ],
            'teams' => [
                'development' => [
                    'class' => \Ajz\Anthropic\Agency\Teams\DevelopmentTeam::class,
                    'agents' => ['developer'],
                    'workflow' => 'sequential',
                ],
            ],
        ];
    }
}
