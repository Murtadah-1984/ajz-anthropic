<?php

namespace Ajz\Anthropic\AIAgents\Sessions;

use Ajz\Anthropic\AIAgents\Communication\AgentMessage;
use Ajz\Anthropic\Models\ComplianceReport;
use Ajz\Anthropic\Models\SessionArtifact;
use Illuminate\Support\Collection;

class ComplianceReviewSession extends BaseSession
{
    /**
     * Compliance requirements to check against.
     *
     * @var Collection
     */
    protected Collection $requirements;

    /**
     * Compliance violations found.
     *
     * @var Collection
     */
    protected Collection $violations;

    /**
     * Recommended actions.
     *
     * @var Collection
     */
    protected Collection $recommendations;

    public function __construct(
        protected readonly AgentMessageBroker $broker,
        protected readonly array $configuration = []
    ) {
        parent::__construct($broker, $configuration);
        $this->requirements = collect();
        $this->violations = collect();
        $this->recommendations = collect();
    }

    public function start(): void
    {
        $this->status = 'compliance_review';

        $steps = [
            'requirements_gathering',
            'code_analysis',
            'documentation_review',
            'security_assessment',
            'data_privacy_review',
            'licensing_check',
            'report_generation',
            'remediation_planning'
        ];

        foreach ($steps as $step) {
            $this->processStep($step);
            $this->trackProgress($step);
        }
    }

    protected function processStep(string $step): void
    {
        $stepResult = match($step) {
            'requirements_gathering' => $this->gatherRequirements(),
            'code_analysis' => $this->analyzeCode(),
            'documentation_review' => $this->reviewDocumentation(),
            'security_assessment' => $this->assessSecurity(),
            'data_privacy_review' => $this->reviewDataPrivacy(),
            'licensing_check' => $this->checkLicensing(),
            'report_generation' => $this->generateReport(),
            'remediation_planning' => $this->planRemediation()
        };

        $this->storeStepArtifacts($step, $stepResult);
    }

    private function gatherRequirements(): array
    {
        $message = new AgentMessage(
            senderId: $this->sessionId,
            content: json_encode([
                'task' => 'compliance_requirements_gathering',
                'context' => $this->configuration
            ]),
            metadata: [
                'session_type' => 'compliance_review',
                'step' => 'requirements_gathering'
            ],
            requiredCapabilities: ['compliance_analysis', 'regulatory_knowledge']
        );

        $requirements = $this->broker->routeMessageAndWait($message);
        $this->requirements = collect($requirements['requirements']);

        return $requirements;
    }

    private function analyzeCode(): array
    {
        $analysis = $this->broker->routeMessageAndWait(new AgentMessage(
            senderId: $this->sessionId,
            content: json_encode([
                'task' => 'code_compliance_analysis',
                'requirements' => $this->requirements->toArray(),
                'codebase' => $this->configuration['codebase_path']
            ]),
            metadata: ['step' => 'code_analysis'],
            requiredCapabilities: ['code_analysis', 'compliance_checking']
        ));

        foreach ($analysis['violations'] as $violation) {
            $this->violations->push($violation);
        }

        return $analysis;
    }

    private function reviewDocumentation(): array
    {
        return $this->broker->routeMessageAndWait(new AgentMessage(
            senderId: $this->sessionId,
            content: json_encode([
                'task' => 'documentation_compliance_review',
                'requirements' => $this->requirements->toArray(),
                'docs_path' => $this->configuration['documentation_path']
            ]),
            metadata: ['step' => 'documentation_review'],
            requiredCapabilities: ['documentation_analysis', 'compliance_checking']
        ));
    }

    private function assessSecurity(): array
    {
        return $this->broker->routeMessageAndWait(new AgentMessage(
            senderId: $this->sessionId,
            content: json_encode([
                'task' => 'security_compliance_assessment',
                'requirements' => $this->requirements->toArray(),
                'security_config' => $this->configuration['security_settings']
            ]),
            metadata: ['step' => 'security_assessment'],
            requiredCapabilities: ['security_analysis', 'compliance_checking']
        ));
    }

    private function reviewDataPrivacy(): array
    {
        return $this->broker->routeMessageAndWait(new AgentMessage(
            senderId: $this->sessionId,
            content: json_encode([
                'task' => 'data_privacy_review',
                'requirements' => $this->requirements->toArray(),
                'data_flows' => $this->configuration['data_flow_diagrams']
            ]),
            metadata: ['step' => 'data_privacy_review'],
            requiredCapabilities: ['privacy_analysis', 'compliance_checking']
        ));
    }

    private function checkLicensing(): array
    {
        return $this->broker->routeMessageAndWait(new AgentMessage(
            senderId: $this->sessionId,
            content: json_encode([
                'task' => 'license_compliance_check',
                'requirements' => $this->requirements->toArray(),
                'dependencies' => $this->configuration['dependencies']
            ]),
            metadata: ['step' => 'licensing_check'],
            requiredCapabilities: ['license_analysis', 'compliance_checking']
        ));
    }

    private function generateReport(): array
    {
        $report = [
            'summary' => $this->generateSummary(),
            'violations' => $this->violations->toArray(),
            'recommendations' => $this->recommendations->toArray(),
            'compliance_status' => $this->calculateComplianceStatus(),
            'risk_assessment' => $this->assessRisks(),
            'remediation_plan' => $this->getStepArtifacts('remediation_planning'),
            'attestations' => $this->generateAttestations()
        ];

        ComplianceReport::create([
            'session_id' => $this->sessionId,
            'content' => $report,
            'status' => $report['compliance_status'],
            'metadata' => [
                'requirements' => $this->requirements->toArray(),
                'timestamp' => now(),
                'version' => $this->configuration['version'] ?? '1.0.0'
            ]
        ]);

        return $report;
    }

    private function planRemediation(): array
    {
        return $this->broker->routeMessageAndWait(new AgentMessage(
            senderId: $this->sessionId,
            content: json_encode([
                'task' => 'remediation_planning',
                'violations' => $this->violations->toArray(),
                'context' => [
                    'priority' => $this->configuration['priority'] ?? 'high',
                    'timeline' => $this->configuration['timeline'] ?? '30 days',
                    'resources' => $this->configuration['available_resources']
                ]
            ]),
            metadata: ['step' => 'remediation_planning'],
            requiredCapabilities: ['remediation_planning', 'resource_management']
        ));
    }

    private function generateSummary(): array
    {
        return [
            'total_requirements' => $this->requirements->count(),
            'total_violations' => $this->violations->count(),
            'compliance_score' => $this->calculateComplianceScore(),
            'critical_issues' => $this->violations->where('severity', 'critical')->count(),
            'high_issues' => $this->violations->where('severity', 'high')->count(),
            'medium_issues' => $this->violations->where('severity', 'medium')->count(),
            'low_issues' => $this->violations->where('severity', 'low')->count()
        ];
    }

    private function calculateComplianceScore(): float
    {
        $totalRequirements = $this->requirements->count();
        if ($totalRequirements === 0) {
            return 100.0;
        }

        $violationWeights = [
            'critical' => 1.0,
            'high' => 0.7,
            'medium' => 0.4,
            'low' => 0.1
        ];

        $weightedViolations = $this->violations->sum(function ($violation) use ($violationWeights) {
            return $violationWeights[$violation['severity']] ?? 0;
        });

        return max(0, 100 - ($weightedViolations / $totalRequirements) * 100);
    }

    private function calculateComplianceStatus(): string
    {
        $score = $this->calculateComplianceScore();
        $criticalIssues = $this->violations->where('severity', 'critical')->count();

        return match(true) {
            $criticalIssues > 0 => 'non_compliant',
            $score >= 90 => 'compliant',
            $score >= 75 => 'partially_compliant',
            default => 'non_compliant'
        };
    }

    private function assessRisks(): array
    {
        return [
            'risk_levels' => [
                'security' => $this->calculateRiskLevel('security'),
                'privacy' => $this->calculateRiskLevel('privacy'),
                'legal' => $this->calculateRiskLevel('legal'),
                'operational' => $this->calculateRiskLevel('operational')
            ],
            'risk_factors' => $this->identifyRiskFactors(),
            'mitigation_status' => $this->assessMitigationStatus()
        ];
    }

    private function calculateRiskLevel(string $category): string
    {
        $categoryViolations = $this->violations->filter(fn($v) => $v['category'] === $category);
        $score = $this->calculateCategoryRiskScore($categoryViolations);

        return match(true) {
            $score >= 80 => 'critical',
            $score >= 60 => 'high',
            $score >= 40 => 'medium',
            $score >= 20 => 'low',
            default => 'minimal'
        };
    }

    private function calculateCategoryRiskScore(Collection $violations): float
    {
        if ($violations->isEmpty()) {
            return 0.0;
        }

        $weights = [
            'critical' => 100,
            'high' => 70,
            'medium' => 40,
            'low' => 20
        ];

        $totalWeight = array_sum($weights);
        $score = $violations->sum(fn($v) => $weights[$v['severity']] ?? 0);

        return ($score / $totalWeight) * 100;
    }

    private function identifyRiskFactors(): array
    {
        return $this->violations->groupBy('category')->map(function ($violations, $category) {
            return [
                'category' => $category,
                'count' => $violations->count(),
                'factors' => $violations->pluck('risk_factors')->flatten()->unique()->values()->toArray()
            ];
        })->values()->toArray();
    }

    private function assessMitigationStatus(): array
    {
        return [
            'mitigated' => $this->violations->where('status', 'mitigated')->count(),
            'in_progress' => $this->violations->where('status', 'in_progress')->count(),
            'planned' => $this->violations->where('status', 'planned')->count(),
            'not_started' => $this->violations->where('status', 'not_started')->count()
        ];
    }

    private function generateAttestations(): array
    {
        return [
            'reviewer' => [
                'id' => $this->sessionId,
                'type' => 'compliance_ai_agent',
                'timestamp' => now()->toIso8601String()
            ],
            'standards' => $this->requirements->pluck('standard')->unique()->values()->toArray(),
            'methodology' => [
                'steps' => [
                    'requirements_gathering',
                    'code_analysis',
                    'documentation_review',
                    'security_assessment',
                    'data_privacy_review',
                    'licensing_check'
                ],
                'tools' => $this->configuration['tools'] ?? [],
                'version' => $this->configuration['version'] ?? '1.0.0'
            ]
        ];
    }

    private function storeStepArtifacts(string $step, array $artifacts): void
    {
        SessionArtifact::create([
            'session_id' => $this->sessionId,
            'step' => $step,
            'content' => $artifacts,
            'metadata' => [
                'timestamp' => now(),
                'status' => 'completed'
            ]
        ]);
    }

    private function getStepArtifacts(string $step): ?array
    {
        return SessionArtifact::where('session_id', $this->sessionId)
            ->where('step', $step)
            ->first()
            ?->content;
    }

    public function getReport(): array
    {
        return ComplianceReport::where('session_id', $this->sessionId)
            ->latest()
            ->first()
            ?->content ?? [];
    }

    public function getViolations(): Collection
    {
        return $this->violations;
    }

    public function getRecommendations(): Collection
    {
        return $this->recommendations;
    }
}
