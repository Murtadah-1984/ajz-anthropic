<?php

declare(strict_types=1);

namespace Ajz\Anthropic\AIAgents\Communication;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Queue;

final class AsyncAgentCommunication implements ShouldQueue
{
    public function handle(AgentMessage $message, string $targetAgent): void
    {
        Queue::push(new ProcessAgentMessage($message, $targetAgent));
    }
}

class ProcessAgentMessage implements ShouldQueue
{
    public function __construct(
        private readonly AgentMessage $message,
        private readonly string $targetAgent
    ) {}

    public function handle(AgentMessageBroker $broker): void
    {
        $agent = app('ai.manager')->getAgent($this->targetAgent);
        $response = $agent->processMessage($this->message);

        // Check if further communication is needed
        if ($response->requiresFollowUp()) {
            $this->handleFollowUp($response, $broker);
        }
    }

    private function handleFollowUp(AgentResponse $response, AgentMessageBroker $broker): void
    {
        foreach ($response->getFollowUpMessages() as $message) {
            $broker->routeMessage($message);
        }
    }
}

class AgentResponse
{
    private array $followUpMessages = [];

    public function __construct(
        private readonly string $content,
        private readonly array $metadata = [],
        private readonly bool $requiresFollowUp = false
    ) {}

    public function requiresFollowUp(): bool
    {
        return $this->requiresFollowUp;
    }

    public function addFollowUpMessage(AgentMessage $message): void
    {
        $this->followUpMessages[] = $message;
    }

    public function getFollowUpMessages(): array
    {
        return $this->followUpMessages;
    }
}

// Example of agent-to-agent communication
namespace App\AIAgents\Examples;

use App\Facades\AI;
use App\AIAgents\Communication\AgentMessage;

class ProjectManager
{
    public function planProject(string $description): void
    {
        // Create initial architecture plan
        $architectResponse = AI::agent('architect')->createPlan($description);

        // Send to developer for implementation details
        $developerMessage = new AgentMessage(
            senderId: 'architect',
            content: $architectResponse->content,
            metadata: ['type' => 'implementation_request'],
            requiredCapabilities: ['code_generation', 'system_design']
        );

        AI::agent('developer')->receiveMessage($developerMessage);

        // Send to security expert for review
        $securityMessage = new AgentMessage(
            senderId: 'architect',
            content: $architectResponse->content,
            metadata: ['type' => 'security_review'],
            requiredCapabilities: ['security_analysis']
        );

        AI::agent('security')->receiveMessage($securityMessage);
    }
}
