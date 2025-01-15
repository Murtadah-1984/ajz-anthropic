<?php

namespace Tests\Performance\MaintenanceWindow;

use Tests\TestCase;
use Illuminate\Support\Benchmark;
use Ajz\Anthropic\Models\MaintenanceWindow;
use Carbon\Carbon;

class TransformerPerformanceTest extends TestCase
{
    protected array $sampleData;
    protected const BATCH_SIZES = [10, 100, 1000, 10000];
    protected const ITERATIONS = 10;
    protected const MEMORY_LIMIT = 100 * 1024 * 1024; // 100MB
    protected const TIME_LIMIT = 1000; // 1 second in milliseconds

    protected function setUp(): void
    {
        parent::setUp();
        $this->generateSampleData();
    }

    protected function generateSampleData(): void
    {
        $this->sampleData = [];
        foreach (self::BATCH_SIZES as $size) {
            $this->sampleData[$size] = $this->generateWindows($size);
        }
    }

    protected function generateWindows(int $count): array
    {
        return array_map(function ($i) {
            return [
                'id' => $i,
                'environment' => ['prod', 'staging', 'dev', 'test'][rand(0, 3)],
                'start_time' => Carbon::now()->addHours(rand(1, 168))->toISOString(),
                'duration' => rand(1, 24),
                'comment' => "Test maintenance window $i",
                'status' => ['pending', 'active', 'completed'][rand(0, 2)],
                'created_by' => rand(1, 100),
                'tasks' => array_map(function ($j) {
                    return [
                        'id' => $j,
                        'description' => "Task $j",
                        'priority' => ['high', 'medium', 'low'][rand(0, 2)],
                        'estimated_duration' => rand(1, 8)
                    ];
                }, range(1, rand(1, 5)))
            ];
        }, range(1, $count));
    }

    /** @test */
    public function api_format_transformation_performance()
    {
        $results = [];

        foreach (self::BATCH_SIZES as $batchSize) {
            $windows = $this->sampleData[$batchSize];

            // Measure execution time
            $time = Benchmark::measure(function () use ($windows) {
                return array_map(fn($window) => toApiFormat($window), $windows);
            }, self::ITERATIONS);

            // Measure memory usage
            $memoryBefore = memory_get_usage(true);
            $result = array_map(fn($window) => toApiFormat($window), $windows);
            $memoryAfter = memory_get_usage(true);
            $memoryUsage = $memoryAfter - $memoryBefore;

            $results[$batchSize] = [
                'avg_time_ms' => $time['mean'] * 1000,
                'memory_mb' => round($memoryUsage / 1024 / 1024, 2),
                'items_per_second' => round($batchSize / ($time['mean'])),
            ];

            // Assert performance requirements
            $this->assertLessThan(
                self::TIME_LIMIT,
                $time['mean'] * 1000,
                "API transformation for batch size $batchSize exceeded time limit"
            );

            $this->assertLessThan(
                self::MEMORY_LIMIT,
                $memoryUsage,
                "API transformation for batch size $batchSize exceeded memory limit"
            );
        }

        $this->logPerformanceResults('API Format Transformation', $results);
    }

    /** @test */
    public function report_generation_performance()
    {
        $results = [];

        foreach (self::BATCH_SIZES as $batchSize) {
            $windows = $this->sampleData[$batchSize];

            // Test different grouping strategies
            foreach (['environment', 'status', 'month'] as $groupBy) {
                $time = Benchmark::measure(function () use ($windows, $groupBy) {
                    return toReportFormat($windows, [
                        'groupBy' => $groupBy,
                        'includeMetrics' => true
                    ]);
                }, self::ITERATIONS);

                $memoryBefore = memory_get_usage(true);
                $result = toReportFormat($windows, [
                    'groupBy' => $groupBy,
                    'includeMetrics' => true
                ]);
                $memoryAfter = memory_get_usage(true);
                $memoryUsage = $memoryAfter - $memoryBefore;

                $results[$batchSize][$groupBy] = [
                    'avg_time_ms' => $time['mean'] * 1000,
                    'memory_mb' => round($memoryUsage / 1024 / 1024, 2),
                    'items_per_second' => round($batchSize / ($time['mean'])),
                ];

                // Assert performance requirements
                $this->assertLessThan(
                    self::TIME_LIMIT * 2, // Allow more time for report generation
                    $time['mean'] * 1000,
                    "Report generation for batch size $batchSize and groupBy $groupBy exceeded time limit"
                );

                $this->assertLessThan(
                    self::MEMORY_LIMIT * 1.5, // Allow more memory for report generation
                    $memoryUsage,
                    "Report generation for batch size $batchSize and groupBy $groupBy exceeded memory limit"
                );
            }
        }

        $this->logPerformanceResults('Report Generation', $results);
    }

    /** @test */
    public function batch_transformation_performance()
    {
        $results = [];

        foreach (self::BATCH_SIZES as $batchSize) {
            $windows = $this->sampleData[$batchSize];

            // Test different transformation formats
            foreach (['api', 'monitoring', 'calendar', 'notification'] as $format) {
                $time = Benchmark::measure(function () use ($windows, $format) {
                    return transformBatch($windows, $format);
                }, self::ITERATIONS);

                $memoryBefore = memory_get_usage(true);
                $result = transformBatch($windows, $format);
                $memoryAfter = memory_get_usage(true);
                $memoryUsage = $memoryAfter - $memoryBefore;

                $results[$batchSize][$format] = [
                    'avg_time_ms' => $time['mean'] * 1000,
                    'memory_mb' => round($memoryUsage / 1024 / 1024, 2),
                    'items_per_second' => round($batchSize / ($time['mean'])),
                ];

                // Assert performance requirements
                $this->assertLessThan(
                    self::TIME_LIMIT,
                    $time['mean'] * 1000,
                    "Batch transformation for size $batchSize and format $format exceeded time limit"
                );

                $this->assertLessThan(
                    self::MEMORY_LIMIT,
                    $memoryUsage,
                    "Batch transformation for size $batchSize and format $format exceeded memory limit"
                );
            }
        }

        $this->logPerformanceResults('Batch Transformation', $results);
    }

    /** @test */
    public function concurrent_transformation_performance()
    {
        $results = [];
        $maxConcurrency = 4;

        foreach (self::BATCH_SIZES as $batchSize) {
            $windows = $this->sampleData[$batchSize];

            // Test with different concurrency levels
            for ($concurrency = 1; $concurrency <= $maxConcurrency; $concurrency++) {
                $chunkSize = ceil(count($windows) / $concurrency);
                $chunks = array_chunk($windows, $chunkSize);

                $time = Benchmark::measure(function () use ($chunks) {
                    return array_map(function ($chunk) {
                        return transformBatch($chunk, 'api');
                    }, $chunks);
                }, self::ITERATIONS);

                $results[$batchSize][$concurrency] = [
                    'avg_time_ms' => $time['mean'] * 1000,
                    'chunks' => count($chunks),
                    'chunk_size' => $chunkSize,
                    'items_per_second' => round($batchSize / ($time['mean'])),
                ];

                // Assert performance improvement with concurrency
                if ($concurrency > 1) {
                    $this->assertLessThan(
                        $results[$batchSize][1]['avg_time_ms'],
                        $results[$batchSize][$concurrency]['avg_time_ms'],
                        "Concurrent processing did not improve performance for batch size $batchSize"
                    );
                }
            }
        }

        $this->logPerformanceResults('Concurrent Transformation', $results);
    }

    protected function logPerformanceResults(string $testName, array $results): void
    {
        $logFile = storage_path("logs/performance_{$testName}.json");
        file_put_contents($logFile, json_encode([
            'test_name' => $testName,
            'timestamp' => Carbon::now()->toISOString(),
            'results' => $results,
            'system_info' => [
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'cpu_cores' => php_sapi_name() === 'cli' ? shell_exec('nproc') : 'N/A',
            ]
        ], JSON_PRETTY_PRINT));
    }
}
