<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection
    |--------------------------------------------------------------------------
    |
    | The default queue connection that will be used by the package. This should
    | be configured in your Laravel application's queue configuration file.
    |
    */
    'default' => env('ANTHROPIC_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'redis')),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Configure connection-specific settings for different queue drivers.
    | These settings will be merged with Laravel's queue configuration.
    |
    */
    'connections' => [
        'redis' => [
            'connection' => env('ANTHROPIC_REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('ANTHROPIC_REDIS_QUEUE', 'anthropic'),
            'retry_after' => env('ANTHROPIC_QUEUE_RETRY_AFTER', 90),
            'block_for' => env('ANTHROPIC_QUEUE_BLOCK_FOR', null),
            'after_commit' => env('ANTHROPIC_QUEUE_AFTER_COMMIT', false),
        ],

        'sqs' => [
            'key' => env('ANTHROPIC_AWS_ACCESS_KEY_ID'),
            'secret' => env('ANTHROPIC_AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('ANTHROPIC_SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('ANTHROPIC_SQS_QUEUE', 'anthropic'),
            'region' => env('ANTHROPIC_AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => env('ANTHROPIC_QUEUE_AFTER_COMMIT', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Names
    |--------------------------------------------------------------------------
    |
    | Define the queue names for different types of jobs. This helps organize
    | jobs into appropriate queues for better processing management.
    |
    */
    'queues' => [
        'default' => env('ANTHROPIC_DEFAULT_QUEUE', 'default'),
        'high' => env('ANTHROPIC_HIGH_PRIORITY_QUEUE', 'high'),
        'low' => env('ANTHROPIC_LOW_PRIORITY_QUEUE', 'low'),
        'agents' => env('ANTHROPIC_AGENTS_QUEUE', 'agents'),
        'sessions' => env('ANTHROPIC_SESSIONS_QUEUE', 'sessions'),
        'knowledge' => env('ANTHROPIC_KNOWLEDGE_QUEUE', 'knowledge'),
        'notifications' => env('ANTHROPIC_NOTIFICATIONS_QUEUE', 'notifications'),
        'monitoring' => env('ANTHROPIC_MONITORING_QUEUE', 'monitoring'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | Configure settings for job batching, including database table and
    | connection settings.
    |
    */
    'batching' => [
        'database' => env('ANTHROPIC_QUEUE_BATCHING_DATABASE', env('DB_CONNECTION', 'mysql')),
        'table' => env('ANTHROPIC_QUEUE_BATCHING_TABLE', 'job_batches'),
        'connection' => env('ANTHROPIC_QUEUE_BATCHING_CONNECTION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Jobs
    |--------------------------------------------------------------------------
    |
    | Configure settings for handling failed jobs, including the database
    | table and connection settings.
    |
    */
    'failed' => [
        'database' => env('ANTHROPIC_QUEUE_FAILED_DATABASE', env('DB_CONNECTION', 'mysql')),
        'table' => env('ANTHROPIC_QUEUE_FAILED_TABLE', 'failed_jobs'),
        'connection' => env('ANTHROPIC_QUEUE_FAILED_CONNECTION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Workers
    |--------------------------------------------------------------------------
    |
    | Configure settings for queue workers, including timeouts, memory limits,
    | and other worker-specific settings.
    |
    */
    'workers' => [
        'default' => [
            'timeout' => env('ANTHROPIC_QUEUE_WORKER_TIMEOUT', 60),
            'tries' => env('ANTHROPIC_QUEUE_WORKER_TRIES', 3),
            'memory' => env('ANTHROPIC_QUEUE_WORKER_MEMORY_LIMIT', 128),
            'sleep' => env('ANTHROPIC_QUEUE_WORKER_SLEEP', 3),
            'maxJobs' => env('ANTHROPIC_QUEUE_WORKER_MAX_JOBS', 0),
            'maxTime' => env('ANTHROPIC_QUEUE_WORKER_MAX_TIME', 0),
            'force' => env('ANTHROPIC_QUEUE_WORKER_FORCE', false),
            'stopWhenEmpty' => env('ANTHROPIC_QUEUE_WORKER_STOP_WHEN_EMPTY', false),
        ],
        'high' => [
            'timeout' => env('ANTHROPIC_HIGH_QUEUE_WORKER_TIMEOUT', 30),
            'tries' => env('ANTHROPIC_HIGH_QUEUE_WORKER_TRIES', 5),
            'memory' => env('ANTHROPIC_HIGH_QUEUE_WORKER_MEMORY_LIMIT', 256),
            'sleep' => env('ANTHROPIC_HIGH_QUEUE_WORKER_SLEEP', 1),
        ],
        'low' => [
            'timeout' => env('ANTHROPIC_LOW_QUEUE_WORKER_TIMEOUT', 120),
            'tries' => env('ANTHROPIC_LOW_QUEUE_WORKER_TRIES', 2),
            'memory' => env('ANTHROPIC_LOW_QUEUE_WORKER_MEMORY_LIMIT', 128),
            'sleep' => env('ANTHROPIC_LOW_QUEUE_WORKER_SLEEP', 5),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Monitoring
    |--------------------------------------------------------------------------
    |
    | Configure settings for queue monitoring, including metrics collection
    | and monitoring endpoints.
    |
    */
    'monitoring' => [
        'enabled' => env('ANTHROPIC_QUEUE_MONITORING', false),
        'driver' => env('ANTHROPIC_QUEUE_MONITORING_DRIVER', 'log'),
        'channels' => ['daily'],
        'metrics' => [
            'jobs' => true,
            'failed' => true,
            'waiting' => true,
            'processing' => true,
            'completed' => true,
            'runtime' => true,
            'memory' => true,
        ],
        'alert_thresholds' => [
            'failed_jobs' => env('ANTHROPIC_QUEUE_ALERT_FAILED_JOBS', 10),
            'waiting_jobs' => env('ANTHROPIC_QUEUE_ALERT_WAITING_JOBS', 100),
            'processing_time' => env('ANTHROPIC_QUEUE_ALERT_PROCESSING_TIME', 300),
            'memory_usage' => env('ANTHROPIC_QUEUE_ALERT_MEMORY_USAGE', 256),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting settings for job processing to prevent
    | overwhelming external services or resources.
    |
    */
    'rate_limiting' => [
        'enabled' => env('ANTHROPIC_QUEUE_RATE_LIMITING', true),
        'driver' => env('ANTHROPIC_QUEUE_RATE_LIMITING_DRIVER', 'redis'),
        'key_prefix' => env('ANTHROPIC_QUEUE_RATE_LIMITING_PREFIX', 'anthropic_queue_limit:'),
        'decay_minutes' => env('ANTHROPIC_QUEUE_RATE_LIMITING_DECAY', 1),
        'max_attempts' => [
            'default' => env('ANTHROPIC_QUEUE_RATE_LIMITING_MAX_ATTEMPTS', 60),
            'high' => env('ANTHROPIC_QUEUE_RATE_LIMITING_HIGH_MAX_ATTEMPTS', 120),
            'low' => env('ANTHROPIC_QUEUE_RATE_LIMITING_LOW_MAX_ATTEMPTS', 30),
        ],
    ],
];
