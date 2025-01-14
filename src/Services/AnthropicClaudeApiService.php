<?php

namespace Ajz\Anthropic\Services;

use Ajz\Anthropic\Services\Anthropic\{Message, Tool, ImageContent, ToolResult};
use Ajz\Anthropic\Services\Anthropic\Streaming\{
    StreamEvent,
    MessageStartEvent,
    ContentBlockStartEvent,
    ContentBlockDeltaEvent,
    ContentBlockStopEvent,
    MessageDeltaEvent,
    MessageStopEvent,
    ErrorEvent
};

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Generator;

final class AnthropicClaudeApiService extends ModelService
{
    /**
     * Create a streaming message response
     *
     * @param string $model
     * @param array $messages
     * @param array $options
     * @return Generator|StreamEvent[]
     * @throws AnthropicException
     */
    public function streamMessage(
        string $model = 'claude-3-5-sonnet-20241022',
        array $messages = [],
        array $options = []
    ): Generator {
        try {
            $payload = array_merge([
                'model' => $model,
                'max_tokens' => $options['max_tokens'] ?? 1024,
                'messages' => $messages,
                'stream' => true,
            ], $this->filterOptions($options));

            $response = $this->getHttpClient()
                ->withOptions(['stream' => true])
                ->post("{$this->baseUrl}/messages", $payload);

            if ($response->status() !== 200) {
                $this->handleResponse($response);
            }

            $buffer = '';
            $currentTextContent = '';
            $currentJsonContent = '';

            foreach ($this->parseSSEResponse($response) as $event) {
                yield $this->processStreamEvent($event);

                if ($event instanceof ContentBlockDeltaEvent) {
                    if ($event->isTextDelta()) {
                        $currentTextContent .= $event->getText();
                    } elseif ($event->isInputJsonDelta()) {
                        $currentJsonContent .= $event->getPartialJson();
                    }
                }

                if ($event instanceof ContentBlockStopEvent) {
                    // Reset accumulators after block stop
                    $currentTextContent = '';
                    $currentJsonContent = '';
                }
            }
        } catch (AnthropicException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage());
        }
    }

    /**
     * Parse SSE response into events
     *
     * @param Response $response
     * @return Generator
     */
    protected function parseSSEResponse(Response $response): Generator
    {
        $buffer = '';

        foreach ($response->getBody() as $chunk) {
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $event = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                $eventData = $this->parseSSEEvent($event);
                if ($eventData) {
                    yield $eventData;
                }
            }
        }

        if (!empty($buffer)) {
            $eventData = $this->parseSSEEvent($buffer);
            if ($eventData) {
                yield $eventData;
            }
        }
    }

    /**
     * Parse a single SSE event
     *
     * @param string $event
     * @return StreamEvent|null
     */
    protected function parseSSEEvent(string $event): ?StreamEvent
    {
        $lines = array_filter(explode("\n", $event));
        $eventType = '';
        $data = '';

        foreach ($lines as $line) {
            if (strpos($line, 'event:') === 0) {
                $eventType = trim(substr($line, 6));
            } elseif (strpos($line, 'data:') === 0) {
                $data = trim(substr($line, 5));
            }
        }

        if (empty($eventType) || empty($data)) {
            return null;
        }

        $data = json_decode($data, true);
        if (!$data) {
            return null;
        }

        return $this->processStreamEvent($eventType, $data);
    }

    /**
     * Process stream event into proper event object
     *
     * @param string $type
     * @param array $data
     * @return StreamEvent
     */
    protected function processStreamEvent(string $type, array $data): StreamEvent
    {
        return match ($type) {
            'message_start' => new MessageStartEvent($data),
            'content_block_start' => new ContentBlockStartEvent($data),
            'content_block_delta' => new ContentBlockDeltaEvent($data),
            'content_block_stop' => new ContentBlockStopEvent($data),
            'message_delta' => new MessageDeltaEvent($data),
            'message_stop' => new MessageStopEvent($data),
            'error' => new ErrorEvent($data),
            default => new StreamEvent($type, $data)
        };
    }

    // ... (rest of the class remains the same)
}
