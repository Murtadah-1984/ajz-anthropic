<?php


// app/Models/KnowledgeSession.php

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeSession extends Model
{
    protected $fillable = [
        'session_id',
        'type',
        'team_agents',
        'options',
        'activity_log',
        'status'
    ];

    protected $casts = [
        'team_agents' => 'array',
        'options' => 'array',
        'activity_log' => 'array'
    ];

    public function getAgents(): array
    {
        return $this->team_agents ?? [];
    }

    public function addAgent(string $agentId): void
    {
        $agents = $this->getAgents();
        $agents[] = $agentId;
        $this->team_agents = array_unique($agents);
        $this->save();
    }

    public function logActivity(array $data): void
    {
        $log = $this->activity_log ?? [];
        $log[] = array_merge($data, [
            'timestamp' => now()->toDateTimeString()
        ]);
        $this->activity_log = $log;
        $this->save();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function end(): void
    {
        $this->status = 'completed';
        $this->save();
    }

    public function getStats(): array
    {
        $log = $this->activity_log ?? [];

        return [
            'total_activities' => count($log),
            'agent_activities' => collect($log)
                ->groupBy('agent_id')
                ->map(fn($items) => count($items))
                ->toArray(),
            'duration' => $this->created_at->diffForHumans(),
            'status' => $this->status
        ];
    }
}
