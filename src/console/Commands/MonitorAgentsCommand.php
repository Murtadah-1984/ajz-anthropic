<?php

namespace Ajz\Anthropic\Commands;

use Illuminate\Console\Command;
use Ajz\Anthropic\Contracts\AIManagerInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;

class MonitorAgentsCommand extends Command
{
    protected $signature = 'anthropic:monitor-agents
                          {--interval=5 : Refresh interval in seconds}
                          {--filter= : Filter agents by type}';

    protected $description = 'Monitor active AI agents in real-time';

    protected AIManagerInterface $aiManager;

    public function __construct(AIManagerInterface $aiManager)
    {
        parent::__construct();
        $this->aiManager = $aiManager;
    }

    public function handle()
    {
        try {
            $this->info('Starting Agent Monitor...');
            $this->info('Press Ctrl+C to stop');
            $this->newLine();

            while (true) {
                // Clear screen
                $this->output->write(sprintf("\033\143"));

                $agents = $this->getActiveAgents();
                $this->displayAgentStatus($agents);

                sleep($this->option('interval'));
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Monitor failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function getActiveAgents()
    {
        $agents = $this->aiManager->getActiveAgents();

        if ($filter = $this->option('filter')) {
            $agents = array_filter($agents, function ($agent) use ($filter) {
                return $agent['type'] === $filter;
            });
        }

        return $agents;
    }

    protected function displayAgentStatus($agents)
    {
        $this->info('Active Agents Monitor');
        $this->info('Last Updated: ' . now()->format('Y-m-d H:i:s'));
        $this->newLine();

        if (empty($agents)) {
            $this->warn('No active agents found.');
            return;
        }

        $rows = [];
        foreach ($agents as $agent) {
            $rows[] = [
                $agent['name'],
                $agent['type'],
                $this->getStatusIndicator($agent['status']),
                $agent['current_task'] ?? 'Idle',
                $this->formatMetrics($agent['metrics'] ?? [])
            ];
        }

        $this->table(
            ['Name', 'Type', 'Status', 'Current Task', 'Metrics'],
            $rows
        );

        // Display system metrics
        $this->displaySystemMetrics();
    }

    protected function getStatusIndicator($status)
    {
        $indicators = [
            'active' => '<fg=green>●</> Active',
            'busy' => '<fg=yellow>●</> Busy',
            'error' => '<fg=red>●</> Error',
            'idle' => '<fg=blue>●</> Idle'
        ];

        return $indicators[$status] ?? '<fg=gray>●</> Unknown';
    }

    protected function formatMetrics($metrics)
    {
        if (empty($metrics)) {
            return 'No metrics available';
        }

        return implode("\n", array_map(function ($key, $value) {
            return "{$key}: {$value}";
        }, array_keys($metrics), $metrics));
    }

    protected function displaySystemMetrics()
    {
        $metrics = $this->aiManager->getSystemMetrics();

        $this->newLine();
        $this->info('System Metrics:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['CPU Usage', $metrics['cpu_usage'] . '%'],
                ['Memory Usage', $metrics['memory_usage'] . '%'],
                ['Active Sessions', $metrics['active_sessions']],
                ['Pending Tasks', $metrics['pending_tasks']],
                ['Average Response Time', $metrics['avg_response_time'] . 'ms']
            ]
        );
    }
}
