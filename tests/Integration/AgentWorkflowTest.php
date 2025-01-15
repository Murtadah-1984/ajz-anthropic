<?php

namespace Ajz\Anthropic\Tests\Integration;

use Ajz\Anthropic\Models\Agent;
use Ajz\Anthropic\Models\Session;
use Ajz\Anthropic\Models\User;
use Ajz\Anthropic\Models\Organization;
use Ajz\Anthropic\Models\Team;
use Ajz\Anthropic\Services\Agency\AgentService;
use Ajz\Anthropic\Services\Agency\SessionService;
use Illuminate\Support\Facades\Event;

class AgentWorkflowTest extends IntegrationTestCase
{
    /**
     * The agent service instance.
     *
     * @var AgentService
     */
    protected AgentService $agentService;

    /**
     * The session service instance.
     *
     * @var SessionService
     */
    protected SessionService $sessionService;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->agentService = $this->app->make(AgentService::class);
        $this->sessionService = $this->app->make(SessionService::class);
    }

    /**
     * Test the complete agent workflow.
     *
     * @return void
     */
    public function test_complete_agent_workflow(): void
    {
        // Create test organization and team
        $organization = $this->createOrganization();
        $team = $this->createTeam(['organization_id' => $organization->id]);
        $user = $this->createUser();

        // Add user to team
        $team->addMember($user, 'admin');

        // Create an agent
        $agent = $this->agentService->create([
            'type' => 'developer',
            'name' => 'Test Agent',
            'organization_id' => $organization->id,
            'team_id' => $team->id,
            'configuration' => [
                'languages' => ['php', 'javascript'],
                'frameworks' => ['laravel', 'vue'],
                'code_review_settings' => ['enabled' => true],
                'testing_frameworks' => ['phpunit', 'jest'],
                'documentation_format' => 'markdown',
                'style_guide' => 'psr-12',
            ],
        ]);

        $this->assertModelExists(Agent::class, [
            'id' => $agent->id,
            'type' => 'developer',
            'name' => 'Test Agent',
        ]);

        $this->assertEventDispatched('agent.created');

        // Start a session
        $session = $this->sessionService->start([
            'agent_id' => $agent->id,
            'user_id' => $user->id,
            'type' => 'development',
            'context' => [
                'project' => 'test-project',
                'task' => 'code-review',
            ],
        ]);

        $this->assertModelExists(Session::class, [
            'id' => $session->id,
            'agent_id' => $agent->id,
            'user_id' => $user->id,
            'type' => 'development',
            'status' => 'active',
        ]);

        $this->assertEventDispatched('session.started');

        // Send a message to the agent
        $message = $this->sessionService->sendMessage($session->id, [
            'content' => 'Please review this code:
                ```php
                function calculate($a, $b) {
                    return $a + $b;
                }
                ```',
            'type' => 'code_review',
        ]);

        $this->assertEventDispatched('message.sent');
        $this->assertJobDispatched('ProcessAgentMessage');

        // Wait for agent response
        $response = $this->sessionService->waitForResponse($session->id);

        $this->assertNotNull($response);
        $this->assertEventDispatched('message.received');

        // Verify agent feedback
        $this->assertArrayHasKey('suggestions', $response->data);
        $this->assertArrayHasKey('issues', $response->data);

        // Add task for agent
        $task = $this->agentService->assignTask($agent->id, [
            'type' => 'refactoring',
            'description' => 'Add type hints to the calculate function',
            'context' => [
                'code' => $message->content,
                'language' => 'php',
            ],
        ]);

        $this->assertEventDispatched('task.assigned');
        $this->assertJobDispatched('ProcessAgentTask');

        // Wait for task completion
        $result = $this->agentService->waitForTaskCompletion($task->id);

        $this->assertNotNull($result);
        $this->assertEventDispatched('task.completed');

        // Verify task result
        $this->assertStringContainsString(
            'function calculate(int|float $a, int|float $b): int|float',
            $result->data['code']
        );

        // End session
        $this->sessionService->end($session->id);

        $this->assertModelExists(Session::class, [
            'id' => $session->id,
            'status' => 'ended',
        ]);

        $this->assertEventDispatched('session.ended');

        // Verify metrics were recorded
        $this->assertMetricsRecorded('agent.performance', [
            'agent_id' => $agent->id,
            'session_id' => $session->id,
            'response_time' => true,
            'task_completion_time' => true,
        ]);

        // Verify audit logs were created
        $this->assertAuditLogCreated('agent.interaction', [
            'agent_id' => $agent->id,
            'session_id' => $session->id,
            'user_id' => $user->id,
            'organization_id' => $organization->id,
        ]);

        // Verify workflow completion
        $this->assertWorkflowCompleted('agent.development', [
            'agent_id' => $agent->id,
            'session_id' => $session->id,
            'task_id' => $task->id,
            'status' => 'success',
        ]);
    }

    /**
     * Test agent error handling.
     *
     * @return void
     */
    public function test_agent_error_handling(): void
    {
        // Create test data
        $agent = $this->createAgent();
        $user = $this->createUser();

        // Start session with invalid configuration
        try {
            $session = $this->sessionService->start([
                'agent_id' => $agent->id,
                'user_id' => $user->id,
                'type' => 'invalid_type',
            ]);
        } catch (\Exception $e) {
            $this->assertEventDispatched('session.failed');
            $this->assertWorkflowFailed('agent.development', 'Invalid session type');
        }

        // Send invalid message
        $session = $this->sessionService->start([
            'agent_id' => $agent->id,
            'user_id' => $user->id,
            'type' => 'development',
        ]);

        try {
            $message = $this->sessionService->sendMessage($session->id, [
                'content' => null,
                'type' => 'invalid_type',
            ]);
        } catch (\Exception $e) {
            $this->assertEventDispatched('message.failed');
            $this->assertWorkflowFailed('agent.message', 'Invalid message format');
        }

        // Assign invalid task
        try {
            $task = $this->agentService->assignTask($agent->id, [
                'type' => 'invalid_type',
                'description' => '',
            ]);
        } catch (\Exception $e) {
            $this->assertEventDispatched('task.failed');
            $this->assertWorkflowFailed('agent.task', 'Invalid task type');
        }

        // Verify error metrics were recorded
        $this->assertMetricsRecorded('agent.errors', [
            'agent_id' => $agent->id,
            'session_errors' => true,
            'message_errors' => true,
            'task_errors' => true,
        ]);
    }

    /**
     * Test agent performance monitoring.
     *
     * @return void
     */
    public function test_agent_performance_monitoring(): void
    {
        // Create test data
        $agent = $this->createAgent();
        $user = $this->createUser();

        // Run multiple interactions
        for ($i = 0; $i < 5; $i++) {
            $session = $this->sessionService->start([
                'agent_id' => $agent->id,
                'user_id' => $user->id,
                'type' => 'development',
            ]);

            $message = $this->sessionService->sendMessage($session->id, [
                'content' => "Test message {$i}",
                'type' => 'text',
            ]);

            $this->sessionService->waitForResponse($session->id);
            $this->sessionService->end($session->id);
        }

        // Verify performance metrics
        $metrics = $this->agentService->getPerformanceMetrics($agent->id);

        $this->assertArrayHasKey('average_response_time', $metrics);
        $this->assertArrayHasKey('message_count', $metrics);
        $this->assertArrayHasKey('session_count', $metrics);
        $this->assertArrayHasKey('error_rate', $metrics);
        $this->assertArrayHasKey('success_rate', $metrics);

        // Verify monitoring thresholds
        $this->assertTrue(
            $metrics['average_response_time'] <= config('anthropic.agents.performance.max_response_time')
        );

        $this->assertTrue(
            $metrics['error_rate'] <= config('anthropic.agents.performance.max_error_rate')
        );
    }
}
