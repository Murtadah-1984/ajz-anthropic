<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache store that will be used by the
    | package. The store should be configured in your Laravel application's
    | cache configuration file.
    |
    */
    'store' => env('ANTHROPIC_CACHE_STORE', env('CACHE_DRIVER', 'file')),

    /*
    |--------------------------------------------------------------------------
    | Cache Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing a RAM based store such as APC or Memcached, there might
    | be other applications utilizing the same cache. So, we'll specify a
    | value to get prefixed to all our keys so we can avoid collisions.
    |
    */
    'prefix' => env('ANTHROPIC_CACHE_PREFIX', 'anthropic_'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL Settings
    |--------------------------------------------------------------------------
    |
    | Here you may configure the default time-to-live (in minutes) for different
    | types of cached data. These values can be overridden when storing items.
    |
    */
    'ttl' => [
        'default' => env('ANTHROPIC_CACHE_TTL', 60),
        'agents' => env('ANTHROPIC_CACHE_AGENTS_TTL', 120),
        'sessions' => env('ANTHROPIC_CACHE_SESSIONS_TTL', 180),
        'users' => env('ANTHROPIC_CACHE_USERS_TTL', 240),
        'organizations' => env('ANTHROPIC_CACHE_ORGANIZATIONS_TTL', 360),
        'teams' => env('ANTHROPIC_CACHE_TEAMS_TTL', 360),
        'knowledge' => env('ANTHROPIC_CACHE_KNOWLEDGE_TTL', 480),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Tags Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure tag groups that will be used for cache invalidation.
    | When an entity is updated, all related tags will be invalidated.
    |
    */
    'tags' => [
        'groups' => [
            'agents' => ['agents', 'sessions', 'messages'],
            'sessions' => ['sessions', 'messages', 'participants'],
            'users' => ['users', 'sessions', 'permissions'],
            'organizations' => ['organizations', 'teams', 'members'],
            'teams' => ['teams', 'members', 'permissions'],
            'knowledge' => ['knowledge', 'embeddings', 'vectors'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Patterns
    |--------------------------------------------------------------------------
    |
    | Define patterns for cache keys to ensure consistency across the application.
    | These patterns will be used by the CacheManager to build cache keys.
    |
    */
    'keys' => [
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Drivers Configuration
    |--------------------------------------------------------------------------
    |
    | Configure specific settings for different cache drivers. These settings
    | will be merged with Laravel's cache configuration.
    |
    */
    'drivers' => [
        'redis' => [
            'connection' => env('ANTHROPIC_REDIS_CONNECTION', 'cache'),
            'lock_connection' => env('ANTHROPIC_REDIS_LOCK_CONNECTION', 'default'),
            'cluster' => false,
            'prefix' => env('ANTHROPIC_REDIS_PREFIX', 'anthropic_cache:'),
        ],

        'memcached' => [
            'persistent_id' => env('ANTHROPIC_MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('ANTHROPIC_MEMCACHED_USERNAME'),
                env('ANTHROPIC_MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('ANTHROPIC_MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('ANTHROPIC_MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Events
    |--------------------------------------------------------------------------
    |
    | Configure which events should trigger cache invalidation. This allows
    | for fine-grained control over cache invalidation strategies.
    |
    */
    'events' => [
        'invalidate' => [
            'agent' => [
                'created', 'updated', 'deleted',
                'state_changed', 'config_updated',
            ],
            'session' => [
                'created', 'updated', 'deleted',
                'participant_added', 'participant_removed',
                'message_added',
            ],
            'user' => [
                'created', 'updated', 'deleted',
                'permissions_updated',
            ],
            'organization' => [
                'created', 'updated', 'deleted',
                'member_added', 'member_removed',
            ],
            'team' => [
                'created', 'updated', 'deleted',
                'member_added', 'member_removed',
            ],
            'knowledge' => [
                'created', 'updated', 'deleted',
                'embeddings_updated', 'vectors_updated',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Monitoring
    |--------------------------------------------------------------------------
    |
    | Configure settings for cache monitoring and debugging. These settings
    | can help track cache usage and identify potential issues.
    |
    */
    'monitoring' => [
        'enabled' => env('ANTHROPIC_CACHE_MONITORING', false),
        'driver' => env('ANTHROPIC_CACHE_MONITORING_DRIVER', 'log'),
        'channels' => ['daily'],
        'metrics' => [
            'hits' => true,
            'misses' => true,
            'keys' => false,
            'memory' => true,
            'size' => true,
        ],
    ],
];
