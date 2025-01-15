<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Agency;

use Ajz\Anthropic\Models\Team;
use Ajz\Anthropic\Models\AIAssistant;
use Ajz\Anthropic\Models\TaskDelegation;
use Ajz\Anthropic\Models\Conversation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AITeamService
{
    public function __construct(
        private readonly AnthropicClaudeApiService $apiService
    ) {}

    public function createTeam(string $name, string $code, array $configuration): Team
    {
        return DB::transaction(function () use ($name, $code, $configuration) {
            $team = Team::create([
                'name' => $name,
                'code' => $code,
                'description' => $configuration['description'] ?? null,
                'metadata' => [
                    'capabilities' => $configuration['capabilities'] ?? [],
                    'workflow' => $configuration['workflow'] ?? 'sequential',
                    'specialization' => $configuration['specialization'] ?? 'general'
                ]
            ]);

            // Create team assistants based on configuration
            $this->setupTeamAssistants($team, $configuration['assistants'] ?? []);

            return $team;
        });
    }

    private function setupTeamAssistants(Team $team, array $assistantConfigs): void
    {
        foreach ($assistantConfigs as $config) {
            AIAssistant::create([
                'name' => $config['name'],
                'code' => $team->code . '_' . $config['role'],
                'assistant_role_id' => $this->getAssistantRole($config['role'])->id,
                'team_id' => $team->id,
                'configuration' => $config['configuration'] ?? [],
                'capabilities' => $config['capabilities'] ?? [],
                'is_personal' => false
            ]);
        }
    }

    public function handleTeamTask(Team $team, string $task, array $context = []): Conversation
    {
        // Analyze task to determine the best assistant to start with
        $analysis = $this->analyzeTask($task);

        // Find the most suitable assistant in the team
        $assistant = $this->findSuitableAssistant($team, $analysis['required_capabilities']);

        // Create a new conversation
        $conversation = Conversation::create([
            'ai_assistant_id' => $assistant->id,
            'user_id' => $context['user_id'] ?? null,
            'subject' => $analysis['task_summary'],
            'status' => 'active',
            'metadata' => [
                'team_id' => $team->id,
                'task_analysis' => $analysis,
                'workflow' => $team->metadata['workflow'],
                'context' => $context
            ]
        ]);

        // Add initial task message
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $task
        ]);

        // Handle the task based on team workflow
        switch ($team->metadata['workflow']) {
            case 'sequential':
                $this->handleSequentialWorkflow($conversation, $analysis);
                break;
            case 'collaborative':
                $this->handleCollaborativeWorkflow($conversation, $analysis);
                break;
            default:
                $this->handleDefaultWorkflow($conversation, $analysis);
        }

        return $conversation;
    }

    private function analyzeTask(string $task): array
    {
        $response = $this->apiService->createMessage(
            messages: [
                [
                    'role' => 'system',
                    'content' => $this->getTaskAnalysisPrompt()
                ],
                [
                    'role' => 'user',
                    'content' => $task
                ]
            ]
        );

        return json_decode($response['content'], true);
    }

    private function findSuitableAssistant(Team $team, array $requiredCapabilities): AIAssistant
    {
        return $team->assistants()
            ->active()
            ->where(function ($query) use ($requiredCapabilities) {
                foreach ($requiredCapabilities as $capability) {
                    $query->whereJsonContains('capabilities->supported_tasks', $capability);
                }
            })
            ->orderBy('last_interaction')
            ->firstOrFail();
    }

    private function handleSequentialWorkflow(Conversation $conversation, array $analysis): void
    {
        $tasks = $analysis['subtasks'] ?? [];
        $currentIndex = 0;

        foreach ($tasks as $task) {
            $assistant = $this->findSuitableAssistant(
                $conversation->assistant->team,
                $task['required_capabilities']
            );

            // Create delegation record
            TaskDelegation::create([
                'conversation_id' => $conversation->id,
                'from_assistant_id' => $conversation->ai_assistant_id,
                'to_assistant_id' => $assistant->id,
                'reason' => $task['description'],
                'context' => [
                    'task_index' => $currentIndex,
                    'total_tasks' => count($tasks),
                    'dependencies' => $task['dependencies'] ?? []
                ],
                'status' => $currentIndex === 0 ? 'active' : 'pending'
            ]);

            $currentIndex++;
        }
    }

    private function handleCollaborativeWorkflow(Conversation $conversation, array $analysis): void
    {
        $assignments = $this->assignCollaborativeTasks(
            $conversation->assistant->team,
            $analysis['components'] ?? []
        );

        foreach ($assignments as $assignment) {
            TaskDelegation::create([
                'conversation_id' => $conversation->id,
                'from_assistant_id' => $conversation->ai_assistant_id,
                'to_assistant_id' => $assignment['assistant_id'],
                'reason' => $assignment['component']['description'],
                'context' => [
                    'component' => $assignment['component'],
                    'collaborators' => $assignments->pluck('assistant_id')->except($assignment['assistant_id'])
                ],
                'status' => 'active'
            ]);
        }
    }

    private function assignCollaborativeTasks(Team $team, array $components): Collection
    {
        return collect($components)->map(function ($component) use ($team) {
            return [
                'component' => $component,
                'assistant_id' => $this->findSuitableAssistant($team, $component['required_capabilities'])->id
            ];
        });

    }

        private function getTaskAnalysisPrompt(): string
        {
            return <<<EOT
    You are a task analysis specialist. Analyze the given task and provide a structured response with:
    {
        "task_summary": "Brief description of the task",
        "complexity": 1-5,
        "required_capabilities": ["capability1", "capability2"],
        "estimated_completion_time": "in hours",
        "subtasks": [
            {
                "description": "Subtask description",
                "required_capabilities": ["capability"],
                "dependencies": ["task_id"],
                "estimated_time": "in hours"
            }
        ],
        "components": [
            {
                "description": "Component description",
                "required_capabilities": ["capability"],
                "interfaces": ["other_component_ids"],
                "priority": 1-5
            }
        ],
        "risks": [
            {
                "description": "Risk description",
                "probability": 1-5,
                "impact": 1-5,
                "mitigation": "Mitigation strategy"
            }
        ]
    }
    EOT;
        }

        private function handleDefaultWorkflow(Conversation $conversation, array $analysis): void
        {
            // Create a single delegation to the most suitable assistant
            $assistant = $this->findSuitableAssistant(
                $conversation->assistant->team,
                $analysis['required_capabilities']
            );

            TaskDelegation::create([
                'conversation_id' => $conversation->id,
                'from_assistant_id' => $conversation->ai_assistant_id,
                'to_assistant_id' => $assistant->id,
                'reason' => $analysis['task_summary'],
                'context' => [
                    'analysis' => $analysis,
                    'workflow_type' => 'default'
                ],
                'status' => 'active'
            ]);
        }

        public function monitorTeamProgress(Team $team): array
        {
            $metrics = [
                'active_conversations' => $this->getActiveConversationsMetrics($team),
                'assistant_performance' => $this->getAssistantPerformanceMetrics($team),
                'task_completion' => $this->getTaskCompletionMetrics($team),
                'workflow_efficiency' => $this->getWorkflowEfficiencyMetrics($team)
            ];

            return $this->analyzeTeamMetrics($metrics);
        }

        private function getActiveConversationsMetrics(Team $team): array
        {
            $conversations = Conversation::where('status', 'active')
                ->whereHas('assistant', function ($query) use ($team) {
                    $query->where('team_id', $team->id);
                })
                ->with(['messages', 'delegations'])
                ->get();

            return [
                'count' => $conversations->count(),
                'average_duration' => $conversations->avg(function ($conv) {
                    return $conv->created_at->diffInMinutes(now());
                }),
                'complexity_distribution' => $this->analyzeComplexityDistribution($conversations),
                'delegation_patterns' => $this->analyzeDelegationPatterns($conversations)
            ];
        }

        private function getAssistantPerformanceMetrics(Team $team): array
        {
            return $team->assistants()
                ->with(['conversations', 'delegatedTasks', 'receivedTasks'])
                ->get()
                ->map(function ($assistant) {
                    return [
                        'assistant_id' => $assistant->id,
                        'name' => $assistant->name,
                        'metrics' => [
                            'tasks_completed' => $assistant->conversations()
                                ->where('status', 'completed')
                                ->count(),
                            'average_response_time' => $this->calculateAverageResponseTime($assistant),
                            'delegation_rate' => $this->calculateDelegationRate($assistant),
                            'success_rate' => $this->calculateSuccessRate($assistant)
                        ]
                    ];
                })
                ->toArray();
        }

        private function getTaskCompletionMetrics(Team $team): array
        {
            $tasks = TaskDelegation::whereHas('conversation', function ($query) use ($team) {
                $query->whereHas('assistant', function ($q) use ($team) {
                    $q->where('team_id', $team->id);
                });
            })->get();

            return [
                'completion_rate' => $this->calculateCompletionRate($tasks),
                'average_completion_time' => $this->calculateAverageCompletionTime($tasks),
                'bottlenecks' => $this->identifyBottlenecks($tasks),
                'success_patterns' => $this->identifySuccessPatterns($tasks)
            ];
        }

        private function getWorkflowEfficiencyMetrics(Team $team): array
        {
            return [
                'sequential_efficiency' => $this->analyzeSequentialWorkflowEfficiency($team),
                'collaborative_efficiency' => $this->analyzeCollaborativeWorkflowEfficiency($team),
                'resource_utilization' => $this->analyzeResourceUtilization($team),
                'optimization_opportunities' => $this->identifyOptimizationOpportunities($team)
            ];
        }

        private function calculateAverageResponseTime(AIAssistant $assistant): float
        {
            return $assistant->conversations()
                ->with('messages')
                ->get()
                ->avg(function ($conversation) {
                    $messages = $conversation->messages->sortBy('created_at');
                    $responseTimes = [];

                    $messages->each(function ($message, $index) use ($messages, &$responseTimes) {
                        if ($message->role === 'assistant' && $index > 0) {
                            $userMessage = $messages[$index - 1];
                            $responseTimes[] = $message->created_at->diffInSeconds($userMessage->created_at);
                        }
                    });

                    return !empty($responseTimes) ? array_sum($responseTimes) / count($responseTimes) : 0;
                });
        }

        private function calculateDelegationRate(AIAssistant $assistant): float
        {
            $totalTasks = $assistant->conversations()->count();
            $delegatedTasks = $assistant->delegatedTasks()->count();

            return $totalTasks > 0 ? ($delegatedTasks / $totalTasks) * 100 : 0;
        }

        private function calculateSuccessRate(AIAssistant $assistant): float
        {
            $completedTasks = $assistant->conversations()
                ->where('status', 'completed')
                ->count();
            $totalTasks = $assistant->conversations()->count();

            return $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;
        }

        private function identifyBottlenecks(Collection $tasks): array
        {
            $bottlenecks = [];

            // Analyze task duration patterns
            $tasks->groupBy('to_assistant_id')
                ->each(function ($assistantTasks, $assistantId) use (&$bottlenecks) {
                    $avgDuration = $assistantTasks->avg(function ($task) {
                        return $task->updated_at->diffInMinutes($task->created_at);
                    });

                    if ($avgDuration > 60) { // More than 1 hour average
                        $bottlenecks[] = [
                            'assistant_id' => $assistantId,
                            'avg_duration' => $avgDuration,
                            'task_count' => $assistantTasks->count(),
                            'type' => 'duration'
                        ];
                    }
                });

            // Analyze delegation chains
            $delegationChains = $this->analyzeDelegationChains($tasks);
            foreach ($delegationChains as $chain) {
                if (count($chain) > 3) { // More than 3 delegations
                    $bottlenecks[] = [
                        'type' => 'delegation_chain',
                        'chain' => $chain,
                        'length' => count($chain)
                    ];
                }
            }

            return $bottlenecks;
        }

        private function identifyOptimizationOpportunities(Team $team): array
        {
            $opportunities = [];

            // Analyze workload distribution
            $workloadDistribution = $this->analyzeWorkloadDistribution($team);
            if ($workloadDistribution['std_deviation'] > 0.3) { // High workload imbalance
                $opportunities[] = [
                    'type' => 'workload_balancing',
                    'description' => 'Significant workload imbalance detected',
                    'metrics' => $workloadDistribution
                ];
            }

            // Analyze skill utilization
            $skillUtilization = $this->analyzeSkillUtilization($team);
            foreach ($skillUtilization as $skill => $usage) {
                if ($usage < 0.3) { // Under-utilized skills
                    $opportunities[] = [
                        'type' => 'skill_utilization',
                        'skill' => $skill,
                        'usage_rate' => $usage,
                        'recommendation' => 'Consider redistributing tasks to better utilize this skill'
                    ];
                }
            }

            // Analyze workflow patterns
            $workflowPatterns = $this->analyzeWorkflowPatterns($team);
            foreach ($workflowPatterns as $pattern) {
                if ($pattern['efficiency'] < 0.7) { // Inefficient workflow patterns
                    $opportunities[] = [
                        'type' => 'workflow_optimization',
                        'pattern' => $pattern['name'],
                        'current_efficiency' => $pattern['efficiency'],
                        'recommendation' => $pattern['optimization_suggestion']
                    ];
                }
            }

            return $opportunities;
        }

        private function analyzeDelegationChains(Collection $tasks): array
        {
            $chains = [];
            $tasks->groupBy('conversation_id')->each(function ($conversationTasks) use (&$chains) {
                $chain = [];
                $sortedTasks = $conversationTasks->sortBy('created_at');

                foreach ($sortedTasks as $task) {
                    $chain[] = [
                        'from' => $task->from_assistant_id,
                        'to' => $task->to_assistant_id,
                        'duration' => $task->updated_at->diffInMinutes($task->created_at)
                    ];
                }

                if (!empty($chain)) {
                    $chains[] = $chain;
                }
            });

            return $chains;
        }

        // ... Additional helper methods for metrics analysis

}
