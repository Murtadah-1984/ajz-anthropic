<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Anthropic;

final class CompletionToMessageConverter
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
