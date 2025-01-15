<?php

namespace Ajz\Anthropic\Repositories;

use Ajz\Anthropic\Models\{
    KnowledgeCollection,
    KnowledgeEntry,
    KnowledgeSession,
    KnowledgeUsageLog
};
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class KnowledgeTeamRepository
{
    /**
     * Create team session
     */
    public function createTeamSession(array $data): KnowledgeSession
    {
        return KnowledgeSession::create(array_merge(
            $data,
            ['type' => 'team']
        ));
    }

    /**
     * Get team sessions
     */
    public function getTeamSessions(array $agentIds): Collection
    {
        return KnowledgeSession::where('type', 'team')
            ->whereJsonContains('team_agents', $agentIds)
            ->get();
    }

    /**
     * Add agent to session
     */
    public function addAgentToSession(string $sessionId, string $agentId): void
    {
        KnowledgeSession::where('session_id', $sessionId)
            ->update([
                'team_agents' => \DB::raw("JSON_ARRAY_APPEND(team_agents, '$', '{$agentId}')")
            ]);
    }

    /**
     * Get session agents
     */
    public function getSessionAgents(string $sessionId): array
    {
        return KnowledgeSession::where('session_id', $sessionId)
            ->value('team_agents') ?? [];
    }

    /**
     * Log team activity
     */
    public function logTeamActivity(string $sessionId, array $data): void
    {
        KnowledgeSession::where('session_id', $sessionId)
            ->update([
                'activity_log' => \DB::raw("JSON_ARRAY_APPEND(activity_log, '$', '" . json_encode($data) . "')")
            ]);
    }

    /**
     * Get team statistics
     */
    public function getTeamStats(string $sessionId): array
    {
        $session = KnowledgeSession::where('session_id', $sessionId)->first();

        if (!$session) {
            return [];
        }

        $activityLog = json_decode($session->activity_log ?? '[]', true);
        $teamAgents = json_decode($session->team_agents ?? '[]', true);

        return [
            'total_agents' => count($teamAgents),
            'total_activities' => count($activityLog),
            'start_time' => $session->created_at->format('Y-m-d H:i:s'),
            'duration' => $session->created_at->diffForHumans(),
            'status' => $session->status
        ];
    }
}
