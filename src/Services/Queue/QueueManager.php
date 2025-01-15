<?php

namespace Ajz\Anthropic\Services\Queue;

use Illuminate\Bus\Batch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue as QueueFacade;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueueManager
{
    /**
     * The queue dispatcher instance.
     *
     * @var Dispatcher
     */
    protected Dispatcher $dispatcher;

    /**
     * The default queue connection.
     *
     * @var Queue
     */
    protected Queue $queue;

    /**
     * Create a new queue manager instance.
     *
     * @param Dispatcher $dispatcher
     */
    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
        $this->queue = QueueFacade::connection(
            config('anthropic.queue.default')
        );
    }

    /**
     * Dispatch a job to its appropriate queue.
     *
     * @param mixed $job
     * @param string|null $queue
     * @return mixed
     */
    public function dispatch(mixed $job, ?string $queue = null): mixed
    {
        $queue = $queue ?? $this->getQueueForJob($job);

        try {
            $result = $this->dispatcher->dispatch(
                $this->setJobQueue($job, $queue)
            );

            $this->logJobDispatched($job, $queue);

            return $result;
        } catch (Throwable $e) {
            $this->logJobError($job, $queue, $e);
            throw $e;
        }
    }

    /**
     * Dispatch a job to be executed after the database transaction commits.
     *
     * @param mixed $job
     * @param string|null $queue
     * @return mixed
     */
    public function dispatchAfterCommit(mixed $job, ?string $queue = null): mixed
    {
        $queue = $queue ?? $this->getQueueForJob($job);

        try {
            $result = $this->dispatcher->dispatchAfterResponse(
                $this->setJobQueue($job, $queue)
            );

            $this->logJobDispatched($job, $queue, true);

            return $result;
        } catch (Throwable $e) {
            $this->logJobError($job, $queue, $e);
            throw $e;
        }
    }

    /**
     * Create a new job batch.
     *
     * @param array $jobs
     * @param string|null $queue
     * @return PendingBatch
     */
    public function batch(array $jobs, ?string $queue = null): PendingBatch
    {
        $queue = $queue ?? config('anthropic.queue.queues.default');

        $batch = Bus::batch([]);

        foreach ($jobs as $job) {
            $batch->add($this->setJobQueue($job, $queue));
        }

        return $batch
            ->then(function (Batch $batch) {
                $this->logBatchCompleted($batch);
            })
            ->catch(function (Batch $batch, Throwable $e) {
                $this->logBatchError($batch, $e);
            })
            ->finally(function (Batch $batch) {
                $this->logBatchFinished($batch);
            });
    }

    /**
     * Get the queue for a specific job.
     *
     * @param mixed $job
     * @return string
     */
    protected function getQueueForJob(mixed $job): string
    {
        if (method_exists($job, 'queue')) {
            return $job->queue();
        }

        if (property_exists($job, 'queue')) {
            return $job->queue;
        }

        if (method_exists($job, 'priority')) {
            return match ($job->priority()) {
                'high' => config('anthropic.queue.queues.high'),
                'low' => config('anthropic.queue.queues.low'),
                default => config('anthropic.queue.queues.default'),
            };
        }

        return $this->getQueueByJobType($job);
    }

    /**
     * Get queue based on job type/namespace.
     *
     * @param mixed $job
     * @return string
     */
    protected function getQueueByJobType(mixed $job): string
    {
        $class = get_class($job);

        return match (true) {
            str_contains($class, 'Agent') => config('anthropic.queue.queues.agents'),
            str_contains($class, 'Session') => config('anthropic.queue.queues.sessions'),
            str_contains($class, 'Knowledge') => config('anthropic.queue.queues.knowledge'),
            str_contains($class, 'Notification') => config('anthropic.queue.queues.notifications'),
            str_contains($class, 'Monitor') => config('anthropic.queue.queues.monitoring'),
            default => config('anthropic.queue.queues.default'),
        };
    }

    /**
     * Set the queue for a job.
     *
     * @param mixed $job
     * @param string $queue
     * @return mixed
     */
    protected function setJobQueue(mixed $job, string $queue): mixed
    {
        if (method_exists($job, 'onQueue')) {
            $job->onQueue($queue);
        } elseif (property_exists($job, 'queue')) {
            $job->queue = $queue;
        }

        return $job;
    }

    /**
     * Log job dispatch.
     *
     * @param mixed $job
     * @param string $queue
     * @param bool $afterCommit
     * @return void
     */
    protected function logJobDispatched(mixed $job, string $queue, bool $afterCommit = false): void
    {
        if (!config('anthropic.queue.monitoring.enabled')) {
            return;
        }

        Log::channel(config('anthropic.queue.monitoring.driver', 'daily'))
            ->info('Job dispatched', [
                'job' => get_class($job),
                'queue' => $queue,
                'after_commit' => $afterCommit,
                'memory' => memory_get_usage(true),
            ]);
    }

    /**
     * Log job error.
     *
     * @param mixed $job
     * @param string $queue
     * @param Throwable $exception
     * @return void
     */
    protected function logJobError(mixed $job, string $queue, Throwable $exception): void
    {
        Log::error('Job dispatch failed', [
            'job' => get_class($job),
            'queue' => $queue,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Log batch completion.
     *
     * @param Batch $batch
     * @return void
     */
    protected function logBatchCompleted(Batch $batch): void
    {
        if (!config('anthropic.queue.monitoring.enabled')) {
            return;
        }

        Log::channel(config('anthropic.queue.monitoring.driver', 'daily'))
            ->info('Batch completed', [
                'id' => $batch->id,
                'name' => $batch->name,
                'total_jobs' => $batch->totalJobs,
                'processed_jobs' => $batch->processedJobs(),
                'failed_jobs' => $batch->failedJobs,
                'runtime' => $batch->finishedAt->diffInSeconds($batch->createdAt),
            ]);
    }

    /**
     * Log batch error.
     *
     * @param Batch $batch
     * @param Throwable $exception
     * @return void
     */
    protected function logBatchError(Batch $batch, Throwable $exception): void
    {
        Log::error('Batch failed', [
            'id' => $batch->id,
            'name' => $batch->name,
            'total_jobs' => $batch->totalJobs,
            'processed_jobs' => $batch->processedJobs(),
            'failed_jobs' => $batch->failedJobs,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Log batch finish.
     *
     * @param Batch $batch
     * @return void
     */
    protected function logBatchFinished(Batch $batch): void
    {
        if (!config('anthropic.queue.monitoring.enabled')) {
            return;
        }

        Log::channel(config('anthropic.queue.monitoring.driver', 'daily'))
            ->info('Batch finished', [
                'id' => $batch->id,
                'name' => $batch->name,
                'total_jobs' => $batch->totalJobs,
                'processed_jobs' => $batch->processedJobs(),
                'failed_jobs' => $batch->failedJobs,
                'cancelled' => $batch->cancelled(),
                'runtime' => $batch->finishedAt->diffInSeconds($batch->createdAt),
            ]);
    }

    /**
     * Get queue metrics.
     *
     * @return array
     */
    public function getMetrics(): array
    {
        $metrics = [
            'jobs' => [
                'total' => 0,
                'processed' => 0,
                'failed' => 0,
                'waiting' => 0,
            ],
            'queues' => [],
            'workers' => [
                'active' => 0,
                'memory' => 0,
            ],
        ];

        // Get queue-specific metrics
        foreach (config('anthropic.queue.queues') as $name => $queue) {
            $size = $this->queue->size($queue);
            $metrics['queues'][$name] = [
                'size' => $size,
                'processed' => 0,
                'failed' => 0,
            ];
            $metrics['jobs']['waiting'] += $size;
        }

        return $metrics;
    }

    /**
     * Check queue health.
     *
     * @return array
     */
    public function checkHealth(): array
    {
        $metrics = $this->getMetrics();
        $thresholds = config('anthropic.queue.monitoring.alert_thresholds');

        $status = 'healthy';
        $issues = [];

        // Check failed jobs
        if ($metrics['jobs']['failed'] >= $thresholds['failed_jobs']) {
            $status = 'warning';
            $issues[] = 'High number of failed jobs';
        }

        // Check waiting jobs
        if ($metrics['jobs']['waiting'] >= $thresholds['waiting_jobs']) {
            $status = 'warning';
            $issues[] = 'High number of waiting jobs';
        }

        // Check worker memory usage
        if ($metrics['workers']['memory'] >= $thresholds['memory_usage']) {
            $status = 'warning';
            $issues[] = 'High worker memory usage';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'metrics' => $metrics,
        ];
    }
}
