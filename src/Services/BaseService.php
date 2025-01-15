<?php

namespace Ajz\Anthropic\Services;

use Illuminate\Support\Collection;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Validation\Factory as Validator;
use Illuminate\Contracts\Config\Repository as Config;

abstract class BaseService
{
    /**
     * The event dispatcher instance.
     *
     * @var Dispatcher
     */
    protected Dispatcher $events;

    /**
     * The cache repository instance.
     *
     * @var Cache
     */
    protected Cache $cache;

    /**
     * The validator instance.
     *
     * @var Validator
     */
    protected Validator $validator;

    /**
     * The config repository instance.
     *
     * @var Config
     */
    protected Config $config;

    /**
     * Create a new service instance.
     *
     * @param Dispatcher $events
     * @param Cache $cache
     * @param Validator $validator
     * @param Config $config
     */
    public function __construct(
        Dispatcher $events,
        Cache $cache,
        Validator $validator,
        Config $config
    ) {
        $this->events = $events;
        $this->cache = $cache;
        $this->validator = $validator;
        $this->config = $config;
    }

    /**
     * Validate data against rules.
     *
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @return bool
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validate(array $data, array $rules, array $messages = []): bool
    {
        $validator = $this->validator->make($data, $rules, $messages);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        return true;
    }

    /**
     * Cache a value.
     *
     * @param string $key
     * @param mixed $value
     * @param int|\DateTimeInterface|\DateInterval|null $ttl
     * @return bool
     */
    protected function cache(string $key, mixed $value, mixed $ttl = null): bool
    {
        return $this->cache->put($key, $value, $ttl);
    }

    /**
     * Get a cached value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getCached(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($key, $default);
    }

    /**
     * Remove a cached value.
     *
     * @param string $key
     * @return bool
     */
    protected function forgetCached(string $key): bool
    {
        return $this->cache->forget($key);
    }

    /**
     * Fire an event.
     *
     * @param string|object $event
     * @param mixed $payload
     * @param bool $halt
     * @return mixed
     */
    protected function fireEvent(string|object $event, mixed $payload = [], bool $halt = false): mixed
    {
        return $this->events->dispatch($event, $payload, $halt);
    }

    /**
     * Get a configuration value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key, $default);
    }

    /**
     * Set a configuration value.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    protected function setConfig(string $key, mixed $value): void
    {
        $this->config->set($key, $value);
    }

    /**
     * Get multiple configuration values.
     *
     * @param array $keys
     * @return array
     */
    protected function getConfigs(array $keys): array
    {
        $configs = [];
        foreach ($keys as $key => $default) {
            $configs[$key] = $this->config($key, $default);
        }
        return $configs;
    }

    /**
     * Get the event dispatcher instance.
     *
     * @return Dispatcher
     */
    public function getEventDispatcher(): Dispatcher
    {
        return $this->events;
    }

    /**
     * Get the cache repository instance.
     *
     * @return Cache
     */
    public function getCacheRepository(): Cache
    {
        return $this->cache;
    }

    /**
     * Get the validator instance.
     *
     * @return Validator
     */
    public function getValidator(): Validator
    {
        return $this->validator;
    }

    /**
     * Get the config repository instance.
     *
     * @return Config
     */
    public function getConfigRepository(): Config
    {
        return $this->config;
    }

    /**
     * Handle dynamic method calls.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call(string $method, array $parameters)
    {
        throw new \BadMethodCallException(sprintf(
            'Method %s::%s does not exist.', static::class, $method
        ));
    }
}
