<?php

namespace App\Services\Anthropic;

class CompletionToMessageConverter
{
    /**
     * Convert a Text Completion prompt to Messages format
     *
     * @param string $prompt The original text completion prompt
     * @return array{system: ?string, messages: array}
     */
    public static function convertPrompt(string $prompt): array
    {
        $result = [
            'system' => null,
            'messages' => []
        ];

        // Split on Human/Assistant markers
        $parts = preg_split('/\n\n(Human|Assistant):\s*/', $prompt, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        // First part might be system prompt if it doesn't start with Human/Assistant
        if (!empty($parts[0]) && !in_array($parts[0], ['Human', 'Assistant'])) {
            $result['system'] = trim($parts[0]);
            array_shift($parts);
        }

        // Process conversation turns
        for ($i = 0; $i < count($parts); $i += 2) {
            if (isset($parts[$i + 1])) {
                $role = strtolower($parts[$i]) === 'human' ? 'user' : 'assistant';
                $content = trim($parts[$i + 1]);
                
                if (!empty($content)) {
                    $result['messages'][] = [
                        'role' => $role,
                        'content' => $content
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Convert a Messages response to Text Completion format
     *
     * @param Message $message
     * @return string
     */
    public static function convertResponse(Message $message): string
    {
        $text = '';
        foreach ($message->content as $content) {
            if ($content instanceof TextContent) {
                $text .= $content->text;
            }
        }
        return $text;
    }

    /**
     * Map Text Completion stop reason to Messages format
     *
     * @param string $stopReason
     * @return string
     */
    public static function mapStopReason(string $stopReason): string
    {
        return match ($stopReason) {
            'stop_sequence' => 'stop_sequence',
            'max_tokens_to_sample' => 'max_tokens',
            default => 'end_turn'
        };
    }
}

class TextCompletionCompatibleService extends AnthropicClaudeApiService
{
    /**
     * Create a completion using Messages API but with Text Completion interface
     *
     * @param string $prompt
     * @param string $model
     * @param array $options
     * @return array
     */
    public function createCompletion(
        string $prompt,
        string $model = 'claude-3-5-sonnet-20241022',
        array $options = []
    ): array {
        // Convert prompt to messages format
        $converted = CompletionToMessageConverter::convertPrompt($prompt);
        
        // Map options
        $messageOptions = [
            'max_tokens' => $options['max_tokens_to_sample'] ?? 1024,
            'stop_sequences' => $options['stop_sequences'] ?? null,
            'stream' => $options['stream'] ?? false,
            'temperature' => $options['temperature'] ?? null,
            'top_p' => $options['top_p'] ?? null,
            'top_k' => $options['top_k'] ?? null,
        ];

        if ($converted['system']) {
            $messageOptions['system'] = $converted['system'];
        }

        // Create message using new API
        $response = $this->createMessage($model, $converted['messages'], $messageOptions);

        // Convert response back to completion format
        return [
            'completion' => CompletionToMessageConverter::convertResponse($response),
            'stop_reason' => CompletionToMessageConverter::mapStopReason($response->stop_reason),
            'model' => $response->model,
        ];
    }

    /**
     * Stream completion using Messages API but with Text Completion interface
     *
     * @param string $prompt
     * @param string $model
     * @param array $options
     * @return \Generator
     */
    public function streamCompletion(
        string $prompt,
        string $model = 'claude-3-5-sonnet-20241022',
        array $options = []
    ): \Generator {
        $converted = CompletionToMessageConverter::convertPrompt($prompt);
        
        $messageOptions = array_merge($options, [
            'max_tokens' => $options['max_tokens_to_sample'] ?? 1024,
            'stream' => true,
        ]);

        if ($converted['system']) {
            $messageOptions['system'] = $converted['system'];
        }

        $completion = '';
        
        foreach ($this->streamMessage($model, $converted['messages'], $messageOptions) as $event) {
            if ($event instanceof ContentBlockDeltaEvent && $event->isTextDelta()) {
                $completion .= $event->getText();
                yield [
                    'completion' => $event->getText(),
                    'stop_reason' => null,
                    'model' => $model,
                ];
            } elseif ($event instanceof MessageDeltaEvent) {
                yield [
                    'completion' => '',
                    'stop_reason' => CompletionToMessageConverter::mapStopReason($event->delta['stop_reason']),
                    'model' => $model,
                ];
            }
        }
    }
}