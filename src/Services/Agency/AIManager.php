<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Agency;


use Ajz\Anthropic\AIAgents\Communication\AgentMessageBroker;
use Ajz\Anthropic\Models\AIAssistant;
use Ajz\Anthropic\Models\Team;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

final class AIManager
{
    private array $loadedAgents = [];
    private array $loadedTeams = [];
    private AgentMessageBroker $messageBroker;

    public function __construct(AgentMessageBroker $messageBroker)
    {
        $this->messageBroker = $messageBroker;
    }

    /**
     * Get or create an AI agent instance
     */
    public function agent(string $type): object
    {
        return Cache::remember("ai_agent:{$type}", 3600, function () use ($type) {
            if (!isset($this->loadedAgents[$type])) {
                $this->loadedAgents[$type] = $this->createAgent($type);
            }

            return $this->loadedAgents[$type];
        });
    }

    /**
     * Get or create an AI team instance
     */
    public function team(string $teamId): object
    {
        return Cache::remember("ai_team:{$teamId}", 3600, function () use ($teamId) {
            if (!isset($this->loadedTeams[$teamId])) {
                $this->loadedTeams[$teamId] = $this->createTeam($teamId);
            }

            return $this->loadedTeams[$teamId];
        });
    }

    /**
     * Get the message broker instance
     */
    public function broker(): AgentMessageBroker
    {
        return $this->messageBroker;
    }

    /**
     * Create a new AI agent instance
     */
    private function createAgent(string $type): object
    {
        $agent = AIAssistant::where('code', $type)
            ->where('is_active', true)
            ->first();

        if (!$agent) {
            throw new InvalidArgumentException("AI Agent type '{$type}' not found");
        }

        $className = $agent->class_name ?? "App\\AIAgents\\{$type}Agent";

        if (!class_exists($className)) {
            throw new InvalidArgumentException("Agent class '{$className}' not found");
        }

        $instance = app($className, [
            'agentId' => $agent->id,
            'configuration' => $agent->configuration
        ]);

        // Register with message broker
        $this->messageBroker->registerAgent($agent->id, $agent->capabilities);

        return $instance;
    }

    /**
     * Create a new AI team instance
     */
    private function createTeam(string $teamId): object
    {
        $team = Team::with(['assistants' => function ($query) {
            $query->where('is_active', true);
        }])->findOrFail($teamId);

        $className = $team->team_class ?? "App\\AIAgents\\Teams\\{$team->code}Team";

        if (!class_exists($className)) {
            throw new InvalidArgumentException("Team class '{$className}' not found");
        }

        return app($className, [
            'team' => $team,
            'agents' => $team->assistants,
            'broker' => $this->messageBroker
        ]);
    }

    /**
     * Register the AI Facade in config/app.php aliases
     */
    public static function registerFacade(): void
    {
        $aliases = config('app.aliases', []);
        $aliases['AI'] = \App\Facades\AI::class;
        config(['app.aliases' => $aliases]);
    }

    public function startBrainstorming(string $topic, array $options = []): BrainstormSession
    {
        $sessionId = uniqid('brainstorm_');

        $session = new BrainstormSession(
            sessionId: $sessionId,
            topic: $topic,
            constraints: $options['constraints'] ?? [],
            broker: $this->messageBroker
        );

        // Add relevant agents based on topic and options
        $this->addRelevantAgents($session, $topic, $options);

        return $session;
    }

    private function addRelevantAgents(BrainstormSession $session, string $topic, array $options): void
    {
        // Get agents based on topic keywords
        $relevantAgents = $this->findRelevantAgents($topic);

        // Add specified agents if any
        if (isset($options['agents'])) {
            foreach ($options['agents'] as $agentType) {
                $agent = $this->agent($agentType);
                $session->addParticipant(
                    $agent->getId(),
                    $agent->getExpertise()
                );
            }
        } else {
            // Add automatically selected agents
            foreach ($relevantAgents as $agent) {
                $session->addParticipant(
                    $agent->getId(),
                    $agent->getExpertise()
                );
            }
        }
    }

    private function findRelevantAgents(string $topic): array
    {
        // Analyze topic for relevant expertise areas
        $keywords = $this->extractKeywords($topic);

        return AIAssistant::query()
            ->where('is_active', true)
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $query->orWhereJsonContains('capabilities->knowledge_domains', $keyword);
                }
            })
            ->limit(5)
            ->get()
            ->map(fn($model) => $this->agent($model->code))
            ->toArray();
    }

    private function extractKeywords(string $topic): array
    {
        // Simple keyword extraction (in production, use NLP)
        return array_filter(
            array_unique(
                str_word_count(strtolower($topic), 1)
            ),
            fn($word) => strlen($word) > 3
        );
    }

    public function createSession(string $type, array $options = []): BaseSession
    {
        $sessionClass = match($type) {
            'planning' => PlanningSession::class,
            'code_review' => CodeReviewSession::class,
            'architecture_review' => ArchitectureReviewSession::class,
            'tech_debt' => TechDebtSession::class,
            'security_audit' => SecurityAuditSession::class,
            'incident_response' => IncidentResponseSession::class,
            'performance' => PerformanceOptimizationSession::class,
            'estimation' => ProjectEstimationSession::class,
            'refactoring' => CodeRefactoringSession::class,
            'standup' => DailyStandupSession::class,
            'feature_discovery' => FeatureDiscoverySession::class,
            'system_design' => SystemDesignSession::class,
            'quality_assurance' => QualityAssuranceSession::class,
            'release_planning' => ReleasePlanningSession::class,
            'documentation' => DocumentationSprintSession::class,
            'skills_assessment' => TeamSkillsAssessmentSession::class,
            'innovation_lab' => InnovationLabSession::class,
            'database_optimization' => DatabaseOptimizationSession::class,
            'api_design' => APIDesignSession::class,
            'debt_prioritization' => TechnicalDebtPrioritizationSession::class,
            'system_migration' => SystemMigrationSession::class,
            'devops_optimization' => DevOpsOptimizationSession::class,
            'code_modernization' => CodeModernizationSession::class,
            'compliance_review' => ComplianceReviewSession::class,
            'knowledge_transfer' => KnowledgeTransferSession::class,
            default => throw new InvalidArgumentException("Unknown session type: {$type}")
        };

        return new $sessionClass($this->messageBroker, $options);
    }

}
