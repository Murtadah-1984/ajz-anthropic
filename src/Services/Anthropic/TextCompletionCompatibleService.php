<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Anthropic;

final class TextCompletionCompatibleService extends AnthropicClaudeApiService
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
