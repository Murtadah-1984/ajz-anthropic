<?php

namespace Ajz\Anthropic\AIAgents\Sessions;

use Ajz\Anthropic\Models\SessionState;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

class SessionStateManager
{
    /**
     * Valid session states.
     *
     * @var array
     */
    protected array $validStates = [
        'initialized',
        'active',
        'paused',
        'blocked',
        'completed',
        'failed',
        'archived'
    ];

    /**
     * Valid state transitions.
     *
     * @var array
     */
    protected array $validTransitions = [
        'initialized' => ['active'],
        'active' => ['paused', 'completed', 'failed'],
        'paused' => ['active', 'completed', 'failed'],
        'blocked' => ['active', 'failed'],
        'completed' => ['archived'],
        'failed' => ['archived'],
        'archived' => []
    ];

    /**
     * Session state history.
     *
     * @var Collection
     */
    protected Collection $stateHistory;

    public function __construct()
    {
        $this->stateHistory = collect();
    }

    /**
     * Initialize session state.
     */
    public function initializeState(string $sessionId, array $initialData = []): void
    {
        $state = [
            'status' => 'initialized',
            'data' => $initialData,
            'timestamp' => now()
        ];

        $this->persistState($sessionId, $state);
        $this->recordStateTransition($sessionId, null, 'initialized');

        Event::dispatch('session.state.initialized', [
            'session_id' => $sessionId,
            'state' => $state
        ]);
    }

    /**
     * Transition session to a new state.
     */
    public function transitionState(string $sessionId, string $newState, array $stateData = []): void
    {
        $currentState = $this->getCurrentState($sessionId);
        if (!$currentState) {
            throw new \RuntimeException("No state found for session: {$sessionId}");
        }

        $this->validateStateTransition($currentState['status'], $newState);

        $state = [
            'status' => $newState,
            'data' => array_merge($currentState['data'], $stateData),
            'timestamp' => now()
        ];

        $this->persistState($sessionId, $state);
        $this->recordStateTransition($sessionId, $currentState['status'], $newState);

        Event::dispatch('session.state.transitioned', [
            'session_id' => $sessionId,
            'from_state' => $currentState['status'],
            'to_state' => $newState,
            'state_data' => $stateData
        ]);
    }

    /**
     * Get current session state.
     */
    public function getCurrentState(string $sessionId): ?array
    {
        return Cache::get("session_state_{$sessionId}");
    }

    /**
     * Get session state history.
     */
    public function getStateHistory(string $sessionId): Collection
    {
        return SessionState::where('session_id', $sessionId)
            ->orderBy('created_at')
            ->get()
            ->map(function ($state) {
                return [
                    'from_state' => $state->from_state,
                    'to_state' => $state->to_state,
                    'timestamp' => $state->created_at,
                    'metadata' => $state->metadata
                ];
            });
    }

    /**
     * Check if state transition is valid.
     */
    public function isValidTransition(string $fromState, string $toState): bool
    {
        if (!isset($this->validTransitions[$fromState])) {
            return false;
        }

        return in_array($toState, $this->validTransitions[$fromState]);
    }

    /**
     * Save session state snapshot.
     */
    public function saveStateSnapshot(string $sessionId): string
    {
        $currentState = $this->getCurrentState($sessionId);
        if (!$currentState) {
            throw new \RuntimeException("No state found for session: {$sessionId}");
        }

        $snapshotId = uniqid('snapshot_');
        $snapshot = [
            'state' => $currentState,
            'history' => $this->getStateHistory($sessionId)->toArray(),
            'timestamp' => now()
        ];

        Cache::put("session_snapshot_{$sessionId}_{$snapshotId}", $snapshot);

        Event::dispatch('session.state.snapshot_created', [
            'session_id' => $sessionId,
            'snapshot_id' => $snapshotId
        ]);

        return $snapshotId;
    }

    /**
     * Restore session state from snapshot.
     */
    public function restoreStateSnapshot(string $sessionId, string $snapshotId): void
    {
        $snapshot = Cache::get("session_snapshot_{$sessionId}_{$snapshotId}");
        if (!$snapshot) {
            throw new \RuntimeException("Snapshot not found: {$snapshotId}");
        }

        $this->persistState($sessionId, $snapshot['state']);

        Event::dispatch('session.state.snapshot_restored', [
            'session_id' => $sessionId,
            'snapshot_id' => $snapshotId,
            'state' => $snapshot['state']
        ]);
    }

    /**
     * Export session state.
     */
    public function exportState(string $sessionId): array
    {
        $currentState = $this->getCurrentState($sessionId);
        if (!$currentState) {
            throw new \RuntimeException("No state found for session: {$sessionId}");
        }

        return [
            'current_state' => $currentState,
            'state_history' => $this->getStateHistory($sessionId)->toArray(),
            'metadata' => [
                'exported_at' => now(),
                'session_id' => $sessionId
            ]
        ];
    }

    /**
     * Import session state.
     */
    public function importState(string $sessionId, array $stateData): void
    {
        if (!isset($stateData['current_state']) || !isset($stateData['state_history'])) {
            throw new \InvalidArgumentException('Invalid state data format');
        }

        $this->persistState($sessionId, $stateData['current_state']);

        foreach ($stateData['state_history'] as $transition) {
            SessionState::create([
                'session_id' => $sessionId,
                'from_state' => $transition['from_state'],
                'to_state' => $transition['to_state'],
                'metadata' => [
                    'imported' => true,
                    'original_timestamp' => $transition['timestamp']
                ]
            ]);
        }

        Event::dispatch('session.state.imported', [
            'session_id' => $sessionId,
            'state' => $stateData['current_state']
        ]);
    }

    /**
     * Validate state transition.
     */
    protected function validateStateTransition(string $fromState, string $toState): void
    {
        if (!in_array($fromState, $this->validStates)) {
            throw new \InvalidArgumentException("Invalid current state: {$fromState}");
        }

        if (!in_array($toState, $this->validStates)) {
            throw new \InvalidArgumentException("Invalid target state: {$toState}");
        }

        if (!$this->isValidTransition($fromState, $toState)) {
            throw new \InvalidArgumentException(
                "Invalid state transition from {$fromState} to {$toState}"
            );
        }
    }

    /**
     * Persist session state.
     */
    protected function persistState(string $sessionId, array $state): void
    {
        Cache::put("session_state_{$sessionId}", $state);

        Event::dispatch('session.state.persisted', [
            'session_id' => $sessionId,
            'state' => $state
        ]);
    }

    /**
     * Record state transition in history.
     */
    protected function recordStateTransition(string $sessionId, ?string $fromState, string $toState): void
    {
        SessionState::create([
            'session_id' => $sessionId,
            'from_state' => $fromState,
            'to_state' => $toState,
            'metadata' => [
                'timestamp' => now()
            ]
        ]);

        $this->stateHistory->push([
            'session_id' => $sessionId,
            'from_state' => $fromState,
            'to_state' => $toState,
            'timestamp' => now()
        ]);
    }

    /**
     * Get valid states.
     */
    public function getValidStates(): array
    {
        return $this->validStates;
    }

    /**
     * Get valid transitions.
     */
    public function getValidTransitions(): array
    {
        return $this->validTransitions;
    }

    /**
     * Add custom state.
     */
    public function addCustomState(string $state, array $validTransitions): void
    {
        if (in_array($state, $this->validStates)) {
            throw new \InvalidArgumentException("State already exists: {$state}");
        }

        $this->validStates[] = $state;
        $this->validTransitions[$state] = $validTransitions;

        Event::dispatch('session.state.custom_state_added', [
            'state' => $state,
            'transitions' => $validTransitions
        ]);
    }

    /**
     * Add custom transition.
     */
    public function addCustomTransition(string $fromState, string $toState): void
    {
        if (!in_array($fromState, $this->validStates)) {
            throw new \InvalidArgumentException("Invalid from state: {$fromState}");
        }

        if (!in_array($toState, $this->validStates)) {
            throw new \InvalidArgumentException("Invalid to state: {$toState}");
        }

        if (!isset($this->validTransitions[$fromState])) {
            $this->validTransitions[$fromState] = [];
        }

        if (!in_array($toState, $this->validTransitions[$fromState])) {
            $this->validTransitions[$fromState][] = $toState;
        }

        Event::dispatch('session.state.custom_transition_added', [
            'from_state' => $fromState,
            'to_state' => $toState
        ]);
    }
}
