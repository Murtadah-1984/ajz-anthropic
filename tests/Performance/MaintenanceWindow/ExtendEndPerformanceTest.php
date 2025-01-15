<?php

namespace Tests\Performance\MaintenanceWindow;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ajz\Anthropic\Models\MaintenanceWindow;
use Ajz\Anthropic\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class ExtendEndPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected array $windows = [];
    protected const CONCURRENT_REQUESTS = 50;
    protected const ACCEPTABLE_RESPONSE_TIME = 500; // milliseconds

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'permissions' => ['manage-maintenance-windows'],
        ]);

        // Create multiple maintenance windows for testing
        for ($i = 0; $i < self::CONCURRENT_REQUESTS; $i++) {
            $this->windows[] = MaintenanceWindow::factory()->create([
                'status' => 'active',
                'start_time' => Carbon::now()->subHour(),
                'duration' => 2,
                'environment' => 'prod',
            ]);
        }

        // Clear caches before each test
        Cache::flush();
        Redis::flushall();
    }

    /** @test */
    public function concurrent_window_extensions_perform_within_acceptable_time()
    {
        $processes = [];
        $startTime = microtime(true);

        // Launch concurrent requests
        foreach ($this->windows as $window) {
            $processes[] = $this->async(function () use ($window) {
                return $this->actingAs($this->user)
                    ->postJson("/api/maintenance-windows/{$window->id}/extend", [
                        'duration' => 2,
                        'reason' => 'Performance test extension',
                    ]);
            });
        }

        // Wait for all processes and collect results
        $responses = [];
        foreach ($processes as $process) {
            $responses[] = $process->wait();
        }

        $totalTime = (microtime(true) - $startTime) * 1000;
        $averageTime = $totalTime / count($processes);

        // Assert performance metrics
        $this->assertLessThan(
            self::ACCEPTABLE_RESPONSE_TIME,
            $averageTime,
            "Average response time ({$averageTime}ms) exceeded acceptable limit (" . self::ACCEPTABLE_RESPONSE_TIME . "ms)"
        );

        // Verify database consistency
        $this->assertDatabaseConsistency();
    }

    /** @test */
    public function concurrent_window_endings_perform_within_acceptable_time()
    {
        $processes = [];
        $startTime = microtime(true);

        // Launch concurrent requests
        foreach ($this->windows as $window) {
            $processes[] = $this->async(function () use ($window) {
                return $this->actingAs($this->user)
                    ->postJson("/api/maintenance-windows/{$window->id}/end", [
                        'reason' => 'Performance test completion',
                        'completion_status' => 'completed',
                    ]);
            });
        }

        // Wait for all processes and collect results
        $responses = [];
        foreach ($processes as $process) {
            $responses[] = $process->wait();
        }

        $totalTime = (microtime(true) - $startTime) * 1000;
        $averageTime = $totalTime / count($processes);

        // Assert performance metrics
        $this->assertLessThan(
            self::ACCEPTABLE_RESPONSE_TIME,
            $averageTime,
            "Average response time ({$averageTime}ms) exceeded acceptable limit (" . self::ACCEPTABLE_RESPONSE_TIME . "ms)"
        );

        // Verify database consistency
        $this->assertDatabaseConsistency();
    }

    /** @test */
    public function cache_improves_read_performance()
    {
        // Warm up cache
        $this->actingAs($this->user)
            ->getJson('/api/maintenance-windows/active');

        $startTime = microtime(true);

        // Perform multiple read requests
        for ($i = 0; $i < 100; $i++) {
            $response = $this->actingAs($this->user)
                ->getJson('/api/maintenance-windows/active');
            $response->assertStatus(200);
        }

        $averageTime = ((microtime(true) - $startTime) * 1000) / 100;

        $this->assertLessThan(
            50, // 50ms target for cached reads
            $averageTime,
            "Cached read performance ({$averageTime}ms) exceeded target (50ms)"
        );
    }

    /** @test */
    public function database_locks_prevent_race_conditions()
    {
        $window = $this->windows[0];
        $successCount = 0;
        $processes = [];

        // Attempt concurrent extensions
        for ($i = 0; $i < 10; $i++) {
            $processes[] = $this->async(function () use ($window) {
                return $this->actingAs($this->user)
                    ->postJson("/api/maintenance-windows/{$window->id}/extend", [
                        'duration' => 1,
                        'reason' => 'Concurrent extension test',
                    ]);
            });
        }

        foreach ($processes as $process) {
            $response = $process->wait();
            if ($response->status() === 200) {
                $successCount++;
            }
        }

        // Only one extension should succeed
        $this->assertEquals(1, $successCount);

        // Verify final duration
        $window->refresh();
        $this->assertEquals(3, $window->duration); // Original 2 + 1
    }

    /** @test */
    public function event_dispatching_scales_with_load()
    {
        $startTime = microtime(true);
        $processes = [];

        // Create multiple windows and end them concurrently
        foreach ($this->windows as $window) {
            $processes[] = $this->async(function () use ($window) {
                return $this->actingAs($this->user)
                    ->postJson("/api/maintenance-windows/{$window->id}/end", [
                        'reason' => 'Performance test completion',
                        'completion_status' => 'completed',
                    ]);
            });
        }

        // Wait for all processes
        foreach ($processes as $process) {
            $process->wait();
        }

        $totalTime = (microtime(true) - $startTime) * 1000;

        // Assert event processing time
        $this->assertLessThan(
            self::ACCEPTABLE_RESPONSE_TIME * 2, // Allow more time for event processing
            $totalTime,
            "Event processing time exceeded acceptable limit"
        );

        // Verify all events were processed
        $this->assertEquals(
            count($this->windows),
            DB::table('maintenance_window_audit_logs')->count()
        );
    }

    /**
     * Helper method to verify database consistency
     */
    protected function assertDatabaseConsistency(): void
    {
        // Verify no duplicate audit logs
        $duplicates = DB::table('maintenance_window_audit_logs')
            ->select('window_id', 'action')
            ->groupBy('window_id', 'action')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $this->assertEmpty($duplicates, 'Found duplicate audit log entries');

        // Verify window statuses are consistent
        foreach ($this->windows as $window) {
            $window->refresh();
            $this->assertContains(
                $window->status,
                ['active', 'completed'],
                "Window {$window->id} has invalid status: {$window->status}"
            );
        }
    }

    /**
     * Helper method to run async processes
     */
    protected function async(callable $callback)
    {
        return new class($callback) {
            private $callback;
            private $process;

            public function __construct(callable $callback)
            {
                $this->callback = $callback;
                $this->process = pcntl_fork();

                if ($this->process === -1) {
                    throw new \RuntimeException('Failed to fork process');
                }

                if ($this->process === 0) {
                    // Child process
                    $result = ($this->callback)();
                    exit(json_encode($result));
                }
            }

            public function wait()
            {
                pcntl_waitpid($this->process, $status);
                return json_decode(file_get_contents("/tmp/result_{$this->process}"));
            }
        };
    }
}
