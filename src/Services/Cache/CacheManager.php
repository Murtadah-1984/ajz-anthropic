<?php

namespace Ajz\Anthropic\Services\Cache;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class CacheManager
{
    /**
     * Cache service instance.
     *
     * @var CacheService
     */
    protected CacheService $cache;

    /**
     * Cache tag groups.
     *
     * @var array
     */
    protected array $tagGroups = [
        'agents' => ['agents', 'sessions', 'messages'],
        'sessions' => ['sessions', 'messages', 'participants'],
        'users' => ['users', 'sessions', 'permissions'],
        'organizations' => ['organizations', 'teams', 'members'],
        'teams' => ['teams', 'members', 'permissions'],
        'knowledge' => ['knowledge', 'embeddings', 'vectors'],
    ];

    /**
     * Cache key patterns.
     *
     * @var array
     */
    protected array $keyPatterns = [
        'agent' => 'agent:{id}',
        'agent_config' => 'agent:{id}:config',
        'agent_state' => 'agent:{id}:state',
        'session' => 'session:{id}',
        'session_messages' => 'session:{id}:messages',
        'session_participants' => 'session:{id}:participants',
        'user' => 'user:{id}',
        'user_permissions' => 'user:{id}:permissions',
        'organization' => 'org:{id}',
        'organization_members' => 'org:{id}:members',
        'team' => 'team:{id}',
        'team_members' => 'team:{id}:members',
        'knowledge_base' => 'kb:{id}',
        'knowledge_embeddings' => 'kb:{id}:embeddings',
    ];

    /**
     * Create a new cache manager instance.
     *
     * @param CacheService $cache
     */
    public function __construct(CacheService $cache)
    {
        $this->cache = $cache;
        $this->registerEventListeners();
    }

    /**
     * Get a cached item with automatic tags.
     *
     * @param string $pattern
     * @param array $params
     * @param mixed $default
     * @return mixed
     */
    public function get(string $pattern, array $params, mixed $default = null): mixed
    {
        $key = $this->buildKey($pattern, $params);
        $tags = $this->getTagsForPattern($pattern, $params);

        return $this->cache->tags($tags)->get($key, $default);
    }

    /**
     * Store an item in the cache with automatic tags.
     *
     * @param string $pattern
     * @param array $params
     * @param mixed $value
     * @param int|\DateTimeInterface|\DateInterval|null $ttl
     * @return bool
     */
    public function put(string $pattern, array $params, mixed $value, mixed $ttl = null): bool
    {
        $key = $this->buildKey($pattern, $params);
        $tags = $this->getTagsForPattern($pattern, $params);

        return $this->cache->tags($tags)->put($key, $value, $ttl);
    }

    /**
     * Remember a value in cache with automatic tags.
     *
     * @param string $pattern
     * @param array $params
     * @param int|\DateTimeInterface|\DateInterval|null $ttl
     * @param callable $callback
     * @return mixed
     */
    public function remember(string $pattern, array $params, mixed $ttl, callable $callback): mixed
    {
        $key = $this->buildKey($pattern, $params);
        $tags = $this->getTagsForPattern($pattern, $params);

        return $this->cache->tags($tags)->remember($key, $ttl, $callback);
    }

    /**
     * Invalidate cache for specific tags.
     *
     * @param array|string $tags
     * @return bool
     */
    public function invalidate(array|string $tags): bool
    {
        $tags = is_array($tags) ? $tags : [$tags];
        $allTags = $this->expandTagGroups($tags);

        return $this->cache->flushTags($allTags);
    }

    /**
     * Invalidate cache for a specific entity.
     *
     * @param string $type
     * @param string|int $id
     * @return bool
     */
    public function invalidateEntity(string $type, string|int $id): bool
    {
        $tags = $this->getEntityTags($type, $id);
        return $this->invalidate($tags);
    }

    /**
     * Build a cache key from pattern and parameters.
     *
     * @param string $pattern
     * @param array $params
     * @return string
     */
    protected function buildKey(string $pattern, array $params): string
    {
        if (!isset($this->keyPatterns[$pattern])) {
            throw new \InvalidArgumentException("Unknown key pattern: {$pattern}");
        }

        $key = $this->keyPatterns[$pattern];
        foreach ($params as $name => $value) {
            $key = str_replace(":{$name}", $value, $key);
        }

        return $key;
    }

    /**
     * Get tags for a specific pattern and parameters.
     *
     * @param string $pattern
     * @param array $params
     * @return array
     */
    protected function getTagsForPattern(string $pattern, array $params): array
    {
        $tags = [];

        // Add pattern-specific tags
        switch ($pattern) {
            case 'agent':
            case 'agent_config':
            case 'agent_state':
                $tags = array_merge($tags, [
                    'agent',
                    "agent:{$params['id']}",
                ]);
                break;

            case 'session':
            case 'session_messages':
            case 'session_participants':
                $tags = array_merge($tags, [
                    'session',
                    "session:{$params['id']}",
                ]);
                break;

            case 'user':
            case 'user_permissions':
                $tags = array_merge($tags, [
                    'user',
                    "user:{$params['id']}",
                ]);
                break;

            case 'organization':
            case 'organization_members':
                $tags = array_merge($tags, [
                    'organization',
                    "org:{$params['id']}",
                ]);
                break;

            case 'team':
            case 'team_members':
                $tags = array_merge($tags, [
                    'team',
                    "team:{$params['id']}",
                ]);
                break;

            case 'knowledge_base':
            case 'knowledge_embeddings':
                $tags = array_merge($tags, [
                    'knowledge',
                    "kb:{$params['id']}",
                ]);
                break;
        }

        return $tags;
    }

    /**
     * Get tags for a specific entity.
     *
     * @param string $type
     * @param string|int $id
     * @return array
     */
    protected function getEntityTags(string $type, string|int $id): array
    {
        return match ($type) {
            'agent' => ['agent', "agent:{$id}"],
            'session' => ['session', "session:{$id}"],
            'user' => ['user', "user:{$id}"],
            'organization' => ['organization', "org:{$id}"],
            'team' => ['team', "team:{$id}"],
            'knowledge_base' => ['knowledge', "kb:{$id}"],
            default => throw new \InvalidArgumentException("Unknown entity type: {$type}"),
        };
    }

    /**
     * Expand tag groups to include all related tags.
     *
     * @param array $tags
     * @return array
     */
    protected function expandTagGroups(array $tags): array
    {
        $expanded = [];

        foreach ($tags as $tag) {
            $expanded[] = $tag;
            if (isset($this->tagGroups[$tag])) {
                $expanded = array_merge($expanded, $this->tagGroups[$tag]);
            }
        }

        return array_unique($expanded);
    }

    /**
     * Register event listeners for cache invalidation.
     *
     * @return void
     */
    protected function registerEventListeners(): void
    {
        // Agent events
        Event::listen('agent.created', fn ($agent) => $this->invalidateEntity('agent', $agent->id));
        Event::listen('agent.updated', fn ($agent) => $this->invalidateEntity('agent', $agent->id));
        Event::listen('agent.deleted', fn ($agent) => $this->invalidateEntity('agent', $agent->id));

        // Session events
        Event::listen('session.created', fn ($session) => $this->invalidateEntity('session', $session->id));
        Event::listen('session.updated', fn ($session) => $this->invalidateEntity('session', $session->id));
        Event::listen('session.deleted', fn ($session) => $this->invalidateEntity('session', $session->id));

        // User events
        Event::listen('user.created', fn ($user) => $this->invalidateEntity('user', $user->id));
        Event::listen('user.updated', fn ($user) => $this->invalidateEntity('user', $user->id));
        Event::listen('user.deleted', fn ($user) => $this->invalidateEntity('user', $user->id));

        // Organization events
        Event::listen('organization.created', fn ($org) => $this->invalidateEntity('organization', $org->id));
        Event::listen('organization.updated', fn ($org) => $this->invalidateEntity('organization', $org->id));
        Event::listen('organization.deleted', fn ($org) => $this->invalidateEntity('organization', $org->id));

        // Team events
        Event::listen('team.created', fn ($team) => $this->invalidateEntity('team', $team->id));
        Event::listen('team.updated', fn ($team) => $this->invalidateEntity('team', $team->id));
        Event::listen('team.deleted', fn ($team) => $this->invalidateEntity('team', $team->id));

        // Knowledge base events
        Event::listen('knowledge.created', fn ($kb) => $this->invalidateEntity('knowledge_base', $kb->id));
        Event::listen('knowledge.updated', fn ($kb) => $this->invalidateEntity('knowledge_base', $kb->id));
        Event::listen('knowledge.deleted', fn ($kb) => $this->invalidateEntity('knowledge_base', $kb->id));
    }
}
