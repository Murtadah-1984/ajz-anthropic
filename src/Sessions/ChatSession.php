<?php

namespace Ajz\Anthropic\Sessions;

use Ajz\Anthropic\Models\Session;
use Ajz\Anthropic\Models\Message;
use Illuminate\Support\Facades\Event;

class ChatSession extends AbstractSession
{
    /**
     * The session's model instance.
     *
     * @var Session|null
     */
    protected ?Session $model = null;

    /**
     * Get the session's configuration schema.
     *
     * @return array
     */
    public static function getConfigurationSchema(): array
    {
        return [
            'max_participants' => ['required', 'integer', 'min:2'],
            'message_retention_days' => ['required', 'integer', 'min:1'],
            'allowed_message_types' => ['required', 'array'],
            'moderation_settings' => ['required', 'array'],
            'language_settings' => ['required', 'array'],
            'notification_settings' => ['required', 'array'],
        ];
    }

    /**
     * Get the session's validation rules.
     *
     * @return array
     */
    public function getValidationRules(): array
    {
        return [
            'message' => ['required', 'array'],
            'message.content' => ['required', 'string'],
            'message.type' => ['required', 'string', 'in:text,code,image,file'],
            'message.metadata' => ['sometimes', 'array'],
        ];
    }

    /**
     * Initialize the chat session.
     *
     * @param array $config
     * @return void
     */
    public function initialize(array $config): void
    {
        if (!$this->validateConfiguration($config)) {
            throw new \InvalidArgumentException('Invalid chat session configuration');
        }

        $this->config = $config;
        $this->state = [
            'participant_count' => 0,
            'message_count' => 0,
            'last_activity' => now(),
        ];

        Event::dispatch('chat_session.initialized', [$this]);
    }

    /**
     * Process an incoming message.
     *
     * @param Message $message
     * @return bool
     */
    public function processMessage(Message $message): bool
    {
        try {
            // Validate message
            if (!$this->validateMessage($message)) {
                throw new \InvalidArgumentException('Invalid message format');
            }

            // Apply moderation if enabled
            if ($this->config['moderation_settings']['enabled']) {
                $this->moderateMessage($message);
            }

            // Process message based on type
            $processed = match ($message->type) {
                'text' => $this->processTextMessage($message),
                'code' => $this->processCodeMessage($message),
                'image' => $this->processImageMessage($message),
                'file' => $this->processFileMessage($message),
                default => throw new \InvalidArgumentException("Unsupported message type: {$message->type}"),
            };

            if ($processed) {
                // Update session state
                $this->updateState([
                    'message_count' => $this->state['message_count'] + 1,
                    'last_activity' => now(),
                    'last_message_type' => $message->type,
                ]);

                // Add message to session
                $this->addMessage($message);

                // Notify participants if enabled
                if ($this->config['notification_settings']['enabled']) {
                    $this->notifyParticipants($message);
                }
            }

            return $processed;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    /**
     * Get the chat history.
     *
     * @param array $filters
     * @return array
     */
    public function getChatHistory(array $filters = []): array
    {
        $query = $this->getMessages();

        // Apply filters
        if (!empty($filters['participant'])) {
            $query = $this->getMessagesFromParticipant($filters['participant']);
        }

        if (!empty($filters['type'])) {
            $query = $query->where('type', $filters['type']);
        }

        if (!empty($filters['from'])) {
            $query = $query->where('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query = $query->where('created_at', '<=', $filters['to']);
        }

        return [
            'messages' => $query->get(),
            'total' => $query->count(),
            'participants' => $this->getParticipants(),
        ];
    }

    /**
     * Get chat statistics.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $messages = $this->getMessages();

        return [
            'total_messages' => $messages->count(),
            'message_types' => $messages->groupBy('type')
                ->map(fn ($group) => $group->count())
                ->toArray(),
            'participant_activity' => $this->getParticipants()
                ->mapWithKeys(fn ($participant) => [
                    $participant->id => $this->getMessagesFromParticipant($participant)->count()
                ])
                ->toArray(),
            'hourly_activity' => $messages->groupBy(fn ($message) =>
                $message->created_at->format('H')
            )->map(fn ($group) => $group->count())->toArray(),
            'average_response_time' => $this->calculateAverageResponseTime(),
        ];
    }

    /**
     * Get the session's model instance.
     *
     * @return Session
     */
    protected function getModel(): Session
    {
        if (!$this->model) {
            $this->model = Session::where('type', 'chat')
                ->where('external_id', $this->getId())
                ->firstOrFail();
        }

        return $this->model;
    }

    /**
     * Validate a message.
     *
     * @param Message $message
     * @return bool
     */
    protected function validateMessage(Message $message): bool
    {
        // Check if message type is allowed
        if (!in_array($message->type, $this->config['allowed_message_types'])) {
            return false;
        }

        // Validate message content
        return $this->validateInput([
            'message' => [
                'content' => $message->content,
                'type' => $message->type,
                'metadata' => $message->metadata,
            ],
        ]);
    }

    /**
     * Moderate a message.
     *
     * @param Message $message
     * @return void
     */
    protected function moderateMessage(Message $message): void
    {
        // Implementation details...
    }

    /**
     * Process a text message.
     *
     * @param Message $message
     * @return bool
     */
    protected function processTextMessage(Message $message): bool
    {
        // Implementation details...
        return true;
    }

    /**
     * Process a code message.
     *
     * @param Message $message
     * @return bool
     */
    protected function processCodeMessage(Message $message): bool
    {
        // Implementation details...
        return true;
    }

    /**
     * Process an image message.
     *
     * @param Message $message
     * @return bool
     */
    protected function processImageMessage(Message $message): bool
    {
        // Implementation details...
        return true;
    }

    /**
     * Process a file message.
     *
     * @param Message $message
     * @return bool
     */
    protected function processFileMessage(Message $message): bool
    {
        // Implementation details...
        return true;
    }

    /**
     * Notify participants about a new message.
     *
     * @param Message $message
     * @return void
     */
    protected function notifyParticipants(Message $message): void
    {
        // Implementation details...
    }

    /**
     * Calculate average response time.
     *
     * @return float|null
     */
    protected function calculateAverageResponseTime(): ?float
    {
        // Implementation details...
        return null;
    }
}
