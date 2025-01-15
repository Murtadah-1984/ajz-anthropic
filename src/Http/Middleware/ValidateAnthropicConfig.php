<?php

namespace Ajz\Anthropic\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ajz\Anthropic\Exceptions\InvalidConfigurationException;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ValidateAnthropicConfig
{
    /**
     * Required configuration keys and their validation rules
     *
     * @var array
     */
    protected $requiredConfig = [
        'api.key' => ['required', 'string'],
        'api.base_url' => ['required', 'url'],
        'api.version' => ['required', 'string'],
        'api.timeout' => ['required', 'integer', 'min:1'],
        'api.retry.times' => ['required', 'integer', 'min:0'],
        'api.retry.sleep' => ['required', 'integer', 'min:0'],

        'rate_limiting.enabled' => ['required', 'boolean'],
        'rate_limiting.max_requests' => ['required', 'integer', 'min:1'],
        'rate_limiting.decay_minutes' => ['required', 'integer', 'min:1'],
        'rate_limiting.cache_driver' => ['required', 'string'],

        'cache.enabled' => ['required', 'boolean'],
        'cache.ttl' => ['required', 'integer', 'min:0'],
        'cache.prefix' => ['required', 'string'],
        'cache.store' => ['required', 'string'],

        'defaults.model' => ['required', 'string'],
        'defaults.max_tokens' => ['required', 'integer', 'min:1'],
        'defaults.temperature' => ['required', 'numeric', 'min:0', 'max:1'],
        'defaults.top_p' => ['required', 'numeric', 'min:0', 'max:1'],

        'logging.enabled' => ['required', 'boolean'],
        'logging.channel' => ['required', 'string'],
        'logging.level' => ['required', 'string', 'in:debug,info,notice,warning,error,critical,alert,emergency'],

        'validation.max_prompt_length' => ['required', 'integer', 'min:1'],
        'validation.max_context_length' => ['required', 'integer', 'min:1'],
        'validation.max_file_size' => ['required', 'integer', 'min:1'],
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws \Ajz\Anthropic\Exceptions\InvalidConfigurationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->validateConfiguration();
        return $next($request);
    }

    /**
     * Validate the Anthropic configuration.
     *
     * @throws \Ajz\Anthropic\Exceptions\InvalidConfigurationException
     */
    protected function validateConfiguration(): void
    {
        $config = [];

        // Gather all configuration values
        foreach ($this->requiredConfig as $key => $rules) {
            $config[$key] = config("anthropic.{$key}");
        }

        // Validate configuration values
        $validator = Validator::make($config, $this->requiredConfig);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            $firstError = reset($errors);
            $firstKey = array_key_first($validator->failed());

            throw InvalidConfigurationException::invalidConfig(
                $firstKey,
                $firstError
            );
        }

        // Additional validation for specific configurations
        $this->validateAgentConfiguration();
        $this->validateTeamConfiguration();
    }

    /**
     * Validate agent configuration.
     *
     * @throws \Ajz\Anthropic\Exceptions\InvalidConfigurationException
     */
    protected function validateAgentConfiguration(): void
    {
        $agents = config('anthropic.agents');

        if (!is_array($agents)) {
            throw InvalidConfigurationException::invalidType('agents', 'array', gettype($agents));
        }

        foreach ($agents as $name => $config) {
            if (!isset($config['class']) || !class_exists($config['class'])) {
                throw InvalidConfigurationException::invalidAgentConfig($name, 'Invalid or non-existent agent class');
            }

            if (!isset($config['capabilities']) || !is_array($config['capabilities'])) {
                throw InvalidConfigurationException::invalidAgentConfig($name, 'Missing or invalid capabilities configuration');
            }
        }
    }

    /**
     * Validate team configuration.
     *
     * @throws \Ajz\Anthropic\Exceptions\InvalidConfigurationException
     */
    protected function validateTeamConfiguration(): void
    {
        $teams = config('anthropic.teams');

        if (!is_array($teams)) {
            throw InvalidConfigurationException::invalidType('teams', 'array', gettype($teams));
        }

        foreach ($teams as $name => $config) {
            if (!isset($config['class']) || !class_exists($config['class'])) {
                throw InvalidConfigurationException::invalidTeamConfig($name, 'Invalid or non-existent team class');
            }

            if (!isset($config['agents']) || !is_array($config['agents'])) {
                throw InvalidConfigurationException::invalidTeamConfig($name, 'Missing or invalid agents configuration');
            }

            // Validate that all referenced agents exist
            foreach ($config['agents'] as $agentName) {
                if (!isset(config('anthropic.agents')[$agentName])) {
                    throw InvalidConfigurationException::invalidTeamConfig($name, "Referenced agent {$agentName} does not exist");
                }
            }
        }
    }
}
