<?php

namespace Tests\Feature\MaintenanceWindow;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ajz\Anthropic\Models\MaintenanceWindow;
use Ajz\Anthropic\Models\User;
use Ajz\Anthropic\Events\MaintenanceWindowExtended;
use Ajz\Anthropic\Events\MaintenanceWindowEnded;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

class ExtendEndMaintenanceWindowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected MaintenanceWindow $window;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'permissions' => ['manage-maintenance-windows'],
        ]);

        $this->window = MaintenanceWindow::factory()->create([
            'status' => 'active',
            'start_time' => Carbon::now()->subHour(),
            'duration' => 2,
            'environment' => 'prod',
        ]);
    }

    /** @test */
    public function authorized_user_can_extend_active_maintenance_window()
    {
        Event::fake();

        $response = $this->actingAs($this->user)
            ->postJson("/api/maintenance-windows/{$this->window->id}/extend", [
                'duration' => 2,
                'reason' => 'Additional tasks required for system upgrade',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Maintenance window extended successfully',
                'window' => [
                    'id' => $this->window->id,
                    'duration' => 4, // Original 2 + extension 2
                    'status' => 'active',
                ],
            ]);

        $this->window->refresh();
        $this->assertEquals(4, $this->window->duration);
        $this->assertEquals('active', $this->window->status);

        Event::assertDispatched(MaintenanceWindowExtended::class, function ($event) {
            return $event->window->id === $this->window->id
                && $event->extendedBy === $this->user->id
                && $event->additionalDuration === 2;
        });
    }

    /** @test */
    public function unauthorized_user_cannot_extend_maintenance_window()
    {
        $unauthorizedUser = User::factory()->create();

        $response = $this->actingAs($unauthorizedUser)
            ->postJson("/api/maintenance-windows/{$this->window->id}/extend", [
                'duration' => 2,
                'reason' => 'Additional tasks required',
            ]);

        $response->assertStatus(403);

        $this->window->refresh();
        $this->assertEquals(2, $this->window->duration);
    }

    /** @test */
    public function cannot_extend_non_active_maintenance_window()
    {
        $window = MaintenanceWindow::factory()->create([
            'status' => 'pending',
            'start_time' => Carbon::tomorrow(),
            'duration' => 2,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/maintenance-windows/{$window->id}/extend", [
                'duration' => 2,
                'reason' => 'Additional tasks required',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['window']);
    }

    /** @test */
    public function authorized_user_can_end_active_maintenance_window()
    {
        Event::fake();

        $response = $this->actingAs($this->user)
            ->postJson("/api/maintenance-windows/{$this->window->id}/end", [
                'reason' => 'Maintenance completed ahead of schedule',
                'completion_status' => 'completed',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Maintenance window ended successfully',
                'window' => [
                    'id' => $this->window->id,
                    'status' => 'completed',
                ],
            ]);

        $this->window->refresh();
        $this->assertEquals('completed', $this->window->status);
        $this->assertNotNull($this->window->ended_at);

        Event::assertDispatched(MaintenanceWindowEnded::class, function ($event) {
            return $event->window->id === $this->window->id
                && $event->endedBy === $this->user->id
                && $event->completionStatus === 'completed';
        });
    }

    /** @test */
    public function can_end_maintenance_window_with_partial_completion()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/maintenance-windows/{$this->window->id}/end", [
                'reason' => 'Some tasks pending',
                'completion_status' => 'partially_completed',
                'completion_notes' => 'Detailed notes about partial completion',
                'remaining_tasks' => [
                    'Configure backup system',
                    'Verify replication status',
                ],
                'follow_up_required' => true,
                'follow_up_date' => Carbon::tomorrow()->toDateTimeString(),
            ]);

        $response->assertStatus(200);

        $this->window->refresh();
        $this->assertEquals('partially_completed', $this->window->status);
        $this->assertNotNull($this->window->follow_up_date);
        $this->assertCount(2, $this->window->remaining_tasks);
    }

    /** @test */
    public function cannot_end_maintenance_window_too_early()
    {
        $window = MaintenanceWindow::factory()->create([
            'status' => 'active',
            'start_time' => Carbon::now()->subMinutes(5), // Less than minimum duration
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/maintenance-windows/{$window->id}/end", [
                'reason' => 'Completed early',
                'completion_status' => 'completed',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['duration']);

        $window->refresh();
        $this->assertEquals('active', $window->status);
    }

    /** @test */
    public function ending_window_creates_audit_log()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/maintenance-windows/{$this->window->id}/end", [
                'reason' => 'Maintenance completed successfully',
                'completion_status' => 'completed',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('maintenance_window_audit_logs', [
            'window_id' => $this->window->id,
            'user_id' => $this->user->id,
            'action' => 'ended',
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function extending_window_creates_audit_log()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/maintenance-windows/{$this->window->id}/extend", [
                'duration' => 2,
                'reason' => 'Additional tasks required',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('maintenance_window_audit_logs', [
            'window_id' => $this->window->id,
            'user_id' => $this->user->id,
            'action' => 'extended',
            'details' => json_encode([
                'additional_duration' => 2,
                'reason' => 'Additional tasks required',
            ]),
        ]);
    }

    /** @test */
    public function notifications_are_sent_when_window_is_extended()
    {
        Notification::fake();

        $response = $this->actingAs($this->user)
            ->postJson("/api/maintenance-windows/{$this->window->id}/extend", [
                'duration' => 2,
                'reason' => 'Additional tasks required',
            ]);

        $response->assertStatus(200);

        Notification::assertSentTo(
            User::permission('receive-maintenance-notifications')->get(),
            MaintenanceWindowExtendedNotification::class
        );
    }

    /** @test */
    public function notifications_are_sent_when_window_is_ended()
    {
        Notification::fake();

        $response = $this->actingAs($this->user)
            ->postJson("/api/maintenance-windows/{$this->window->id}/end", [
                'reason' => 'Maintenance completed successfully',
                'completion_status' => 'completed',
            ]);

        $response->assertStatus(200);

        Notification::assertSentTo(
            User::permission('receive-maintenance-notifications')->get(),
            MaintenanceWindowEndedNotification::class
        );
    }
}
