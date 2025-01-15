<?php

namespace Ajz\Anthropic\Tests\Integration;

use Ajz\Anthropic\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;

abstract class IntegrationTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Indicates whether events should be faked.
     *
     * @var bool
     */
    protected bool $fakeEvents = true;

    /**
     * Indicates whether queues should be faked.
     *
     * @var bool
     */
    protected bool $fakeQueue = true;

    /**
     * Indicates whether cache should be faked.
     *
     * @var bool
     */
    protected bool $fakeCache = true;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Fake facades if configured
        if ($this->fakeEvents) {
            Event::fake();
        }

        if ($this->fakeQueue) {
            Queue::fake();
        }

        if ($this->fakeCache) {
            Cache::fake();
        }

        // Set up test data
        $this->setUpTestData();
    }

    /**
     * Set up test data.
     *
     * @return void
     */
    protected function setUpTestData(): void
    {
        // Override in test classes to set up specific test data
    }

    /**
     * Assert that an event was dispatched.
     *
     * @param string $event
     * @param callable|null $callback
     * @return void
     */
    protected function assertEventDispatched(string $event, ?callable $callback = null): void
    {
        Event::assertDispatched($event, $callback);
    }

    /**
     * Assert that a job was dispatched.
     *
     * @param string $job
     * @param callable|null $callback
     * @return void
     */
    protected function assertJobDispatched(string $job, ?callable $callback = null): void
    {
        Queue::assertPushed($job, $callback);
    }

    /**
     * Assert that a value was cached.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    protected function assertCached(string $key, mixed $value): void
    {
        $this->assertEquals($value, Cache::get($key));
    }

    /**
     * Create a test agent.
     *
     * @param array $attributes
     * @return \Ajz\Anthropic\Models\Agent
     */
    protected function createAgent(array $attributes = []): \Ajz\Anthropic\Models\Agent
    {
        return \Ajz\Anthropic\Models\Agent::factory()->create($attributes);
    }

    /**
     * Create a test session.
     *
     * @param array $attributes
     * @return \Ajz\Anthropic\Models\Session
     */
    protected function createSession(array $attributes = []): \Ajz\Anthropic\Models\Session
    {
        return \Ajz\Anthropic\Models\Session::factory()->create($attributes);
    }

    /**
     * Create a test user.
     *
     * @param array $attributes
     * @return \Ajz\Anthropic\Models\User
     */
    protected function createUser(array $attributes = []): \Ajz\Anthropic\Models\User
    {
        return \Ajz\Anthropic\Models\User::factory()->create($attributes);
    }

    /**
     * Create a test organization.
     *
     * @param array $attributes
     * @return \Ajz\Anthropic\Models\Organization
     */
    protected function createOrganization(array $attributes = []): \Ajz\Anthropic\Models\Organization
    {
        return \Ajz\Anthropic\Models\Organization::factory()->create($attributes);
    }

    /**
     * Create a test team.
     *
     * @param array $attributes
     * @return \Ajz\Anthropic\Models\Team
     */
    protected function createTeam(array $attributes = []): \Ajz\Anthropic\Models\Team
    {
        return \Ajz\Anthropic\Models\Team::factory()->create($attributes);
    }

    /**
     * Create a test API key.
     *
     * @param array $attributes
     * @return \Ajz\Anthropic\Models\ApiKey
     */
    protected function createApiKey(array $attributes = []): \Ajz\Anthropic\Models\ApiKey
    {
        return \Ajz\Anthropic\Models\ApiKey::factory()->create($attributes);
    }

    /**
     * Create a test access token.
     *
     * @param array $attributes
     * @return \Ajz\Anthropic\Models\AccessToken
     */
    protected function createAccessToken(array $attributes = []): \Ajz\Anthropic\Models\AccessToken
    {
        return \Ajz\Anthropic\Models\AccessToken::factory()->create($attributes);
    }

    /**
     * Assert that a model exists in the database.
     *
     * @param string $model
     * @param array $attributes
     * @return void
     */
    protected function assertModelExists(string $model, array $attributes): void
    {
        $this->assertDatabaseHas((new $model)->getTable(), $attributes);
    }

    /**
     * Assert that a model does not exist in the database.
     *
     * @param string $model
     * @param array $attributes
     * @return void
     */
    protected function assertModelMissing(string $model, array $attributes): void
    {
        $this->assertDatabaseMissing((new $model)->getTable(), $attributes);
    }

    /**
     * Assert that a relationship exists between models.
     *
     * @param string $relation
     * @param mixed $model1
     * @param mixed $model2
     * @param array $pivotAttributes
     * @return void
     */
    protected function assertRelationshipExists(
        string $relation,
        mixed $model1,
        mixed $model2,
        array $pivotAttributes = []
    ): void {
        $this->assertTrue(
            $model1->{$relation}()->where('id', $model2->id)->exists(),
            "Failed asserting that a relationship '{$relation}' exists."
        );

        if ($pivotAttributes) {
            $this->assertDatabaseHas(
                $model1->{$relation}()->getTable(),
                array_merge(
                    [
                        $model1->{$relation}()->getForeignPivotKeyName() => $model1->id,
                        $model1->{$relation}()->getRelatedPivotKeyName() => $model2->id,
                    ],
                    $pivotAttributes
                )
            );
        }
    }

    /**
     * Assert that a workflow completed successfully.
     *
     * @param string $workflow
     * @param array $data
     * @return void
     */
    protected function assertWorkflowCompleted(string $workflow, array $data): void
    {
        $this->assertEventDispatched("{$workflow}.completed", function ($event) use ($data) {
            return $event->data === $data;
        });

        $this->assertModelExists(\Ajz\Anthropic\Models\WorkflowRun::class, [
            'workflow' => $workflow,
            'status' => 'completed',
            'data' => json_encode($data),
        ]);
    }

    /**
     * Assert that a workflow failed.
     *
     * @param string $workflow
     * @param string $error
     * @return void
     */
    protected function assertWorkflowFailed(string $workflow, string $error): void
    {
        $this->assertEventDispatched("{$workflow}.failed", function ($event) use ($error) {
            return $event->error === $error;
        });

        $this->assertModelExists(\Ajz\Anthropic\Models\WorkflowRun::class, [
            'workflow' => $workflow,
            'status' => 'failed',
            'error' => $error,
        ]);
    }

    /**
     * Assert that metrics were recorded.
     *
     * @param string $metric
     * @param array $data
     * @return void
     */
    protected function assertMetricsRecorded(string $metric, array $data): void
    {
        $this->assertModelExists(\Ajz\Anthropic\Models\Metric::class, [
            'name' => $metric,
            'data' => json_encode($data),
        ]);
    }

    /**
     * Assert that an audit log was created.
     *
     * @param string $action
     * @param array $data
     * @return void
     */
    protected function assertAuditLogCreated(string $action, array $data): void
    {
        $this->assertModelExists(\Ajz\Anthropic\Models\AuditLog::class, [
            'action' => $action,
            'data' => json_encode($data),
        ]);
    }
}
