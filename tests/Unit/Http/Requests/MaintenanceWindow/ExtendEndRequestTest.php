<?php

namespace Tests\Unit\Http\Requests\MaintenanceWindow;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ajz\Anthropic\Http\Requests\MaintenanceWindow\ExtendRequest;
use Ajz\Anthropic\Http\Requests\MaintenanceWindow\EndRequest;
use Ajz\Anthropic\Models\MaintenanceWindow;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ExtendEndRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('manage-maintenance-windows', function ($user) {
            return true;
        });
    }

    /** @test */
    public function it_validates_extend_request_with_valid_data()
    {
        $window = MaintenanceWindow::factory()->create([
            'status' => 'active',
            'start_time' => Carbon::now()->subHour(),
            'duration' => 2,
        ]);

        $request = ExtendRequest::create("/maintenance-windows/{$window->id}/extend", 'POST', [
            'duration' => 2,
            'reason' => 'Additional tasks required for system upgrade',
        ]);

        $request->setRouteResolver(function () use ($window) {
            return tap(new \Illuminate\Routing\Route('POST', 'maintenance-windows/{window}/extend', []), function ($route) use ($window) {
                $route->setParameter('window', $window);
            });
        });

        $this->assertTrue($request->authorize());
        $this->assertEmpty($request->validator()->errors()->all());
    }

    /** @test */
    public function it_rejects_extend_request_for_non_active_window()
    {
        $window = MaintenanceWindow::factory()->create([
            'status' => 'pending',
        ]);

        $request = ExtendRequest::create("/maintenance-windows/{$window->id}/extend", 'POST', [
            'duration' => 2,
            'reason' => 'Additional tasks required',
        ]);

        $request->setRouteResolver(function () use ($window) {
            return tap(new \Illuminate\Routing\Route('POST', 'maintenance-windows/{window}/extend', []), function ($route) use ($window) {
                $route->setParameter('window', $window);
            });
        });

        $validator = $request->validator();
        $validator->validate();

        $this->assertTrue($validator->errors()->has('window'));
    }

    /** @test */
    public function it_rejects_extend_request_exceeding_max_total_duration()
    {
        $window = MaintenanceWindow::factory()->create([
            'status' => 'active',
            'duration' => 90, // Already close to max
        ]);

        $request = ExtendRequest::create("/maintenance-windows/{$window->id}/extend", 'POST', [
            'duration' => 10, // Would exceed 96-hour limit
            'reason' => 'Need more time',
        ]);

        $request->setRouteResolver(function () use ($window) {
            return tap(new \Illuminate\Routing\Route('POST', 'maintenance-windows/{window}/extend', []), function ($route) use ($window) {
                $route->setParameter('window', $window);
            });
        });

        $validator = $request->validator();
        $validator->validate();

        $this->assertTrue($validator->errors()->has('duration'));
    }

    /** @test */
    public function it_validates_end_request_with_valid_data()
    {
        $window = MaintenanceWindow::factory()->create([
            'status' => 'active',
            'start_time' => Carbon::now()->subMinutes(30),
        ]);

        $request = EndRequest::create("/maintenance-windows/{$window->id}/end", 'POST', [
            'reason' => 'Maintenance completed ahead of schedule',
            'completion_status' => 'completed',
        ]);

        $request->setRouteResolver(function () use ($window) {
            return tap(new \Illuminate\Routing\Route('POST', 'maintenance-windows/{window}/end', []), function ($route) use ($window) {
                $route->setParameter('window', $window);
            });
        });

        $this->assertTrue($request->authorize());
        $this->assertEmpty($request->validator()->errors()->all());
    }

    /** @test */
    public function it_requires_completion_notes_for_partially_completed_status()
    {
        $window = MaintenanceWindow::factory()->create([
            'status' => 'active',
            'start_time' => Carbon::now()->subMinutes(30),
        ]);

        $request = EndRequest::create("/maintenance-windows/{$window->id}/end", 'POST', [
            'reason' => 'Some tasks pending',
            'completion_status' => 'partially_completed',
            // Missing completion_notes
        ]);

        $request->setRouteResolver(function () use ($window) {
            return tap(new \Illuminate\Routing\Route('POST', 'maintenance-windows/{window}/end', []), function ($route) use ($window) {
                $route->setParameter('window', $window);
            });
        });

        $this->expectException(ValidationException::class);
        $request->validateResolved();
    }

    /** @test */
    public function it_validates_remaining_tasks_format()
    {
        $window = MaintenanceWindow::factory()->create([
            'status' => 'active',
            'start_time' => Carbon::now()->subMinutes(30),
        ]);

        $request = EndRequest::create("/maintenance-windows/{$window->id}/end", 'POST', [
            'reason' => 'Some tasks pending',
            'completion_status' => 'partially_completed',
            'completion_notes' => 'Detailed notes about partial completion',
            'remaining_tasks' => [
                'too short', // Should fail min length
                'This is a properly formatted remaining task description',
            ],
            'follow_up_required' => true,
            'follow_up_date' => Carbon::tomorrow(),
        ]);

        $request->setRouteResolver(function () use ($window) {
            return tap(new \Illuminate\Routing\Route('POST', 'maintenance-windows/{window}/end', []), function ($route) use ($window) {
                $route->setParameter('window', $window);
            });
        });

        $this->expectException(ValidationException::class);
        $request->validateResolved();
    }

    /** @test */
    public function it_enforces_minimum_duration_before_ending()
    {
        $window = MaintenanceWindow::factory()->create([
            'status' => 'active',
            'start_time' => Carbon::now()->subMinutes(5), // Less than minimum duration
        ]);

        $request = EndRequest::create("/maintenance-windows/{$window->id}/end", 'POST', [
            'reason' => 'Completed early',
            'completion_status' => 'completed',
        ]);

        $request->setRouteResolver(function () use ($window) {
            return tap(new \Illuminate\Routing\Route('POST', 'maintenance-windows/{window}/end', []), function ($route) use ($window) {
                $route->setParameter('window', $window);
            });
        });

        $validator = $request->validator();
        $validator->validate();

        $this->assertTrue($validator->errors()->has('duration'));
    }

    /** @test */
    public function it_validates_follow_up_date_is_in_future()
    {
        $window = MaintenanceWindow::factory()->create([
            'status' => 'active',
            'start_time' => Carbon::now()->subMinutes(30),
        ]);

        $request = EndRequest::create("/maintenance-windows/{$window->id}/end", 'POST', [
            'reason' => 'Some tasks pending',
            'completion_status' => 'partially_completed',
            'completion_notes' => 'Detailed notes',
            'remaining_tasks' => ['A properly formatted remaining task'],
            'follow_up_required' => true,
            'follow_up_date' => Carbon::yesterday(), // Past date
        ]);

        $request->setRouteResolver(function () use ($window) {
            return tap(new \Illuminate\Routing\Route('POST', 'maintenance-windows/{window}/end', []), function ($route) use ($window) {
                $route->setParameter('window', $window);
            });
        });

        $this->expectException(ValidationException::class);
        $request->validateResolved();
    }
}
