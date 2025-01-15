<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Builders;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Cache;
use Ajz\Anthropic\Models\AssistantRole;
use Ajz\Anthropic\Services\XmlHandler;
use Ajz\Anthropic\DTOs\SystemPromptConfig;
use Ajz\Anthropic\Events\AssistantOutputGenerated;

final class SystemPromptBuilder
{
    const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private readonly XmlHandler $xmlHandler,
        private readonly Pipeline $pipeline
    ) {}

    public static function build(string $roleName): string
    {
        return app(self::class)->buildPrompt($roleName);
    }

    public function buildPrompt(string $roleName): string
    {
        return Cache::remember(
            "assistant_prompt:{$roleName}",
            self::CACHE_TTL,
            fn () => $this->generatePrompt($roleName)
        );
    }

    private function generatePrompt(string $roleName): string
    {
        $role = AssistantRole::where('role_name', $roleName)->firstOrFail();
        $config = $this->parseConfig($role);

        return $this->pipeline
            ->send($config)
            ->through($this->getPipes())
            ->then(fn ($result) => $this->finalizePrompt($result, $role));
    }

    private function parseConfig(AssistantRole $role): SystemPromptConfig
    {
        $configData = $this->xmlHandler->parseConfig($role->xml_config);

        return new SystemPromptConfig(
            role: $role->role_name,
            context: $configData['context'],
            guidelines: $configData['guidelines'],
            examples: $configData['examples'],
            outputFormat: $configData['output_format'],
            bestPractices: $configData['best_practices']
        );
    }

    public function recordOutput(string $roleName, string $output, int $feedbackScore): void
    {
        $role = AssistantRole::where('role_name', $roleName)->firstOrFail();

        // Update XML output history
        $xmlOutput = $role->xml_output ?? '<?xml version="1.0"?><outputs></outputs>';
        $updatedXmlOutput = $this->xmlHandler->appendOutput($xmlOutput, $output, $feedbackScore);

        // Store in database
        $role->update(['xml_output' => $updatedXmlOutput]);

        // Create output record
        $role->outputs()->create([
            'output' => $output,
            'feedback_score' => $feedbackScore,
            'metadata' => [
                'timestamp' => now()->toIso8601String(),
                'analysis' => $this->xmlHandler->analyzeOutputHistory($updatedXmlOutput)
            ]
        ]);

        // Clear cache
        Cache::forget("assistant_prompt:{$roleName}");

        // Dispatch event for potential listeners
        event(new AssistantOutputGenerated($role, $output, $feedbackScore));
    }

    private function getPipes(): array
    {
        return [
            \Ajz\Anthropic\Pipes\RoleDefinition::class,
            \Ajz\Anthropic\Pipes\ContextBuilder::class,
            \Ajz\Anthropic\Pipes\TaskGuidelines::class,
            \Ajz\Anthropic\Pipes\ExampleFormatter::class,
            \Ajz\Anthropic\Pipes\OutputFormatting::class,
            \Ajz\Anthropic\Pipes\BestPracticesEnforcer::class,
            \Ajz\Anthropic\Pipes\HistoricalPerformanceAnalyzer::class,
        ];
    }

    private function finalizePrompt(SystemPromptConfig $config, AssistantRole $role): string
    {
        if ($role->xml_output) {
            $analysis = $this->xmlHandler->analyzeOutputHistory($role->xml_output);

            // Enhance prompt with historical performance insights
            $config = $config->addComponent(
                'historical_performance',
                $this->formatHistoricalInsights($analysis)
            );
        }

        return json_encode([
            'role' => 'system',
            'content' => collect($config->getAllComponents())->implode("\n\n")
        ]);
    }

    private function formatHistoricalInsights(array $analysis): string
    {
        $insights = "Historical Performance Insights:\n";

        if (!empty($analysis['high_performing_patterns'])) {
            $insights .= "\nHighly Rated Output Patterns:\n";
            foreach (array_slice($analysis['high_performing_patterns'], 0, 3) as $pattern) {
                $insights .= "- Pattern with score {$pattern['score']}: {$pattern['content']}\n";
            }
        }

        $insights .= "\nAverage Feedback Score: {$analysis['average_feedback']}\n";

        return $insights;
    }

    /**
     * @return XmlHandler
     */
    public function getXmlHandler()
    {
        return $this->xmlHandler;
    }
}


