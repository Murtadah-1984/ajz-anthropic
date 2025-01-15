<?php

namespace Tests\Unit\Http\Requests\MaintenanceWindow;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ajz\Anthropic\Http\Requests\MaintenanceWindow\CreateRequest;
use Ajz\Anthropic\Http\Requests\MaintenanceWindow\UpdateRequest;
use Ajz\Anthropic\Models\MaintenanceWindow;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MaintenanceWindowRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the authorization gate
        Gate::define('manage-maintenance-windows', function ($user) {
            return true;
        });
    }

    /** @test */
    public function it_validates_create_request_with_valid_data()
    {
        $request = CreateRequest::create('/maintenance-windows', 'POST', [
            'environment' => 'prod',
            'start_time' => Carbon::now()->addHour()->toDateTimeString(),
            'duration' => 2,
            'comment' => 'Scheduled maintenance for system updates',
        ]);

        $this->assertTrue($request->authorize());
        $this->assertEmpty($request->validator()->errors()->all());
    }

    /** @test */
    public function it_rejects_create_request_with_invalid_environment()
    {
        $this->expectException(ValidationException::class);

        $request = CreateRequest::create('/maintenance-windows', 'POST', [
            'environment' => 'invalid',
            'start_time' => Carbon::now()->addHour()->toDateTimeString(),
            'duration' => 2,
            'comment' => 'Test maintenance',
        ]);

        $request->validateResolved();
    }

    /** @test */
    public function it_rejects_create_request_with_past_start_time()
    {
        $this->expectException(ValidationException::class);

        $request = CreateRequest::create('/maintenance-windows', 'POST', [
            'environment' => 'prod',
            'start_time' => Carbon::now()->subHour()->toDateTimeString(),
            'duration' => 2,
            'comment' => 'Test maintenance',
        ]);

        $request->validateResolved();
    }

    /** @test */
    public function it_rejects_create_request_with_invalid_duration()
    {
        $this->expectException(ValidationException::class);

        $request = CreateRequest::create('/maintenance-windows', 'POST', [
            'environment' => 'prod',
            'start_time' => Carbon::now()->addHour()->toDateTimeString(),
            'duration' => 100, // Exceeds max duration
            'comment' => 'Test maintenance',
        ]);

        $request->validateResolved();
    }

    /** @test */
    public function it_validates_update_request_with_valid_data()
    {
        $window = MaintenanceWindow::factory()->create([
            'status' => 'pending',
        ]);

        $request = UpdateRequest::create("/maintenance-windows/{$window->id}", 'PUT', [
            'duration' => 3,
            'comment' => 'Updated maintenance window comment',
        ]);

        $request->setRouteResolver(function () use ($window) {
            return tap(new \Illuminate\Routing\Route('PUT', 'maintenance-windows/{window}', []), function ($route) use ($window) {
                $route->setParameter('window', $window);
            });
        });

        $this->assertTrue($request->authorize());
        $this->assertEmpty($request->validator()->errors()->all());
    }

    /** @test */
    public function it_rejects_update_request_for_expired_window()
    {
        $window = MaintenanceWindow::factory()->create([
            'status' => 'expired',
        ]);

        $request = UpdateRequest::create("/maintenance-windows/{$window->id}", 'PUT', [
            'duration' => 3,
            'comment' => 'Updated maintenance window comment',
        ]);

        $request->setRouteResolver(function () use ($window) {
            return tap(new \Illuminate\Routing\Route('PUT', 'maintenance-windows/{window}', []), function ($route) use ($window) {
                $route->setParameter('window', $window);
            });
        });

        $validator = $request->validator();
        $validator->validate();

        $this->assertTrue($validator->errors()->has('window'));
    }

    /** @test */
    public function it_rejects_start_time_modification_for_active_window()
    {
        $window = MaintenanceWindow::factory()->create([
            'status' => 'active',
        ]);

        $request = UpdateRequest::create("/maintenance-windows/{$window->id}", 'PUT', [
            'start_time' => Carbon::now()->addHour()->toDateTimeString(),
        ]);

        $request->setRouteResolver(function () use ($window) {
            return tap(new \Illuminate\Routing\Route('PUT', 'maintenance-windows/{window}', []), function ($route) use ($window) {
                $route->setParameter('window', $window);
            });
        });

        $validator = $request->validator();
        $validator->validate();

        $this->assertTrue($validator->errors()->has('start_time'));
    }

    /** @test */
    public function it_detects_time_conflicts_in_update_request()
    {
        // Create an existing window
        $existingWindow = MaintenanceWindow::factory()->create([
            'environment' => 'prod',
            'start_time' => Carbon::now()->addHours(2),
            'duration' => 2,
        ]);

        // Create another window to update
        $window = MaintenanceWindow::factory()->create([
            'environment' => 'prod',
            'start_time' => Carbon::now()->addHours(6),
            'duration' => 2,
        ]);

        // Try to update the second window to overlap with the first
        $request = UpdateRequest::create("/maintenance-windows/{$window->id}", 'PUT', [
            'start_time' => Carbon::now()->addHours(3), // This would overlap
            'duration' => 2,
        ]);

        $request->setRouteResolver(function () use ($window) {
            return tap(new \Illuminate\Routing\Route('PUT', 'maintenance-windows/{window}', []), function ($route) use ($window) {
                $route->setParameter('window', $window);
            });
        });

        $validator = $request->validator();
        $validator->validate();

        $this->assertTrue($validator->errors()->has('time_conflict'));
    }

    /** @test */
    public function it_allows_concurrent_windows_in_different_environments()
    {
        // Create an existing window in prod
        $existingWindow = MaintenanceWindow::factory()->create([
            'environment' => 'prod',
            'start_time' => Carbon::now()->addHours(2),
            'duration' => 2,
        ]);

        // Create a window in staging
        $window = MaintenanceWindow::factory()->create([
            'environment' => 'staging',
            'start_time' => Carbon::now()->addHours(6),
            'duration' => 2,
        ]);

        // Update staging window to overlap with prod window (should be allowed)
        $request = UpdateRequest::create("/maintenance-windows/{$window->id}", 'PUT', [
            'start_time' => Carbon::now()->addHours(3),
            'duration' => 2,
        ]);

        $request->setRouteResolver(function () use ($window) {
            return tap(new \Illuminate\Routing\Route('PUT', 'maintenance-windows/{window}', []), function ($route) use ($window) {
                $route->setParameter('window', $window);
            });
        });

        $validator = $request->validator();
        $validator->validate();

        $this->assertEmpty($validator->errors()->all());
    }

    /** @test */
    public function it_properly_formats_validation_messages()
    {
        $request = new CreateRequest();
        $messages = $request->messages();

        $this->assertArrayHasKey('environment.in', $messages);
        $this->assertArrayHasKey('start_time.after_or_equal', $messages);
        $this->assertArrayHasKey('duration.min', $messages);
        $this->assertArrayHasKey('duration.max', $messages);
        $this->assertArrayHasKey('comment.min', $messages);
    }
}
