<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Agency;

/**
 * @OA\Info(
 *     title="Workflow Orchestration Service",
 *     version="1.0.0",
 *     description="Manages complex AI conversation workflows with dynamic step execution, validation, and error handling"
 * )
 *
 * @OA\Tag(
 *     name="Workflow",
 *     description="AI Workflow orchestration operations"
 * )
 */

use Ajz\Anthropic\Models\Conversation;
use Ajz\Anthropic\Models\TaskDelegation;
use Ajz\Anthropic\Models\AIAssistant;
use Ajz\Anthropic\Events\WorkflowStepCompleted;
use Ajz\Anthropic\Events\WorkflowCompleted;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates complex AI conversation workflows with dynamic step execution and error handling.
 *
 * @OA\Schema(
 *     schema="WorkflowDefinition",
 *     required={"type", "steps", "conditions", "fallback"},
 *     @OA\Property(property="type", type="string", enum={"sequential", "parallel", "collaborative", "expedited"}),
 *     @OA\Property(property="steps", type="array", @OA\Items(ref="#/components/schemas/WorkflowStep")),
 *     @OA\Property(property="conditions", type="object"),
 *     @OA\Property(property="fallback", type="object")
 * )
 *
 * @OA\Schema(
 *     schema="WorkflowStep",
 *     required={"id", "description", "requirements"},
 *     @OA\Property(property="id", type="string"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(
 *         property="requirements",
 *         type="object",
 *         @OA\Property(property="capabilities", type="array", @OA\Items(type="string")),
 *         @OA\Property(property="input", type="array", @OA\Items(type="string"))
 *     ),
 *     @OA\Property(
 *         property="validation",
 *         type="array",
 *         @OA\Items(
 *             @OA\Property(property="type", type="string"),
 *             @OA\Property(property="criteria", type="string")
 *         )
 *     )
 * )
 */
final class WorkflowOrchestrationService
{
    /**
     * @param AnthropicClaudeApiService $apiService Service for interacting with Claude API
     * @param AITeamService $teamService Service for managing AI assistant teams
     */
    public function __construct(
        private readonly AnthropicClaudeApiService $apiService,
        private readonly AITeamService $teamService
    ) {}

    /**
     * Orchestrates the execution of an AI conversation workflow.
     *
     * @OA\Post(
     *     path="/api/workflow/orchestrate",
     *     tags={"Workflow"},
     *     summary="Orchestrate an AI conversation workflow",
     *     description="Analyzes conversation context, determines appropriate workflow, and executes it with error handling",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/Conversation")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Workflow executed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="completed"),
     *             @OA\Property(property="workflow_results", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error in workflow execution",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string"),
     *             @OA\Property(property="step_id", type="string"),
     *             @OA\Property(property="validation_errors", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Workflow execution error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string"),
     *             @OA\Property(property="conversation_id", type="string")
     *         )
     *     )
     * )
     *
     * @param Conversation $conversation The conversation to orchestrate workflow for
     * @throws WorkflowValidationException When step validation fails
     * @throws \Exception When unrecoverable error occurs
     */
    public function orchestrateWorkflow(Conversation $conversation): void
    {
        try {
            $workflow = $this->determineWorkflow($conversation);
            $this->executeWorkflow($conversation, $workflow);
        } catch (\Exception $e) {
            Log::error('Workflow Orchestration Error', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->handleWorkflowError($conversation, $e);
        }
    }

    /**
     * Determines the appropriate workflow configuration based on conversation analysis.
     *
     * @param Conversation $conversation The conversation to analyze
     * @return array The determined workflow configuration
     */
    private function determineWorkflow(Conversation $conversation): array
    {
        $analysis = $this->analyzeConversationContext($conversation);

        return [
            'type' => $this->selectWorkflowType($analysis),
            'steps' => $this->planWorkflowSteps($analysis),
            'conditions' => $this->defineWorkflowConditions($analysis),
            'fallback' => $this->defineFallbackStrategies($analysis)
        ];
    }

    /**
     * Executes a determined workflow configuration.
     *
     * @param Conversation $conversation The conversation context
     * @param array $workflow The workflow configuration to execute
     * @throws \Exception When workflow execution fails
     */
    private function executeWorkflow(Conversation $conversation, array $workflow): void
    {
        $currentStep = 0;
        $workflowData = [];

        foreach ($workflow['steps'] as $step) {
            if (!$this->checkStepConditions($step, $workflowData)) {
                continue;
            }

            try {
                $result = $this->executeWorkflowStep($conversation, $step, $workflowData);
                $workflowData[$step['id']] = $result;

                event(new WorkflowStepCompleted($conversation, $step, $result));
                $currentStep++;

            } catch (\Exception $e) {
                if (!$this->handleStepError($conversation, $step, $e, $workflow['fallback'])) {
                    throw $e;
                }
            }
        }

        if ($currentStep === count($workflow['steps'])) {
            $this->finalizeWorkflow($conversation, $workflowData);
            event(new WorkflowCompleted($conversation, $workflowData));
        }
    }

    /**
     * Executes a single step in the workflow.
     *
     * @param Conversation $conversation The conversation context
     * @param array $step The workflow step to execute
     * @param array $workflowData Data collected from previous steps
     * @return array The step execution result
     * @throws WorkflowValidationException When step validation fails
     */
    private function executeWorkflowStep(
        Conversation $conversation,
        array $step,
        array $workflowData
    ): array {
        $assistant = $this->findAssistantForStep($step, $conversation->assistant->team);

        // Create delegation for this step
        $delegation = TaskDelegation::create([
            'conversation_id' => $conversation->id,
            'from_assistant_id' => $conversation->ai_assistant_id,
            'to_assistant_id' => $assistant->id,
            'reason' => $step['description'],
            'context' => [
                'step_id' => $step['id'],
                'workflow_data' => $workflowData,
                'requirements' => $step['requirements'] ?? []
            ],
            'status' => 'active'
        ]);

        // Execute step logic
        $result = $this->processStepExecution($delegation, $step);

        // Validate step output
        $this->validateStepOutput($result, $step['validation'] ?? []);

        return $result;
    }

    /**
     * Processes the execution of a single workflow step.
     *
     * @param TaskDelegation $delegation The task delegation details
     * @param array $step The workflow step configuration
     * @return array The execution result containing output and metadata
     * @throws \Exception When API call fails
     */
    private function processStepExecution(TaskDelegation $delegation, array $step): array
    {
        $assistant = $delegation->toAssistant;
        $prompt = $this->buildStepPrompt($step, $delegation->context);

        $response = $this->apiService->createMessage(
            messages: [
                [
                    'role' => 'system',
                    'content' => $prompt
                ],
                [
                    'role' => 'user',
                    'content' => json_encode($delegation->context)
                ]
            ]
        );

        return [
            'output' => $response['content'],
            'metadata' => [
                'execution_time' => now()->timestamp - $delegation->created_at->timestamp,
                'assistant_id' => $assistant->id,
                'step_id' => $step['id']
            ],
            'validation_status' => 'pending'
        ];
    }

    /**
     * Builds the prompt for a workflow step execution.
     *
     * @param array $step The workflow step configuration
     * @param array $context The execution context
     * @return string The formatted prompt
     */
    private function buildStepPrompt(array $step, array $context): string
    {
        return <<<EOT
You are executing step {$step['id']}: {$step['description']}

Requirements:
{$this->formatRequirements($step['requirements'] ?? [])}

Available Context:
{$this->formatContext($context)}

Expected Output Format:
{$this->formatOutputRequirements($step['output_format'] ?? [])}

Validation Criteria:
{$this->formatValidationCriteria($step['validation'] ?? [])}

Please process this step and provide output in the specified format.
EOT;
    }

    /**
     * Validates the output of a workflow step against defined rules.
     *
     * @param array $result The step execution result
     * @param array $validationRules The validation rules to apply
     * @throws WorkflowValidationException When validation fails
     */
    private function validateStepOutput(array $result, array $validationRules): void
    {
                        $validationErrors = [];

                        foreach ($validationRules as $rule) {
                            if (!$this->validateRule($result['output'], $rule)) {
                                $validationErrors[] = "Failed validation rule: {$rule['description']}";
                            }
                        }

                        if (!empty($validationErrors)) {
                            throw new WorkflowValidationException(
                                "Step output validation failed: " . implode(", ", $validationErrors)
                            );
                        }
                    }

    /**
     * Analyzes the conversation context to determine workflow requirements.
     *
     * @param Conversation $conversation The conversation to analyze
     * @return array The analysis results including topics, complexity, and preferences
     */
    private function analyzeConversationContext(Conversation $conversation): array
    {
        $messages = $conversation->messages()
                            ->orderBy('created_at')
                            ->get();

                        $context = [
                            'initial_request' => $messages->first()->content,
                            'conversation_flow' => $this->analyzeConversationFlow($messages),
                            'identified_topics' => $this->identifyTopics($messages),
                            'complexity_indicators' => $this->assessComplexity($messages),
                            'user_preferences' => $this->extractUserPreferences($messages)
                        ];

                        return $this->enrichContextWithMetadata($context, $conversation);
                    }

    /**
     * Selects the appropriate workflow type based on analysis results.
     *
     * @param array $analysis The conversation analysis results
     * @return string The selected workflow type (sequential|parallel|collaborative|expedited)
     */
    private function selectWorkflowType(array $analysis): string
    {
        $criteria = [
                            'complexity' => $analysis['complexity_indicators']['overall_score'],
                            'topic_diversity' => count($analysis['identified_topics']),
                            'user_expectations' => $analysis['user_preferences']['response_type'] ?? 'standard',
                            'time_sensitivity' => $analysis['metadata']['urgency_level'] ?? 'normal'
                        ];

                        return match (true) {
                            $criteria['complexity'] > 4 => 'collaborative',
                            $criteria['topic_diversity'] > 3 => 'parallel',
                            $criteria['time_sensitivity'] === 'high' => 'expedited',
                            default => 'sequential'
                        };
                    }

    /**
     * Plans the workflow steps based on conversation analysis.
     *
     * @param array $analysis The conversation analysis results
     * @return array The planned workflow steps
     */
    private function planWorkflowSteps(array $analysis): array
    {
        $steps = [];

        // Initial understanding step
        $steps[] = [
            'id' => 'understanding',
            'description' => 'Analyze and understand the request',
            'requirements' => [
                'capabilities' => ['comprehension', 'analysis'],
                'input' => ['initial_request', 'user_preferences']
            ],
            'validation' => [
                [
                    'type' => 'completeness',
                    'criteria' => 'Must identify main objectives and constraints'
                ]
            ]
        ];

        // Planning step
        $steps[] = [
            'id' => 'planning',
            'description' => 'Develop execution strategy',
            'requirements' => [
                'capabilities' => ['planning', 'strategy'],
                'input' => ['understanding_output', 'complexity_indicators']
            ],
            'validation' => [
                [
                    'type' => 'coherence',
                    'criteria' => 'Must provide clear, actionable steps'
                ]
            ]
        ];

        // Add task-specific steps based on analysis
        foreach ($analysis['identified_topics'] as $topic) {
            $steps[] = $this->createTopicSpecificStep($topic);
        }

        // Add integration step if multiple topics
        if (count($analysis['identified_topics']) > 1) {
            $steps[] = [
                'id' => 'integration',
                'description' => 'Integrate results from all topics',
                'requirements' => [
                    'capabilities' => ['integration', 'synthesis'],
                    'input' => array_map(fn($topic) => "{$topic['id']}_output", $analysis['identified_topics'])
                ]
            ];
        }

        // Final quality check step
        $steps[] = [
            'id' => 'quality_check',
            'description' => 'Perform final quality assurance',
            'requirements' => [
                'capabilities' => ['quality_assurance', 'verification'],
                'input' => ['all_previous_outputs']
            ]
        ];

        return $steps;
    }

    /**
     * Creates a workflow step for processing a specific topic.
     *
     * @param array $topic The topic configuration
     * @return array The configured workflow step
     */
    private function createTopicSpecificStep(array $topic): array
    {
        return [
            'id' => "{$topic['id']}_processing",
            'description' => "Process {$topic['name']}",
            'requirements' => [
                'capabilities' => array_merge(['topic_expertise'], $topic['required_capabilities']),
                'input' => ['planning_output', 'topic_specific_context']
            ],
            'validation' => [
                [
                    'type' => 'domain_specific',
                    'criteria' => $topic['validation_criteria']
                ]
            ]
        ];
    }

    /**
     * Defines workflow execution conditions based on analysis.
     *
     * @param array $analysis The conversation analysis results
     * @return array The workflow conditions configuration
     */
    private function defineWorkflowConditions(array $analysis): array
    {
        return [
            'prerequisites' => $this->definePrerequisites($analysis),
            'transitions' => $this->defineTransitions($analysis),
            'completion_criteria' => $this->defineCompletionCriteria($analysis)
        ];
    }

    /**
     * Defines fallback strategies for handling workflow execution failures.
     *
     * @param array $analysis The conversation analysis results
     * @return array The fallback strategies configuration
     */
    private function defineFallbackStrategies(array $analysis): array
    {
        return [
            'validation_failure' => [
                'max_retries' => 3,
                'alternate_paths' => $this->defineAlternatePaths($analysis),
                'escalation_criteria' => $this->defineEscalationCriteria($analysis)
            ],
            'resource_unavailable' => [
                'backup_assistants' => $this->identifyBackupAssistants($analysis),
                'simplified_workflow' => $this->defineSimplifiedWorkflow($analysis)
            ],
            'timeout' => [
                'max_duration' => $this->calculateMaxDuration($analysis),
                'checkpoint_intervals' => $this->defineCheckpoints($analysis)
            ]
        ];
    }

    /**
     * Handles errors that occur during workflow step execution.
     *
     * @param Conversation $conversation The conversation context
     * @param array $step The failed workflow step
     * @param \Exception $error The error that occurred
     * @param array $fallback The fallback strategies to apply
     * @return bool Whether the error was handled successfully
     */
    private function handleStepError(
        Conversation $conversation,
        array $step,
        \Exception $error,
        array $fallback
    ): bool {
        $errorContext = [
            'step_id' => $step['id'],
            'error_type' => get_class($error),
            'error_message' => $error->getMessage()
        ];

        Log::warning('Workflow Step Error', $errorContext);

        // Check if we can retry
        if ($this->canRetryStep($step, $fallback)) {
            return $this->retryStep($conversation, $step, $fallback);
        }

        // Check if we can use alternate path
        if ($this->hasAlternatePath($step, $fallback)) {
            return $this->executeAlternatePath($conversation, $step, $fallback);
        }

        // Check if we need to escalate
        if ($this->shouldEscalate($errorContext, $fallback)) {
            $this->escalateWorkflow($conversation, $errorContext);
            return false;
        }

        return false;
    }

    /**
     * Checks if a failed step can be retried based on retry count.
     *
     * @param array $step The failed workflow step
     * @param array $fallback The fallback configuration
     * @return bool Whether the step can be retried
     */
    private function canRetryStep(array $step, array $fallback): bool
    {
        $retryCount = Cache::get("workflow_step_retry:{$step['id']}", 0);
        return $retryCount < $fallback['validation_failure']['max_retries'];
    }

    /**
     * Retries a failed workflow step with modified requirements.
     *
     * @param Conversation $conversation The conversation context
     * @param array $step The failed workflow step
     * @param array $fallback The fallback configuration
     * @return bool Whether the retry was successful
     */
    private function retryStep(Conversation $conversation, array $step, array $fallback): bool
    {
        $retryCount = Cache::increment("workflow_step_retry:{$step['id']}", 1);

        // Modify step for retry
        $modifiedStep = array_merge($step, [
            'retry_count' => $retryCount,
            'modified_requirements' => $this->adjustRequirementsForRetry($step['requirements'], $retryCount)
        ]);

        return $this->executeWorkflowStep($conversation, $modifiedStep, []) !== null;
    }

    /**
     * Checks if an alternate execution path exists for a failed step.
     *
     * @param array $step The failed workflow step
     * @param array $fallback The fallback configuration
     * @return bool Whether an alternate path exists
     */
    private function hasAlternatePath(array $step, array $fallback): bool
    {
        return isset($fallback['validation_failure']['alternate_paths'][$step['id']]);
    }

    /**
     * Executes an alternate path for a failed workflow step.
     *
     * @param Conversation $conversation The conversation context
     * @param array $step The failed workflow step
     * @param array $fallback The fallback configuration
     * @return bool Whether the alternate path execution was successful
     */
    private function executeAlternatePath(
        Conversation $conversation,
        array $step,
        array $fallback
    ): bool {
        $alternatePath = $fallback['validation_failure']['alternate_paths'][$step['id']];

        Log::info('Executing alternate path for step', [
            'step_id' => $step['id'],
            'alternate_path' => $alternatePath
        ]);

        return $this->executeWorkflowStep($conversation, $alternatePath, []) !== null;
    }

    /**
     * Finalizes the workflow execution and updates conversation state.
     *
     * @param Conversation $conversation The conversation to finalize
     * @param array $workflowData The collected workflow execution data
     */
    private function finalizeWorkflow(Conversation $conversation, array $workflowData): void
    {
        // Aggregate results
        $finalResult = $this->aggregateWorkflowResults($workflowData);

        // Update conversation
        $conversation->update([
            'status' => 'completed',
            'metadata' => array_merge(
                $conversation->metadata ?? [],
                ['workflow_results' => $finalResult]
            )
        ]);

        // Add summary message
        $conversation->messages()->create([
            'role' => 'system',
            'content' => $this->generateWorkflowSummary($finalResult),
            'metadata' => ['type' => 'workflow_summary']
        ]);
    }
}
