<?php

namespace Ajz\Anthropic\Services\Cache;

use Illuminate\Cache\TaggedCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Psr\SimpleCache\InvalidArgumentException;

class CacheService
{
    /**
     * Default cache duration in minutes.
     *
     * @var int
     */
    protected const DEFAULT_DURATION = 60;

    /**
     * Cache tags prefix.
     *
     * @var string
     */
    protected const TAG_PREFIX = 'anthropic_';

    /**
     * Cache store instance.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * Current cache tags.
     *
     * @var array|null
     */
    protected ?array $tags = null;

    /**
     * Create a new cache service instance.
     *
     * @param string|null $store
     */
    public function __construct(?string $store = null)
    {
        $this->cache = Cache::store($store ?? config('anthropic.cache.store'));
    }

    /**
     * Set cache tags.
     *
     * @param array|string $tags
     * @return self
     */
    public function tags(array|string $tags): self
    {
        $tags = is_array($tags) ? $tags : [$tags];
        $this->tags = array_map(fn ($tag) => self::TAG_PREFIX . $tag, $tags);
        return $this;
    }

    /**
     * Get an item from the cache.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $cache = $this->tags ? $this->cache->tags($this->tags) : $this->cache;
            return $cache->get($this->normalizeKey($key), $default);
        } catch (InvalidArgumentException $e) {
            return $default;
        } finally {
            $this->tags = null;
        }
    }

    /**
     * Store an item in the cache.
     *
     * @param string $key
     * @param mixed $value
     * @param int|\DateTimeInterface|\DateInterval|null $ttl
     * @return bool
     */
    public function put(string $key, mixed $value, mixed $ttl = null): bool
    {
        try {
            $cache = $this->tags ? $this->cache->tags($this->tags) : $this->cache;
            $ttl = $this->normalizeTtl($ttl);
            return $cache->put($this->normalizeKey($key), $value, $ttl);
        } catch (InvalidArgumentException $e) {
            return false;
        } finally {
            $this->tags = null;
        }
    }

    /**
     * Store an item in the cache if it doesn't exist.
     *
     * @param string $key
     * @param mixed $value
     * @param int|\DateTimeInterface|\DateInterval|null $ttl
     * @return bool
     */
    public function add(string $key, mixed $value, mixed $ttl = null): bool
    {
        try {
            $cache = $this->tags ? $this->cache->tags($this->tags) : $this->cache;
            $ttl = $this->normalizeTtl($ttl);
            return $cache->add($this->normalizeKey($key), $value, $ttl);
        } catch (InvalidArgumentException $e) {
            return false;
        } finally {
            $this->tags = null;
        }
    }

    /**
     * Get and store a value if it doesn't exist.
     *
     * @param string $key
     * @param int|\DateTimeInterface|\DateInterval|null $ttl
     * @param callable $callback
     * @return mixed
     */
    public function remember(string $key, mixed $ttl, callable $callback): mixed
    {
        try {
            $cache = $this->tags ? $this->cache->tags($this->tags) : $this->cache;
            $ttl = $this->normalizeTtl($ttl);
            return $cache->remember($this->normalizeKey($key), $ttl, $callback);
        } catch (InvalidArgumentException $e) {
            return $callback();
        } finally {
            $this->tags = null;
        }
    }

    /**
     * Remove an item from the cache.
     *
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool
    {
        try {
            $cache = $this->tags ? $this->cache->tags($this->tags) : $this->cache;
            return $cache->forget($this->normalizeKey($key));
        } catch (InvalidArgumentException $e) {
            return false;
        } finally {
            $this->tags = null;
        }
    }

    /**
     * Remove all items with the given tags.
     *
     * @param array|string $tags
     * @return bool
     */
    public function flushTags(array|string $tags): bool
    {
        try {
            $tags = is_array($tags) ? $tags : [$tags];
            $tags = array_map(fn ($tag) => self::TAG_PREFIX . $tag, $tags);
            return $this->cache->tags($tags)->flush();
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Increment a cached value.
     *
     * @param string $key
     * @param int $value
     * @return int|bool
     */
    public function increment(string $key, int $value = 1): int|bool
    {
        try {
            $cache = $this->tags ? $this->cache->tags($this->tags) : $this->cache;
            return $cache->increment($this->normalizeKey($key), $value);
        } catch (InvalidArgumentException $e) {
            return false;
        } finally {
            $this->tags = null;
        }
    }

    /**
     * Decrement a cached value.
     *
     * @param string $key
     * @param int $value
     * @return int|bool
     */
    public function decrement(string $key, int $value = 1): int|bool
    {
        try {
            $cache = $this->tags ? $this->cache->tags($this->tags) : $this->cache;
            return $cache->decrement($this->normalizeKey($key), $value);
        } catch (InvalidArgumentException $e) {
            return false;
        } finally {
            $this->tags = null;
        }
    }

    /**
     * Get multiple items from the cache.
     *
     * @param array $keys
     * @param mixed $default
     * @return array
     */
    public function many(array $keys, mixed $default = null): array
    {
        try {
            $cache = $this->tags ? $this->cache->tags($this->tags) : $this->cache;
            $keys = array_map(fn ($key) => $this->normalizeKey($key), $keys);
            return $cache->many($keys);
        } catch (InvalidArgumentException $e) {
            return array_fill_keys($keys, $default);
        } finally {
            $this->tags = null;
        }
    }

    /**
     * Store multiple items in the cache.
     *
     * @param array $values
     * @param int|\DateTimeInterface|\DateInterval|null $ttl
     * @return bool
     */
    public function putMany(array $values, mixed $ttl = null): bool
    {
        try {
            $cache = $this->tags ? $this->cache->tags($this->tags) : $this->cache;
            $ttl = $this->normalizeTtl($ttl);
            $values = collect($values)->mapWithKeys(
                fn ($value, $key) => [$this->normalizeKey($key) => $value]
            )->all();
            return $cache->putMany($values, $ttl);
        } catch (InvalidArgumentException $e) {
            return false;
        } finally {
            $this->tags = null;
        }
    }

    /**
     * Get the cache store instance.
     *
     * @return \Illuminate\Contracts\Cache\Repository
     */
    public function getStore()
    {
        return $this->cache;
    }

    /**
     * Get the tagged cache instance.
     *
     * @return TaggedCache|null
     */
    public function getTaggedCache(): ?TaggedCache
    {
        return $this->tags ? $this->cache->tags($this->tags) : null;
    }

    /**
     * Normalize cache key.
     *
     * @param string $key
     * @return string
     */
    protected function normalizeKey(string $key): string
    {
        return config('anthropic.cache.prefix', 'anthropic_') . $key;
    }

    /**
     * Normalize TTL value.
     *
     * @param mixed $ttl
     * @return \DateInterval|int|null
     */
    protected function normalizeTtl(mixed $ttl): \DateInterval|int|null
    {
        if ($ttl === null) {
            return self::DEFAULT_DURATION;
        }

        if (is_numeric($ttl)) {
            return (int) $ttl;
        }

        if ($ttl instanceof \DateTimeInterface) {
            return $ttl->diff(now());
        }

        if ($ttl instanceof \DateInterval) {
            return $ttl;
        }

        return self::DEFAULT_DURATION;
    }
}
