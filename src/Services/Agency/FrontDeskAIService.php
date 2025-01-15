<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Agency;

use Ajz\Anthropic\Models\AIAssistant;
use Ajz\Anthropic\Models\Conversation;
use Ajz\Anthropic\Models\TaskDelegation;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Ajz\Anthropic\Services\AnthropicClaudeApiService;
use Ajz\Anthropic\Exceptions\NoSuitableAssistantException;

final class FrontDeskAIService
{
    private const MAX_DIRECT_HANDLING_TOKENS = 150;

    public function __construct(
        private readonly AnthropicClaudeApiService $apiService,
        private readonly AIAssistant $frontDeskAssistant
    ) {}

    public function handleRequest(User $user, string $request): Conversation
    {
        // Start a new conversation
        $conversation = $this->createConversation($user, $request);

        try {
            // Analyze request and determine appropriate assistant
            $analysis = $this->analyzeRequest($request);

            if ($analysis['requires_delegation']) {
                // Delegate to appropriate assistant
                $this->delegateRequest(
                    conversation: $conversation,
                    taskType: $analysis['task_type'],
                    context: $analysis['context']
                );
            } else {
                // Handle directly if simple enough
                $this->handleDirectly($conversation, $request);
            }

            return $conversation;
        } catch (\Exception $e) {
            Log::error('Front Desk Error', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversation->id
            ]);

            $this->handleError($conversation, $e);
            throw $e;
        }
    }

    private function createConversation(User $user, string $request): Conversation
    {
        $conversation = Conversation::create([
            'ai_assistant_id' => $this->frontDeskAssistant->id,
            'user_id' => $user->id,
            'status' => 'active',
            'metadata' => [
                'initial_request' => $request,
                'source' => 'front_desk'
            ]
        ]);

        // Add initial user message
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $request
        ]);

        return $conversation;
    }

    private function analyzeRequest(string $request): array
    {
        $analysisPrompt = $this->getAnalysisPrompt();

        $response = $this->apiService->createMessage(
            messages: [
                [
                    'role' => 'system',
                    'content' => $analysisPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $request
                ]
            ]
        );

        $analysis = json_decode($response['content'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse request analysis');
        }

        return $analysis;
    }

    private function getAnalysisPrompt(): string
    {
        return <<<EOT
You are an AI request analyzer. Analyze the user request and provide a structured response with the following information:
{
    "requires_delegation": boolean,
    "task_type": string (one of: "development", "architecture", "review", "documentation", "security", "general"),
    "priority": number (1-5),
    "estimated_complexity": number (1-5),
    "context": {
        "domain": string,
        "specific_requirements": array,
        "constraints": array
    }
}

For simple queries that can be answered directly, set requires_delegation to false.
For complex tasks requiring specific expertise, set requires_delegation to true and specify the appropriate task_type.
EOT;
    }

    private function delegateRequest(
        Conversation $conversation,
        string $taskType,
        array $context
    ): void {
        try {
            // Find the most appropriate assistant
            $assistant = $this->findAppropriateAssistant($taskType);

            // Create delegation record
            $delegation = TaskDelegation::create([
                'conversation_id' => $conversation->id,
                'from_assistant_id' => $this->frontDeskAssistant->id,
                'to_assistant_id' => $assistant->id,
                'reason' => "Task type: {$taskType}",
                'context' => $context,
                'status' => 'pending'
            ]);

            // Add delegation message
            $this->addDelegationMessage($conversation, $assistant, $delegation);

            // Transfer conversation
            $this->transferConversation($conversation, $assistant);

        } catch (NoSuitableAssistantException $e) {
            // Handle case where no suitable assistant is found
            $this->handleNoSuitableAssistant($conversation, $taskType);
        }
    }

    private function findAppropriateAssistant(string $taskType): AIAssistant
    {
        $assistant = AIAssistant::active()
            ->whereJsonContains('capabilities->supported_tasks', $taskType)
            ->orderBy('last_interaction')
            ->first();

        if (!$assistant) {
            throw new NoSuitableAssistantException($taskType);
        }

        return $assistant;
    }

    private function handleDirectly(Conversation $conversation, string $request): void
    {
        $response = $this->apiService->createMessage(
            messages: [
                [
                    'role' => 'system',
                    'content' => $this->getFrontDeskSystemPrompt()
                ],
                [
                    'role' => 'user',
                    'content' => $request
                ]
            ]
        );

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $response['content']
        ]);

        // Update last interaction time
        $this->frontDeskAssistant->update(['last_interaction' => now()]);
    }

    private function getFrontDeskSystemPrompt(): string
    {
        return <<<EOT
You are a helpful front desk AI assistant. For simple queries, provide direct and concise responses.
If a request seems complex or requires specific expertise, indicate that it should be delegated.
Always maintain a professional and courteous tone.
EOT;
    }

    private function addDelegationMessage(
        Conversation $conversation,
        AIAssistant $assistant,
        TaskDelegation $delegation
    ): void {
        $message = <<<EOT
I'm connecting you with our {$assistant->name} who specializes in {$assistant->role->role_name}.
They will assist you with your request. There might be a brief moment while they review our conversation.
EOT;

        $conversation->messages()->create([
            'role' => 'system',
            'content' => $message,
            'metadata' => [
                'delegation_id' => $delegation->id,
                'assistant_id' => $assistant->id
            ]
        ]);
    }

    private function transferConversation(
        Conversation $conversation,
        AIAssistant $assistant
    ): void {
        $conversation->update([
            'ai_assistant_id' => $assistant->id,
            'metadata' => array_merge(
                $conversation->metadata ?? [],
                [
                    'delegated_from' => $this->frontDeskAssistant->id,
                    'delegation_time' => now()->toIso8601String()
                ]
            )
        ]);

        // Update assistant's last interaction time
        $assistant->update(['last_interaction' => now()]);
    }

    private function handleNoSuitableAssistant(
        Conversation $conversation,
        string $taskType
    ): void {
        $message = <<<EOT
I apologize, but I currently don't have access to an assistant who specializes in {$taskType}.
Would you like me to:
1. Handle your request with general knowledge (though it may not be as specialized)
2. Connect you with a human supervisor
3. Suggest an alternative approach to your request
Please let me know your preference.
EOT;

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $message,
            'metadata' => [
                'error_type' => 'no_suitable_assistant',
                'requested_task_type' => $taskType
            ]
        ]);
    }

    private function handleError(Conversation $conversation, \Exception $e): void
    {
        $message = "I apologize, but I encountered an issue while processing your request. ";
        $message .= "A supervisor has been notified and will look into this shortly. ";
        $message .= "Please feel free to rephrase your request or try again later.";

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $message,
            'metadata' => [
                'error_type' => get_class($e),
                'error_message' => $e->getMessage()
            ]
        ]);

        // Update conversation status
        $conversation->update(['status' => 'error']);

        // Log detailed error for monitoring
        Log::error('AI Assistant Error', [
            'conversation_id' => $conversation->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}
