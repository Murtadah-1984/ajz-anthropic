<?php

namespace Ajz\Anthropic\Console\Commands;

use Illuminate\Console\Command;
use Ajz\Anthropic\Contracts\AIManagerInterface;
use Illuminate\Support\Facades\Config;

class ListAgentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'anthropic:agents:list
                          {--type= : Filter by agent type}
                          {--capability= : Filter by capability}
                          {--detailed : Show detailed information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List available AI agents and their capabilities';

    /**
     * The AI manager instance.
     *
     * @var AIManagerInterface
     */
    protected AIManagerInterface $aiManager;

    /**
     * Create a new command instance.
     *
     * @param AIManagerInterface $aiManager
     */
    public function __construct(AIManagerInterface $aiManager)
    {
        parent::__construct();
        $this->aiManager = $aiManager;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        try {
            $agents = $this->getFilteredAgents();

            if (empty($agents)) {
                $this->warn('No agents found matching the specified criteria.');
                return 0;
            }

            $this->displayAgents($agents);
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to list agents: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Get filtered list of agents.
     *
     * @return array
     */
    protected function getFilteredAgents(): array
    {
        $agents = Config::get('anthropic.agents', []);
        $type = $this->option('type');
        $capability = $this->option('capability');

        if ($type) {
            $agents = array_filter($agents, function ($agent, $key) use ($type) {
                return $key === $type;
            }, ARRAY_FILTER_USE_BOTH);
        }

        if ($capability) {
            $agents = array_filter($agents, function ($agent) use ($capability) {
                return in_array($capability, $agent['capabilities'] ?? []);
            });
        }

        return $agents;
    }

    /**
     * Display agents in a formatted table.
     *
     * @param array $agents
     * @return void
     */
    protected function displayAgents(array $agents): void
    {
        if ($this->option('detailed')) {
            $this->displayDetailedInfo($agents);
        } else {
            $this->displayBasicInfo($agents);
        }
    }

    /**
     * Display basic agent information.
     *
     * @param array $agents
     * @return void
     */
    protected function displayBasicInfo(array $agents): void
    {
        $rows = [];
        foreach ($agents as $type => $config) {
            $rows[] = [
                'Type' => $type,
                'Class' => class_basename($config['class']),
                'Model' => isset($config['model']) ? $config['model'] : 'N/A',
                'Capabilities' => implode(', ', $config['capabilities'] ?? []),
            ];
        }

        $this->table(['Type', 'Class', 'Model', 'Capabilities'], $rows);
    }

    /**
     * Display detailed agent information.
     *
     * @param array $agents
     * @return void
     */
    protected function displayDetailedInfo(array $agents): void
    {
        foreach ($agents as $type => $config) {
            $this->info("\nAgent Type: $type");
            $this->line('----------------------------------------');
            $this->line("Class: {$config['class']}");

            $model = isset($config['model']) ? $config['model'] : 'N/A';
            $this->line("Model: {$model}");

            $maxTokens = isset($config['max_tokens']) ? $config['max_tokens'] : 'Default';
            $this->line("Max Tokens: {$maxTokens}");

            if (!empty($config['capabilities'])) {
                $this->line("\nCapabilities:");
                foreach ($config['capabilities'] as $capability) {
                    $this->line("  - $capability");
                }
            }

            // Try to instantiate the agent to get more information
            try {
                $agent = $this->aiManager->createAgent($type);
                if (method_exists($agent, 'getDescription')) {
                    $this->line("\nDescription:");
                    $this->line("  " . $agent->getDescription());
                }
            } catch (\Exception $e) {
                $this->warn("  Could not load agent instance: {$e->getMessage()}");
            }

            $this->newLine();
        }
    }
}
