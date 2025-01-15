<?php

namespace Ajz\Anthropic\AIAgents\Sessions;

use Ajz\Anthropic\Facades\AI;
use Ajz\Anthropic\AIAgents\Communication\AgentMessage;

class CollaborativeSession
{
    protected $participants = [];
    protected $conversation = [];
    protected $topic;
    protected $meetingState = 'planning';

    public function __construct(string $topic, array $participants)
    {
        $this->topic = $topic;
        foreach ($participants as $role) {
            $this->participants[$role] = AI::agent($role);
        }
    }

    public function conductMeeting(): array
    {
        // Start with meeting planning
        $this->facilitateDiscussion([
            'topic' => $this->topic,
            'phase' => 'ideation',
            'expectations' => 'Open brainstorming and creative solutions'
        ]);

        // Discussion rounds
        foreach ($this->participants as $role => $agent) {
            $this->handleParticipantTurn($role);
        }

        // Synthesis phase
        $this->synthesizeIdeas();

        return $this->conversation;
    }

    protected function handleParticipantTurn(string $role): void
    {
        $currentAgent = $this->participants[$role];
        $context = $this->buildContextForAgent($role);

        $message = new AgentMessage(
            senderId: $role,
            content: "Based on our discussion so far, here are my thoughts...",
            metadata: [
                'phase' => $this->meetingState,
                'context' => $context,
                'requires_response' => true
            ]
        );

        // Get responses from other participants
        foreach ($this->participants as $respondingRole => $respondingAgent) {
            if ($respondingRole !== $role) {
                $response = $respondingAgent->receiveMessage($message);
                $this->conversation[] = [
                    'from' => $respondingRole,
                    'to' => $role,
                    'content' => $response->content,
                    'type' => 'response'
                ];
            }
        }
    }

    protected function facilitateDiscussion(array $context): void
    {
        foreach ($this->participants as $role => $agent) {
            $contribution = $agent->handleRequest([
                'type' => 'meeting_contribution',
                'context' => $context,
                'current_phase' => $this->meetingState,
                'conversation_history' => $this->conversation
            ]);

            $this->conversation[] = [
                'from' => $role,
                'type' => 'contribution',
                'content' => $contribution
            ];
        }
    }

    protected function synthesizeIdeas(): void
    {
        $this->meetingState = 'synthesis';

        // Have the architect summarize and synthesize
        $architect = $this->participants['architect'] ?? $this->participants[array_key_first($this->participants)];

        $synthesis = $architect->handleRequest([
            'type' => 'synthesis',
            'conversation_history' => $this->conversation,
            'required_output' => [
                'key_insights',
                'action_items',
                'next_steps'
            ]
        ]);

        $this->conversation[] = [
            'type' => 'synthesis',
            'content' => $synthesis
        ];
    }

    protected function buildContextForAgent(string $role): array
    {
        return [
            'meeting_topic' => $this->topic,
            'current_phase' => $this->meetingState,
            'conversation_history' => $this->conversation,
            'participant_role' => $role,
            'other_participants' => array_keys(array_diff_key($this->participants, [$role => true]))
        ];
    }
}
