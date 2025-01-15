<?php

namespace Ajz\Anthropic\Sessions;

use Ajz\Anthropic\Contracts\SessionInterface;
use Ajz\Anthropic\Models\Message;
use Ajz\Anthropic\Models\Agent;
use Ajz\Anthropic\Models\User;
use Ajz\Anthropic\Models\Session;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

abstract class AbstractSession implements SessionInterface
{
    /**
     * The session's unique identifier.
     *
     * @var string
     */
    protected string $id;

    /**
     * The session's state.
     *
     * @var array
     */
    protected array $state = [];

    /**
     * The session's context.
     *
     * @var array
     */
    protected array $context = [];

    /**
     * The session's metadata.
     *
     * @var array
     */
    protected array $metadata = [];

    /**
     * The session's configuration.
     *
     * @var array
     */
    protected array $config = [];

    /**
     * The session's model instance.
     *
     * @var Session|null
     */
    protected ?Session $model = null;

    /**
     * Create a new session instance.
     */
    public function __construct()
    {
        $this->id = Str::uuid()->toString();
    }

    /**
     * Get the session's unique identifier.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the session's type.
     *
     * @return string
     */
    public function getType(): string
    {
        return class_basename($this);
    }

    /**
     * Get the session's status.
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->getModel()->status;
    }

    /**
     * Get the session's context.
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Update the session's context.
     *
     * @param array $context
     * @return void
     */
    public function updateContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
        $this->getModel()->update(['context' => $this->context]);
    }

    /**
     * Get the session's state.
     *
     * @return array
     */
    public function getState(): array
    {
        return $this->state;
    }

    /**
     * Update the session's state.
     *
     * @param array $state
     * @return void
     */
    public function updateState(array $state): void
    {
        $this->state = array_merge($this->state, $state);
        $this->getModel()->update(['state' => $this->state]);
    }

    /**
     * Start the session.
     *
     * @return bool
     */
    public function start(): bool
    {
        try {
            Event::dispatch('session.starting', [$this]);

            $this->getModel()->update([
                'status' => 'active',
                'started_at' => now(),
            ]);

            Event::dispatch('session.started', [$this]);

            return true;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    /**
     * End the session.
     *
     * @return bool
     */
    public function end(): bool
    {
        try {
            Event::dispatch('session.ending', [$this]);

            $this->getModel()->update([
                'status' => 'ended',
                'ended_at' => now(),
            ]);

            Event::dispatch('session.ended', [$this]);

            return true;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    /**
     * Pause the session.
     *
     * @return bool
     */
    public function pause(): bool
    {
        try {
            Event::dispatch('session.pausing', [$this]);

            $this->getModel()->update(['status' => 'paused']);

            Event::dispatch('session.paused', [$this]);

            return true;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    /**
     * Resume the session.
     *
     * @return bool
     */
    public function resume(): bool
    {
        try {
            Event::dispatch('session.resuming', [$this]);

            $this->getModel()->update(['status' => 'active']);

            Event::dispatch('session.resumed', [$this]);

            return true;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    /**
     * Add a participant to the session.
     *
     * @param Agent|User $participant
     * @param string $role
     * @return bool
     */
    public function addParticipant(Agent|User $participant, string $role): bool
    {
        try {
            Event::dispatch('session.participant_joining', [$this, $participant, $role]);

            $success = $this->getModel()->participants()->attach($participant, [
                'role' => $role,
                'joined_at' => now(),
            ]);

            if ($success) {
                Event::dispatch('session.participant_joined', [$this, $participant, $role]);
            }

            return $success;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    /**
     * Remove a participant from the session.
     *
     * @param Agent|User $participant
     * @return bool
     */
    public function removeParticipant(Agent|User $participant): bool
    {
        try {
            Event::dispatch('session.participant_leaving', [$this, $participant]);

            $success = $this->getModel()->participants()->updateExistingPivot(
                $participant->id,
                ['left_at' => now()]
            );

            if ($success) {
                Event::dispatch('session.participant_left', [$this, $participant]);
            }

            return $success;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    /**
     * Get all participants in the session.
     *
     * @return Collection
     */
    public function getParticipants(): Collection
    {
        return $this->getModel()->participants;
    }

    /**
     * Get participants with a specific role.
     *
     * @param string $role
     * @return Collection
     */
    public function getParticipantsByRole(string $role): Collection
    {
        return $this->getModel()->participants()->wherePivot('role', $role)->get();
    }

    /**
     * Add a message to the session.
     *
     * @param Message $message
     * @return bool
     */
    public function addMessage(Message $message): bool
    {
        try {
            Event::dispatch('session.message_adding', [$this, $message]);

            $success = $this->getModel()->messages()->save($message);

            if ($success) {
                Event::dispatch('session.message_added', [$this, $message]);
            }

            return (bool) $success;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    /**
     * Get all messages in the session.
     *
     * @return Collection
     */
    public function getMessages(): Collection
    {
        return $this->getModel()->messages;
    }

    /**
     * Get messages from a specific participant.
     *
     * @param Agent|User $participant
     * @return Collection
     */
    public function getMessagesFromParticipant(Agent|User $participant): Collection
    {
        return $this->getModel()->messages()
            ->where('participant_type', get_class($participant))
            ->where('participant_id', $participant->id)
            ->get();
    }

    /**
     * Get the session's metadata.
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Update the session's metadata.
     *
     * @param array $metadata
     * @return void
     */
    public function updateMetadata(array $metadata): void
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        $this->getModel()->update(['metadata' => $this->metadata]);
    }

    /**
     * Validate session configuration.
     *
     * @param array $config
     * @return bool
     */
    public function validateConfiguration(array $config): bool
    {
        $validator = validator($config, static::getConfigurationSchema());
        return !$validator->fails();
    }

    /**
     * Handle an error that occurred during the session.
     *
     * @param \Throwable $error
     * @return void
     */
    public function handleError(\Throwable $error): void
    {
        Log::error('Session Error', [
            'session_id' => $this->getId(),
            'session_type' => $this->getType(),
            'error' => $error->getMessage(),
            'trace' => $error->getTraceAsString(),
            'state' => $this->getState(),
        ]);

        Event::dispatch('session.error', [$this, $error]);
    }

    /**
     * Check if the session is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->getStatus() === 'active';
    }

    /**
     * Check if the session is paused.
     *
     * @return bool
     */
    public function isPaused(): bool
    {
        return $this->getStatus() === 'paused';
    }

    /**
     * Check if the session has ended.
     *
     * @return bool
     */
    public function hasEnded(): bool
    {
        return $this->getStatus() === 'ended';
    }

    /**
     * Get the session's start time.
     *
     * @return \DateTimeInterface|null
     */
    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->getModel()->started_at;
    }

    /**
     * Get the session's end time.
     *
     * @return \DateTimeInterface|null
     */
    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->getModel()->ended_at;
    }

    /**
     * Get the session's duration in seconds.
     *
     * @return int|null
     */
    public function getDuration(): ?int
    {
        $startTime = $this->getStartTime();
        $endTime = $this->getEndTime() ?? now();

        return $startTime ? $endTime->diffInSeconds($startTime) : null;
    }

    /**
     * Get the session's performance metrics.
     *
     * @return array
     */
    public function getPerformanceMetrics(): array
    {
        return $this->getModel()->getPerformanceMetrics();
    }

    /**
     * Export the session data.
     *
     * @param array $options
     * @return array
     */
    public function export(array $options = []): array
    {
        $data = [
            'id' => $this->getId(),
            'type' => $this->getType(),
            'status' => $this->getStatus(),
            'context' => $this->getContext(),
            'state' => $this->getState(),
            'metadata' => $this->getMetadata(),
            'started_at' => $this->getStartTime(),
            'ended_at' => $this->getEndTime(),
            'duration' => $this->getDuration(),
        ];

        if (!empty($options['include_messages'])) {
            $data['messages'] = $this->getMessages()->toArray();
        }

        if (!empty($options['include_participants'])) {
            $data['participants'] = $this->getParticipants()->toArray();
        }

        if (!empty($options['include_events'])) {
            $data['events'] = $this->getEventHistory()->toArray();
        }

        return $data;
    }

    /**
     * Import session data.
     *
     * @param array $data
     * @return bool
     */
    public function import(array $data): bool
    {
        try {
            $this->context = $data['context'] ?? [];
            $this->state = $data['state'] ?? [];
            $this->metadata = $data['metadata'] ?? [];

            $this->getModel()->update([
                'context' => $this->context,
                'state' => $this->state,
                'metadata' => $this->metadata,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    /**
     * Create a snapshot of the session's current state.
     *
     * @return array
     */
    public function createSnapshot(): array
    {
        return [
            'timestamp' => now(),
            'state' => $this->getState(),
            'context' => $this->getContext(),
            'metadata' => $this->getMetadata(),
            'status' => $this->getStatus(),
        ];
    }

    /**
     * Restore the session from a snapshot.
     *
     * @param array $snapshot
     * @return bool
     */
    public function restoreSnapshot(array $snapshot): bool
    {
        try {
            $this->state = $snapshot['state'] ?? [];
            $this->context = $snapshot['context'] ?? [];
            $this->metadata = $snapshot['metadata'] ?? [];

            $this->getModel()->update([
                'state' => $this->state,
                'context' => $this->context,
                'metadata' => $this->metadata,
                'status' => $snapshot['status'] ?? 'active',
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    /**
     * Get the session's event history.
     *
     * @return Collection
     */
    public function getEventHistory(): Collection
    {
        return $this->getModel()->events;
    }

    /**
     * Add an event to the session's history.
     *
     * @param string $type
     * @param array $data
     * @return void
     */
    public function addEvent(string $type, array $data): void
    {
        $this->getModel()->events()->create([
            'type' => $type,
            'data' => $data,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Get the session's model instance.
     *
     * @return Session
     */
    abstract protected function getModel(): Session;
}
