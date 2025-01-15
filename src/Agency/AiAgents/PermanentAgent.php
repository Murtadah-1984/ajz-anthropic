<?php

namespace App\AIAgents;

use App\Models\AIAssistant as AIAssistantModel;
use App\Models\Conversation;
use Ajz\Anthropic\Services\AnthropicClaudeApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

abstract class PermanentAgent
{
    protected AIAssistantModel $model;
    protected array $memory = [];
    protected array $context = [];
    protected string $role;
    protected array $capabilities = [];
    protected array $configuration = [];

    public function __construct(
        protected readonly AnthropicClaudeApiService $apiService,
        protected readonly string $agentId
    ) {
        $this->initializeAgent();
    }

    abstract protected function initializeCapabilities(): void;
    abstract protected function getSpecializedPrompt(): string;

    protected function initializeAgent(): void
    {
        $this->model = $this->loadOrCreateModel();
        $this->memory = $this->loadMemory();
        $this->initializeCapabilities();
    }

    protected function loadOrCreateModel(): AIAssistantModel
    {
        return Cache::remember(
            "permanent_agent:{$this->agentId}",
            3600,
            fn() => AIAssistantModel::findOrFail($this->agentId)
        );
    }

    protected function loadMemory(): array
    {
        return Cache::remember(
            "agent_memory:{$this->agentId}",
            3600,
            fn() => $this->model->memory ?? []
        );
    }

    public function handleRequest(Conversation $conversation, string $input): array
    {
        $this->updateContext($conversation);

        $response = $this->generateResponse($input);

        $this->updateMemory($conversation, $input, $response);

        return $response;
    }

    protected function generateResponse(string $input): array
    {
        $prompt = $this->buildPrompt();
        $messages = $this->buildMessages($input);

        return $this->apiService->createMessage([
            'prompt' => $prompt,
            'messages' => $messages,
            'context' => $this->context
        ]);
    }

    protected function buildPrompt(): string
    {
        return <<<EOT
{$this->getSystemPrompt()}

{$this->getSpecializedPrompt()}

Current Context:
{$this->formatContext()}

Agent Memory Summary:
{$this->summarizeMemory()}
EOT;
    }

    protected function getSystemPrompt(): string
    {
        return <<<EOT
You are a permanent AI assistant with the role of {$this->role}.
Your capabilities include:
{$this->formatCapabilities()}

Configuration:
{$this->formatConfiguration()}
EOT;
    }

    protected function buildMessages(string $input): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildPrompt()
            ]
        ];

        // Add relevant conversation history
        if (!empty($this->context['conversation_history'])) {
            foreach ($this->context['conversation_history'] as $message) {
                $messages[] = [
                    'role' => $message['role'],
                    'content' => $message['content']
                ];
            }
        }

        // Add current input
        $messages[] = [
            'role' => 'user',
            'content' => $input
        ];

        return $messages;
    }

    protected function updateContext(Conversation $conversation): void
    {
        $this->context = [
            'conversation_id' => $conversation->id,
            'conversation_history' => $this->getRelevantHistory($conversation),
            'learned_patterns' => $this->memory['learned_patterns'] ?? [],
            'current_capabilities' => $this->capabilities,
            'performance_metrics' => $this->memory['performance_metrics'] ?? []
        ];
    }

    protected function getRelevantHistory(Conversation $conversation): Collection
    {
        return $conversation->messages()
            ->latest()
            ->take(10)
            ->get()
            ->map(fn($message) => [
                'role' => $message->role,
                'content' => $message->content,
                'timestamp' => $message->created_at
            ]);
    }

    protected function updateMemory(
        Conversation $conversation,
        string $input,
        array $response
    ): void {
        $this->memory['conversation_history'][] = [
            'timestamp' => now()->toIso8601String(),
            'conversation_id' => $conversation->id,
            'input' => $input,
            'response' => $response['content']
        ];

        $this->memory['learned_patterns'] = $this->updateLearnedPatterns($input, $response);

        $this->saveMemory();
    }

    protected function updateLearnedPatterns(string $input, array $response): array
    {
        $patterns = $this->memory['learned_patterns'] ?? [];

        // Analyze input for patterns
        $newPatterns = $this->analyzeForPatterns($input, $response);

        return array_merge($patterns, $newPatterns);
    }



    protected function analyzeForPatterns(string $input, array $response): array
    {
        $patterns = [];

        // Recognize preference patterns
        if (str_contains(strtolower($input), ['prefer', 'like', 'want'])) {
            $patterns['preferences'][] = [
                'timestamp' => now()->toIso8601String(),
                'content' => $input,
                'type' => 'user_preference'
            ];
        }

        // Recognize domain-specific patterns
        foreach ($this->capabilities['knowledge_domains'] as $domain) {
            if ($this->containsDomainSpecificPatterns($input, $domain)) {
                $patterns['domain_interests'][] = [
                    'timestamp' => now()->toIso8601String(),
                    'domain' => $domain,
                    'content' => $input
                ];
            }
        }

        // Recognize interaction patterns
        $patterns['interaction_patterns'][] = [
            'timestamp' => now()->toIso8601String(),
            'time_of_day' => now()->format('H:i'),
            'day_of_week' => now()->format('l'),
            'complexity' => $this->assessInputComplexity($input)
        ];

        return $patterns;
    }

    protected function saveMemory(): void
    {
        // Trim memory if too large
        $this->trimMemoryIfNeeded();

        // Update model and cache
        $this->model->update(['memory' => $this->memory]);
        Cache::put("agent_memory:{$this->agentId}", $this->memory, 3600);
    }

    protected function trimMemoryIfNeeded(): void
    {
        // Keep only last 100 conversations
        if (isset($this->memory['conversation_history'])) {
            $this->memory['conversation_history'] = array_slice(
                $this->memory['conversation_history'],
                -100
            );
        }

        // Keep only recent patterns
        if (isset($this->memory['learned_patterns'])) {
            foreach ($this->memory['learned_patterns'] as $type => $patterns) {
                $this->memory['learned_patterns'][$type] = array_filter(
                    $patterns,
                    fn($pattern) => Carbon::parse($pattern['timestamp'])
                        ->isAfter(now()->subDays(30))
                );
            }
        }
    }

    protected function containsDomainSpecificPatterns(string $input, string $domain): bool
    {
        $domainPatterns = $this->getDomainPatterns($domain);
        foreach ($domainPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        return false;
    }

    protected function getDomainPatterns(string $domain): array
    {
        return config("ai_agents.domain_patterns.{$domain}", []);
    }

    protected function assessInputComplexity(string $input): int
    {
        $factors = [
            'length' => strlen($input),
            'sentence_count' => substr_count($input, '.'),
            'question_count' => substr_count($input, '?'),
            'technical_terms' => $this->countTechnicalTerms($input)
        ];

        $complexity = 1;

        if ($factors['length'] > 200) $complexity++;
        if ($factors['sentence_count'] > 3) $complexity++;
        if ($factors['question_count'] > 2) $complexity++;
        if ($factors['technical_terms'] > 5) $complexity++;

        return min($complexity, 5);
    }

    protected function countTechnicalTerms(string $input): int
    {
        $technicalTerms = $this->getTechnicalTerms();
        $count = 0;

        foreach ($technicalTerms as $term) {
            $count += substr_count(strtolower($input), strtolower($term));
        }

        return $count;
    }

    protected function getTechnicalTerms(): array
    {
        return array_merge(
            config('ai_agents.technical_terms.general', []),
            config("ai_agents.technical_terms.{$this->role}", [])
        );
    }

    public function updateConfiguration(array $newConfig): void
    {
        $this->configuration = array_merge($this->configuration, $newConfig);
        $this->model->update(['configuration' => $this->configuration]);
    }

    public function updateCapabilities(array $newCapabilities): void
    {
        $this->capabilities = array_merge($this->capabilities, $newCapabilities);
        $this->model->update(['capabilities' => $this->capabilities]);
    }

    public function getPerformanceMetrics(): array
    {
        return [
            'response_times' => $this->calculateResponseTimes(),
            'success_rate' => $this->calculateSuccessRate(),
            'pattern_effectiveness' => $this->evaluatePatternEffectiveness(),
            'memory_utilization' => $this->analyzeMemoryUtilization()
        ];
    }

    protected function calculateResponseTimes(): array
    {
        $history = $this->memory['conversation_history'] ?? [];
        $responseTimes = [];

        foreach ($history as $interaction) {
            if (isset($interaction['timestamp'])) {
                $responseTimes[] = $interaction['processing_time'] ?? 0;
            }
        }

        return [
            'average' => !empty($responseTimes) ? array_sum($responseTimes) / count($responseTimes) : 0,
            'min' => !empty($responseTimes) ? min($responseTimes) : 0,
            'max' => !empty($responseTimes) ? max($responseTimes) : 0
        ];
    }

    protected function calculateSuccessRate(): float
    {
        $history = $this->memory['conversation_history'] ?? [];
        $totalInteractions = count($history);
        $successfulInteractions = 0;

        foreach ($history as $interaction) {
            if (isset($interaction['success_score']) && $interaction['success_score'] >= 0.8) {
                $successfulInteractions++;
            }
        }

        return $totalInteractions > 0 ? ($successfulInteractions / $totalInteractions) * 100 : 0;
    }

    protected function evaluatePatternEffectiveness(): array
    {
        $patterns = $this->memory['learned_patterns'] ?? [];
        $effectiveness = [];

        foreach ($patterns as $type => $typePatterns) {
            $effectiveness[$type] = [
                'total_patterns' => count($typePatterns),
                'usage_frequency' => $this->calculatePatternUsage($typePatterns),
                'success_rate' => $this->calculatePatternSuccessRate($typePatterns)
            ];
        }

        return $effectiveness;
    }

    protected function analyzeMemoryUtilization(): array
    {
        return [
            'total_interactions' => count($this->memory['conversation_history'] ?? []),
            'pattern_count' => count($this->memory['learned_patterns'] ?? []),
            'memory_size' => strlen(serialize($this->memory)),
            'utilization_rate' => $this->calculateMemoryUtilizationRate()
        ];
    }

    protected function calculateMemoryUtilizationRate(): float
    {
        $totalPatterns = count($this->memory['learned_patterns'] ?? []);
        $recentInteractions = array_filter(
            $this->memory['conversation_history'] ?? [],
            fn($interaction) => Carbon::parse($interaction['timestamp'])
                ->isAfter(now()->subDays(7))
        );

        $patternUsage = 0;
        foreach ($recentInteractions as $interaction) {
            if (isset($interaction['patterns_used'])) {
                $patternUsage += count($interaction['patterns_used']);
            }
        }

        return $totalPatterns > 0 ? ($patternUsage / ($totalPatterns * count($recentInteractions))) * 100 : 0;
    }
}
