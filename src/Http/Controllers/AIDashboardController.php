<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Http\Controllers;

use Ajz\Anthropic\Facades\AI;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class AIDashboardController extends Controller
{
    public function index()
    {
        return view('ai-dashboard.index');
    }

    public function getSystemStats()
    {
        $stats = Cache::remember('ai_system_stats', 60, function () {
            return [
                'activeAgents' => AI::getActiveAgentCount(),
                'activeSessions' => AI::getActiveSessionCount(),
                'pendingTasks' => AI::getPendingTaskCount(),
                'completedTasks' => AI::getCompletedTaskCount(),
            ];
        });

        return response()->json($stats);
    }

    public function getRecentActivities()
    {
        $activities = AI::getRecentActivities();
        return response()->json($activities);
    }

    public function getAgentMetrics()
    {
        $metrics = Cache::remember('ai_agent_metrics', 300, function () {
            return [
                'performance' => AI::getAgentPerformanceMetrics(),
                'utilization' => AI::getAgentUtilizationMetrics(),
                'responseTime' => AI::getAgentResponseTimeMetrics(),
            ];
        });

        return response()->json($metrics);
    }

    public function getActiveAgents()
    {
        $agents = AI::getActiveAgents();
        return response()->json($agents);
    }

    public function getActiveSessions()
    {
        $sessions = AI::getActiveSessions();
        return response()->json($sessions);
    }
}
