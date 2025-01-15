<?php

namespace Ajz\Anthropic\AIAgents\Sessions;

use Ajz\Anthropic\AIAgents\Communication\AgentMessage;
use Ajz\Anthropic\AIAgents\Documents\OpenAPISpecification;
use Ajz\Anthropic\Models\SessionArtifact;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class APIDesignSession extends BaseSession
{
    private Collection $endpoints;
    private Collection $models;
    private array $securitySchemes;
    private OpenAPISpecification $specification;

    public function __construct(
        protected readonly AgentMessageBroker $broker,
        protected readonly array $configuration = []
    ) {
        parent::__construct($broker, $configuration);
        $this->endpoints = collect();
        $this->models = collect();
        $this->securitySchemes = [];
        $this->specification = new OpenAPISpecification();
    }

    public function start(): void
    {
        $this->status = 'api_design';

        $steps = [
            'requirements_analysis',
            'resource_identification',
            'endpoint_design',
            'data_model_definition',
            'security_scheme_design',
            'documentation_generation',
            'specification_review',
            'developer_experience_review'
        ];

        foreach ($steps as $step) {
            $this->processStep($step);
            $this->trackProgress($step);
        }
    }

    protected function processStep(string $step): void
    {
        $stepResult = match($step) {
            'requirements_analysis' => $this->analyzeRequirements(),
            'resource_identification' => $this->identifyResources(),
            'endpoint_design' => $this->designEndpoints(),
            'data_model_definition' => $this->defineDataModels(),
            'security_scheme_design' => $this->designSecuritySchemes(),
            'documentation_generation' => $this->generateDocumentation(),
            'specification_review' => $this->reviewSpecification(),
            'developer_experience_review' => $this->reviewDeveloperExperience()
        };

        $this->storeStepArtifacts($step, $stepResult);
    }

    private function analyzeRequirements(): array
    {
        $message = new AgentMessage(
            senderId: $this->sessionId,
            content: json_encode([
                'task' => 'api_requirements_analysis',
                'context' => $this->configuration
            ]),
            metadata: [
                'session_type' => 'api_design',
                'step' => 'requirements_analysis'
            ],
            requiredCapabilities: ['api_design', 'requirements_analysis']
        );

        $analysis = $this->broker->routeMessageAndWait($message);

        return [
            'requirements' => $analysis['requirements'],
            'constraints' => $analysis['constraints'],
            'stakeholder_needs' => $analysis['stakeholder_needs']
        ];
    }

    private function identifyResources(): array
    {
        $resources = $this->broker->routeMessageAndWait(new AgentMessage(
            senderId: $this->sessionId,
            content: json_encode([
                'task' => 'resource_identification',
                'previous_analysis' => $this->getStepArtifacts('requirements_analysis')
            ]),
            metadata: ['step' => 'resource_identification'],
            requiredCapabilities: ['api_design', 'domain_modeling']
        ));

        foreach ($resources['entities'] as $resource) {
            $this->models->put($resource['name'], $resource);
        }

        return $resources;
    }

    private function designEndpoints(): array
    {
        $endpoints = $this->broker->routeMessageAndWait(new AgentMessage(
            senderId: $this->sessionId,
            content: json_encode([
                'task' => 'endpoint_design',
                'resources' => $this->models->toArray()
            ]),
            metadata: ['step' => 'endpoint_design'],
            requiredCapabilities: ['api_design', 'rest_design']
        ));

        foreach ($endpoints['endpoints'] as $endpoint) {
            $this->endpoints->put($endpoint['path'], $endpoint);
        }

        return $endpoints;
    }

    private function defineDataModels(): array
    {
        $models = $this->broker->routeMessageAndWait(new AgentMessage(
            senderId: $this->sessionId,
            content: json_encode([
                'task' => 'data_model_definition',
                'resources' => $this->models->toArray(),
                'endpoints' => $this->endpoints->toArray()
            ]),
            metadata: ['step' => 'data_model_definition'],
            requiredCapabilities: ['api_design', 'data_modeling']
        ));

        $this->specification->setModels($models['schemas']);

        return $models;
    }

    private function designSecuritySchemes(): array
    {
        $security = $this->broker->routeMessageAndWait(new AgentMessage(
            senderId: $this->sessionId,
            content: json_encode([
                'task' => 'security_scheme_design',
                'api_requirements' => $this->getStepArtifacts('requirements_analysis')
            ]),
            metadata: ['step' => 'security_scheme_design'],
            requiredCapabilities: ['api_security', 'authentication_design']
        ));

        $this->securitySchemes = $security['schemes'];
        $this->specification->setSecuritySchemes($security['schemes']);

        return $security;
    }

    private function generateDocumentation(): array
    {
        $this->specification->setInfo([
            'title' => $this->configuration['api_name'] ?? 'API Specification',
            'version' => $this->configuration['version'] ?? '1.0.0',
            'description' => $this->configuration['description'] ?? ''
        ]);

        $this->specification->setPaths($this->endpoints->toArray());

        return [
            'openapi' => $this->specification->generate(),
            'additional_docs' => $this->generateAdditionalDocs()
        ];
    }

    private function generateAdditionalDocs(): array
    {
        // Generate additional documentation
        $examples = $this->generateExamples();
        $guides = $this->generateGuides();
        $tutorials = $this->generateTutorials();

        return [
            'examples' => $examples,
            'guides' => $guides,
            'tutorials' => $tutorials
        ];
    }

    private function reviewSpecification(): array
    {
        $review = $this->broker->routeMessageAndWait(new AgentMessage(
            senderId: $this->sessionId,
            content: json_encode([
                'task' => 'specification_review',
                'specification' => $this->specification->generate()
            ]),
            metadata: ['step' => 'specification_review'],
            requiredCapabilities: ['api_design', 'technical_review']
        ));

        // Apply any necessary corrections
        foreach ($review['corrections'] as $correction) {
            $this->applyCorrection($correction);
        }

        return $review;
    }

    private function reviewDeveloperExperience(): array
    {
        return $this->broker->routeMessageAndWait(new AgentMessage(
            senderId: $this->sessionId,
            content: json_encode([
                'task' => 'dx_review',
                'specification' => $this->specification->generate(),
                'documentation' => $this->getStepArtifacts('documentation_generation')
            ]),
            metadata: ['step' => 'dx_review'],
            requiredCapabilities: ['developer_experience', 'api_design']
        ));
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

    public function getSpecification(): array
    {
        return $this->specification->generate();
    }

    public function getDocumentation(): array
    {
        return $this->getStepArtifacts('documentation_generation');
    }
}
