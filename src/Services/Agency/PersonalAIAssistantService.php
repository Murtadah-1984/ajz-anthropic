<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Agency;

use Ajz\Anthropic\Models\AIAssistant;
use App\Models\User;
use Ajz\Anthropic\Models\Conversation;
use Ajz\Anthropic\Services\AnthropicClaudeApiService;
use Illuminate\Support\Facades\Cache;

/**
 * @OA\Schema(
 *     schema="AIAssistant",
 *     title="AI Assistant",
 *     description="Personal AI Assistant model",
 *     @OA\Property(property="id", type="integer", description="Assistant unique identifier"),
 *     @OA\Property(property="name", type="string", description="Assistant name"),
 *     @OA\Property(property="code", type="string", description="Unique assistant code"),
 *     @OA\Property(property="assistant_role_id", type="integer", description="Role ID of the assistant"),
 *     @OA\Property(property="user_id", type="integer", description="ID of the associated user"),
 *     @OA\Property(property="is_personal", type="boolean", description="Whether this is a personal assistant"),
 *     @OA\Property(
 *         property="configuration",
 *         type="object",
 *         description="Assistant configuration settings"
 *     ),
 *     @OA\Property(
 *         property="capabilities",
 *         type="object",
 *         description="Assistant capabilities",
 *         @OA\Property(property="supported_tasks", type="array", @OA\Items(type="string")),
 *         @OA\Property(property="preferences", type="object"),
 *         @OA\Property(property="limitations", type="array", @OA\Items(type="string"))
 *     ),
 *     @OA\Property(
 *         property="memory",
 *         type="object",
 *         description="Assistant's memory storage",
 *         @OA\Property(property="user_preferences", type="object"),
 *         @OA\Property(property="important_dates", type="array", @OA\Items(type="string")),
 *         @OA\Property(property="conversation_history", type="array", @OA\Items(type="object")),
 *         @OA\Property(property="learned_patterns", type="object")
 *     )
 * )
 */
final class PersonalAIAssistantService
{
    private const MEMORY_CACHE_TTL = 3600; // 1 hour
    private const CONTEXT_WINDOW = 10; // Number of recent messages to include

    public function __construct(
        private readonly AnthropicClaudeApiService $apiService
    ) {}

    /**
     * Create a new personal AI assistant for a user
     *
     * @OA\Post(
     *     path="/api/assistants/personal",
     *     summary="Create a personal AI assistant",
     *     description="Creates a new personal AI assistant configured for a specific user",
     *     tags={"AI Assistants"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"configuration"},
     *             @OA\Property(
     *                 property="configuration",
     *                 type="object",
     *                 description="Assistant configuration settings",
     *                 @OA\Property(property="preferences", type="object", description="User preferences for the assistant"),
     *                 example={"preferences": {"communication_style": "casual", "response_format": "concise"}}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Personal assistant created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/AIAssistant")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid configuration provided"
     *     )
     * )
     *
     * @param User $user The user to create the assistant for
     * @param array $configuration Configuration settings for the assistant
     * @return AIAssistant The created AI assistant instance
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public function createPersonalAssistant(User $user, array $configuration): AIAssistant
    {
        return AIAssistant::create([
            'name' => "{$user->name}'s Personal Assistant",
            'code' => "personal_{$user->id}",
            'assistant_role_id' => $this->getPersonalAssistantRole()->id,
            'user_id' => $user->id,
            'is_personal' => true,
            'configuration' => $configuration,
            'capabilities' => [
                'supported_tasks' => ['personal', 'scheduling', 'reminders', 'notes', 'general'],
                'preferences' => $configuration['preferences'] ?? [],
                'limitations' => ['no_financial_transactions', 'no_external_api_access']
            ],
            'memory' => [
                'user_preferences' => [],
                'important_dates' => [],
                'conversation_history' => [],
                'learned_patterns' => []
            ]
        ]);
    }

    /**
     * Handle an incoming request to the personal AI assistant
     *
     * @OA\Post(
     *     path="/api/assistants/{assistant_id}/request",
     *     summary="Send request to personal assistant",
     *     description="Processes a user request and generates an AI response with context",
     *     tags={"AI Assistants"},
     *     @OA\Parameter(
     *         name="assistant_id",
     *         in="path",
     *         required=true,
     *         description="ID of the personal assistant",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"request"},
     *             @OA\Property(
     *                 property="request",
     *                 type="string",
     *                 description="The user's request message",
     *                 example="What meetings do I have scheduled today?"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Request processed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="content", type="string", description="Assistant's response"),
     *             @OA\Property(property="conversation_id", type="integer", description="ID of the conversation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Assistant not found"
     *     )
     * )
     *
     * @param AIAssistant $assistant The AI assistant instance
     * @param string $request The user's request message
     * @return array Response containing assistant's reply and metadata
     */
    public function handleRequest(AIAssistant $assistant, string $request): array
    {
        // Get or create active conversation
        $conversation = $this->getActiveConversation($assistant);

        // Add user message
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $request
        ]);

        // Generate response with context
        $response = $this->generateResponse($assistant, $conversation, $request);

        // Update assistant's memory
        $this->updateAssistantMemory($assistant, $request, $response);

        return $response;
    }

    private function generateResponse(
        AIAssistant $assistant,
        Conversation $conversation,
        string $request
    ): array {
        $context = $this->buildContext($assistant, $conversation);

        $messages = [
            [
                'role' => 'system',
                'content' => $this->getPersonalAssistantPrompt($assistant)
            ],
            [
                'role' => 'system',
                'content' => "Context:\n" . json_encode($context)
            ]
        ];

        // Add recent conversation history
        foreach ($conversation->messages()->latest()->take(self::CONTEXT_WINDOW)->get() as $message) {
            $messages[] = [
                'role' => $message->role,
                'content' => $message->content
            ];
        }

        // Add current request
        $messages[] = [
            'role' => 'user',
            'content' => $request
        ];

        $response = $this->apiService->createMessage(messages: $messages);

        // Save assistant's response
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $response['content']
        ]);

        return $response;
    }

    private function buildContext(AIAssistant $assistant, Conversation $conversation): array
    {
        $cacheKey = "assistant_{$assistant->id}_context";

        return Cache::remember($cacheKey, self::MEMORY_CACHE_TTL, function () use ($assistant) {
            return [
                'user_preferences' => $assistant->memory['user_preferences'] ?? [],
                'important_dates' => $assistant->memory['important_dates'] ?? [],
                'learned_patterns' => $assistant->memory['learned_patterns'] ?? [],
                'capabilities' => $assistant->capabilities,
                'configuration' => $assistant->configuration
            ];
        });
    }

    private function updateAssistantMemory(
        AIAssistant $assistant,
        string $request,
        array $response
    ): void {
        $memory = $assistant->memory;

        // Update conversation history
        $memory['conversation_history'][] = [
            'timestamp' => now()->toIso8601String(),
            'request' => $request,
            'response' => $response['content']
        ];

        // Trim history if too long
        $memory['conversation_history'] = array_slice(
            $memory['conversation_history'],
            -50 // Keep last 50 interactions
        );

        // Extract and update learned patterns
        $this->updateLearnedPatterns($memory, $request, $response['content']);

        $assistant->update(['memory' => $memory]);
    }

    private function updateLearnedPatterns(array &$memory, string $request, string $response): void
    {
        // Analyze interaction for patterns
        $patterns = $memory['learned_patterns'] ?? [];

        // Example pattern: User's preferred response style
        if (str_contains(strtolower($request), 'i prefer')) {
            $patterns['communication_preferences'][] = $request;
        }

        // Example pattern: Important dates or events
        if (preg_match('/\b\d{1,2}\/\d{1,2}\/\d{4}\b/', $request)) {
            $patterns['date_mentions'][] = $request;
        }

        $memory['learned_patterns'] = $patterns;
    }

    private function getPersonalAssistantPrompt(AIAssistant $assistant): string
    {
        $userName = $assistant->user->name;
        $preferences = json_encode($assistant->configuration['preferences'] ?? []);

        return <<<EOT
    You are {$userName}'s personal AI assistant. Your primary goal is to be helpful while maintaining a natural,
    conversational tone that adapts to {$userName}'s preferences and communication style.

    User Preferences:
    {$preferences}

    Key Responsibilities:
    1. Maintain context across conversations
    2. Learn from interactions to better serve {$userName}
    3. Remember important details and preferences
    4. Provide personalized responses
    5. Respect privacy and confidentiality

    When handling requests:
    - Be proactive in offering relevant information based on learned patterns
    - Ask for clarification when needed
    - Maintain a friendly but professional tone
    - Reference previous conversations when relevant
    - Alert the user if a request goes beyond your capabilities

    Never make assumptions about personal information that hasn't been explicitly shared.
    EOT;
        }

    private function getActiveConversation(AIAssistant $assistant): Conversation
    {
        return Conversation::firstOrCreate(
            [
                'ai_assistant_id' => $assistant->id,
                'user_id' => $assistant->user_id,
                'status' => 'active'
            ],
            [
                'subject' => 'Ongoing Personal Assistance',
                'metadata' => [
                    'type' => 'personal_assistant',
                    'started_at' => now()->toIso8601String()
                ]
            ]
        );
        }

    /**
     * Update assistant's knowledge based on user feedback
     *
     * @OA\Post(
     *     path="/api/assistants/{assistant_id}/feedback",
     *     summary="Submit feedback for learning",
     *     description="Updates the assistant's memory and learning patterns based on user feedback",
     *     tags={"AI Assistants"},
     *     @OA\Parameter(
     *         name="assistant_id",
     *         in="path",
     *         required=true,
     *         description="ID of the personal assistant",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="feedback",
     *                 type="object",
     *                 @OA\Property(property="rating", type="integer", description="Feedback rating (1-5)"),
     *                 @OA\Property(property="preferences", type="object", description="Updated user preferences"),
     *                 @OA\Property(property="important_dates", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="improvements", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="successful_patterns", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Feedback processed successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid feedback format"
     *     )
     * )
     *
     * @param AIAssistant $assistant The AI assistant instance
     * @param array $feedback Feedback data for learning
     * @return void
     */
    public function learnFromFeedback(AIAssistant $assistant, array $feedback): void
    {
        $memory = $assistant->memory;

        // Update learned patterns based on feedback
        if (!empty($feedback['preferences'])) {
                $memory['user_preferences'] = array_merge(
                    $memory['user_preferences'] ?? [],
                    $feedback['preferences']
                );
            }

        if (!empty($feedback['important_dates'])) {
                $memory['important_dates'] = array_merge(
                    $memory['important_dates'] ?? [],
                    $feedback['important_dates']
                );
            }

        // Update response effectiveness metrics
        $memory['effectiveness_metrics'] = array_merge(
                $memory['effectiveness_metrics'] ?? [],
                [
                    'timestamp' => now()->toIso8601String(),
                    'rating' => $feedback['rating'] ?? null,
                    'areas_for_improvement' => $feedback['improvements'] ?? [],
                    'successful_patterns' => $feedback['successful_patterns'] ?? []
                ]
            );

        $assistant->update(['memory' => $memory]);

        // Clear context cache to reflect new learning
        Cache::forget("assistant_{$assistant->id}_context");
        }

        /**
     * Analyze interaction patterns between user and assistant
     *
     * @OA\Get(
     *     path="/api/assistants/{assistant_id}/patterns",
     *     summary="Get interaction patterns",
     *     description="Analyzes and returns patterns from user-assistant interactions",
     *     tags={"AI Assistants"},
     *     @OA\Parameter(
     *         name="assistant_id",
     *         in="path",
     *         required=true,
     *         description="ID of the personal assistant",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Patterns analyzed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="patterns",
     *                 type="object",
     *                 @OA\Property(property="most_common_topics", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="preferred_interaction_times", type="array", @OA\Items(
     *                     @OA\Property(property="hour", type="integer"),
     *                     @OA\Property(property="formatted_time", type="string")
     *                 )),
     *                 @OA\Property(property="common_request_types", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="response_preferences", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Assistant not found"
     *     )
     * )
     *
     * @param AIAssistant $assistant The AI assistant instance
     * @return array Analysis of interaction patterns
     */
    public function analyzeInteractionPatterns(AIAssistant $assistant): array
    {
        $conversations = $assistant->conversations()
            ->with('messages')
            ->recent()
            ->get();

        $patterns = [
                'common_topics' => [],
                'response_preferences' => [],
                'interaction_times' => [],
                'frequent_requests' => []
            ];

            foreach ($conversations as $conversation) {
                foreach ($conversation->messages as $message) {
                    if ($message->role === 'user') {
                        // Analyze message content for patterns
                        $this->extractPatterns($patterns, $message->content);
                    }

                    // Track interaction times
                    $hour = $message->created_at->format('H');
                    $patterns['interaction_times'][$hour] =
                        ($patterns['interaction_times'][$hour] ?? 0) + 1;
                }
            }

            return $this->summarizePatterns($patterns);
        }

    private function extractPatterns(array &$patterns, string $content): void
    {
        // Extract topics using basic keyword analysis
        $topics = $this->extractTopics($content);
        foreach ($topics as $topic) {
                $patterns['common_topics'][$topic] =
                    ($patterns['common_topics'][$topic] ?? 0) + 1;
            }

        // Analyze response preferences
        if (str_contains(strtolower($content), ['prefer', 'like', 'want'])) {
                $patterns['response_preferences'][] = $content;
            }

        // Track frequent request types
        $requestType = $this->categorizeRequest($content);
        if ($requestType) {
                $patterns['frequent_requests'][$requestType] =
                    ($patterns['frequent_requests'][$requestType] ?? 0) + 1;
            }
        }

    private function extractTopics(string $content): array
    {
        // Basic keyword extraction - in production, use NLP service
        $keywords = array_filter(
            str_word_count(strtolower($content), 1),
            fn($word) => strlen($word) > 3
        );

        return array_unique($keywords);
        }

    private function categorizeRequest(string $content): ?string
    {
        $categories = [
            'schedule' => ['schedule', 'appointment', 'meeting', 'calendar'],
            'reminder' => ['remind', 'remember', 'don\'t forget'],
            'information' => ['what', 'how', 'why', 'explain'],
            'task' => ['do', 'make', 'create', 'update'],
        ];

        foreach ($categories as $category => $keywords) {
            if (str_contains_any(strtolower($content), $keywords)) {
                return $category;
            }
        }

        return null;
        }

    private function summarizePatterns(array $patterns): array
    {
        return [
            'most_common_topics' => array_slice(
                arsort($patterns['common_topics']),
                0,
                5
            ),
            'preferred_interaction_times' => $this->findPeakInteractionTimes(
                $patterns['interaction_times']
            ),
            'common_request_types' => array_slice(
                arsort($patterns['frequent_requests']),
                0,
                3
            ),
            'response_preferences' => $this->summarizeResponsePreferences(
                $patterns['response_preferences']
            )
        ];
        }

    private function findPeakInteractionTimes(array $times): array
    {
        arsort($times);
        $peakHours = array_slice($times, 0, 3, true);

        return array_map(function ($hour) {
            return [
                'hour' => (int) $hour,
                'formatted_time' => sprintf(
                    '%02d:00 - %02d:00',
                    $hour,
                    ($hour + 1) % 24
                )
            ];
        }, array_keys($peakHours));
        }

    private function summarizeResponsePreferences(array $preferences): array
    {
        // Group similar preferences and extract key patterns
        $summary = [];
        foreach ($preferences as $preference) {
            $type = $this->categorizePreference($preference);
            if (!isset($summary[$type])) {
                $summary[$type] = [
                    'count' => 0,
                    'examples' => []
                ];
            }
            $summary[$type]['count']++;
            if (count($summary[$type]['examples']) < 3) {
                $summary[$type]['examples'][] = $preference;
            }
        }

        return $summary;
        }

    private function categorizePreference(string $preference): string
    {
        $categories = [
            'verbosity' => ['detailed', 'brief', 'concise', 'thorough'],
            'tone' => ['formal', 'casual', 'professional', 'friendly'],
            'format' => ['bullet points', 'paragraphs', 'steps', 'examples']
        ];

        foreach ($categories as $category => $keywords) {
            if (str_contains_any(strtolower($preference), $keywords)) {
                return $category;
            }
        }

        return 'other';
        }
}
