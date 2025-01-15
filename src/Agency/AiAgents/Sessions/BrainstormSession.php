<?php

declare(Strict_types=1);

namespace Ajz\Anthropic\AIAgents\Sessions;

use Ajz\Anthropic\AIAgents\Communication\AgentMessage;
use Ajz\Anthropic\AIAgents\Communication\AgentMessageBroker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class BrainstormSession
{
    private Collection $ideas;
    private Collection $participants;
    private array $votingResults;
    private string $status = 'preparing';

    public function __construct(
        private readonly string $sessionId,
        private readonly string $topic,
        private readonly array $constraints,
        private readonly AgentMessageBroker $broker
    ) {
        $this->ideas = collect();
        $this->participants = collect();
        $this->votingResults = [];
    }

    public function addParticipant(string $agentId, array $expertise): void
    {
        $this->participants[$agentId] = [
            'expertise' => $expertise,
            'contributions' => 0,
            'last_activity' => now()
        ];
    }

    public function start(): void
    {
        $this->status = 'ideation';

        // Notify all participants to start generating ideas
        $this->broadcastMessage('start_ideation', [
            'topic' => $this->topic,
            'constraints' => $this->constraints,
            'current_ideas' => []
        ]);
    }

    public function submitIdea(string $agentId, array $idea): void
    {
        $this->ideas->push([
            'id' => uniqid('idea_'),
            'agent_id' => $agentId,
            'content' => $idea['content'],
            'reasoning' => $idea['reasoning'],
            'tags' => $idea['tags'] ?? [],
            'timestamp' => now(),
            'inspired_by' => $idea['inspired_by'] ?? null,
            'votes' => 0,
            'comments' => collect()
        ]);

        $this->participants[$agentId]['contributions']++;

        // Broadcast new idea to other participants
        $this->broadcastMessage('new_idea', [
            'idea' => $idea,
            'agent_id' => $agentId
        ]);
    }

    public function commentOnIdea(string $agentId, string $ideaId, string $comment): void
    {
        $idea = $this->ideas->firstWhere('id', $ideaId);
        if ($idea) {
            $idea['comments']->push([
                'agent_id' => $agentId,
                'content' => $comment,
                'timestamp' => now()
            ]);

            $this->broadcastMessage('new_comment', [
                'idea_id' => $ideaId,
                'comment' => $comment,
                'agent_id' => $agentId
            ]);
        }
    }

    public function buildUponIdea(string $agentId, string $ideaId, array $enhancement): void
    {
        $originalIdea = $this->ideas->firstWhere('id', $ideaId);
        if ($originalIdea) {
            $this->submitIdea($agentId, [
                'content' => $enhancement['content'],
                'reasoning' => $enhancement['reasoning'],
                'inspired_by' => $ideaId,
                'tags' => array_merge($originalIdea['tags'], $enhancement['new_tags'] ?? [])
            ]);
        }
    }

    public function startVoting(): void
    {
        $this->status = 'voting';

        // Prepare voting criteria based on session constraints
        $votingCriteria = $this->generateVotingCriteria();

        $this->broadcastMessage('start_voting', [
            'ideas' => $this->ideas,
            'criteria' => $votingCriteria
        ]);
    }

    public function submitVote(string $agentId, array $votes): void
    {
        $this->votingResults[$agentId] = $votes;

        // Check if all participants have voted
        if (count($this->votingResults) === $this->participants->count()) {
            $this->finalizeVoting();
        }
    }

    private function finalizeVoting(): void
    {
        $this->status = 'synthesis';

        // Calculate final scores
        $finalScores = $this->calculateFinalScores();

        // Select top ideas
        $topIdeas = $this->selectTopIdeas($finalScores);

        // Generate synthesis
        $synthesis = $this->synthesizeResults($topIdeas);

        $this->broadcastMessage('session_complete', [
            'top_ideas' => $topIdeas,
            'synthesis' => $synthesis,
            'voting_results' => $finalScores
        ]);
    }

    private function broadcastMessage(string $type, array $payload): void
    {
        $message = new AgentMessage(
            senderId: 'brainstorm_session',
            content: json_encode([
                'type' => $type,
                'session_id' => $this->sessionId,
                'payload' => $payload
            ]),
            metadata: [
                'session_id' => $this->sessionId,
                'message_type' => $type
            ],
            requiredCapabilities: ['brainstorming']
        );

        $this->broker->routeMessage($message);
    }

    private function generateVotingCriteria(): array
    {
        return [
            'innovation' => [
                'weight' => 0.3,
                'description' => 'How innovative and original is the idea?'
            ],
            'feasibility' => [
                'weight' => 0.3,
                'description' => 'How feasible is the implementation?'
            ],
            'impact' => [
                'weight' => 0.4,
                'description' => 'What potential impact could this idea have?'
            ]
        ];
    }

    private function calculateFinalScores(): array
    {
        $scores = [];
        foreach ($this->ideas as $idea) {
            $scores[$idea['id']] = $this->calculateIdeaScore($idea);
        }
        return $scores;
    }

    private function calculateIdeaScore(array $idea): float
    {
        $totalScore = 0;
        $criteria = $this->generateVotingCriteria();

        foreach ($this->votingResults as $agentVotes) {
            if (isset($agentVotes[$idea['id']])) {
                $vote = $agentVotes[$idea['id']];
                foreach ($criteria as $criterion => $details) {
                    $totalScore += ($vote[$criterion] ?? 0) * $details['weight'];
                }
            }
        }

        return $totalScore / count($this->votingResults);
    }

    private function selectTopIdeas(array $scores): array
    {
        arsort($scores);
        $topIds = array_slice(array_keys($scores), 0, 3);

        return $this->ideas
            ->whereIn('id', $topIds)
            ->map(fn($idea) => array_merge($idea, ['final_score' => $scores[$idea['id']]])
            ->toArray());
    }

    private function synthesizeResults(array $topIdeas): array
    {
        // Group related ideas
        $groups = [];
        foreach ($topIdeas as $idea) {
            $mainTag = $idea['tags'][0] ?? 'uncategorized';
            $groups[$mainTag][] = $idea;
        }

        // Create synthesis
        return [
            'main_themes' => array_keys($groups),
            'key_insights' => $this->extractKeyInsights($topIdeas),
            'potential_combinations' => $this->findPotentialCombinations($topIdeas),
            'next_steps' => $this->suggestNextSteps($topIdeas)
        ];
    }

    private function extractKeyInsights(array $ideas): array
    {
        $insights = [];
        foreach ($ideas as $idea) {
            $insights[] = [
                'idea_summary' => substr($idea['content'], 0, 100),
                'key_points' => explode("\n", $idea['reasoning']),
                'potential_impact' => $idea['impact'] ?? 'Unknown'
            ];
        }
        return $insights;
    }

    private function findPotentialCombinations(array $ideas): array
    {
        $combinations = [];
        foreach ($ideas as $i => $idea1) {
            foreach (array_slice($ideas, $i + 1) as $idea2) {
                if ($this->ideasAreCompatible($idea1, $idea2)) {
                    $combinations[] = [
                        'ideas' => [$idea1['id'], $idea2['id']],
                        'rationale' => $this->generateCombinationRationale($idea1, $idea2)
                    ];
                }
            }
        }
        return $combinations;
    }

    private function suggestNextSteps(array $ideas): array
    {
        return [
            'validation_needed' => $this->identifyValidationNeeds($ideas),
            'research_areas' => $this->identifyResearchAreas($ideas),
            'implementation_path' => $this->suggestImplementationPath($ideas)
        ];
    }
}
