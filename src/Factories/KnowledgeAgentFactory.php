<?php

namespace Ajz\Anthropic\Factories;

use Ajz\Anthropic\Contracts\Agent;
use Ajz\Anthropic\Services\KnowledgeBaseService;

class KnowledgeAgentFactory
{
    protected KnowledgeBaseService $knowledgeService;

    public function __construct(KnowledgeBaseService $knowledgeService)
    {
        $this->knowledgeService = $knowledgeService;
    }

    public function createFromProfession(string $profession): Agent
    {
        // Parse profession attributes
        $attributes = $this->analyzeProfession($profession);

        // Create dynamic agent class
        $agentClass = $this->createAgentClass($attributes);

        // Instantiate the agent
        return new $agentClass($attributes, $this->knowledgeService);
    }

    protected function analyzeProfession(string $profession): array
    {
        // Use Claude to analyze the profession
        $analysis = app(\Ajz\Anthropic\Services\AnthropicClaudeApiService::class)
            ->messages()
            ->create([
                'model' => 'claude-3-5-sonnet-20241022',
                'messages' => [[
                    'role' => 'user',
                    'content' => "Analyze this professional role and return a JSON structure with role details: {$profession}"
                ]],
                'system' => "You are a professional role analyzer. Extract key aspects of professional roles into structured data."
            ]);

        $attributes = json_decode($analysis->content, true);

        return array_merge($attributes, [
            'id' => str($profession)->slug(),
            'original_profession' => $profession
        ]);
    }

    protected function createAgentClass(array $attributes): string
    {
        // Generate class name
        $className = str($attributes['role'])
            ->studly()
            ->append('KnowledgeAgent');

        // Create dynamic class if it doesn't exist
        if (!class_exists($className)) {
            $this->generateAgentClass($className, $attributes);
        }

        return $className;
    }

    protected function generateAgentClass(string $className, array $attributes): void
    {
        $code = $this->generateAgentCode($className, $attributes);

        // Save to temporary file and require it
        $tempFile = storage_path("framework/cache/agents/{$className}.php");

        if (!is_dir(dirname($tempFile))) {
            mkdir(dirname($tempFile), 0755, true);
        }

        file_put_contents($tempFile, $code);
        require_once $tempFile;
    }

    protected function generateAgentCode(string $className, array $attributes): string
    {
        $capabilities = $this->generateCapabilities($attributes);
        $systemPrompt = $this->generateSystemPrompt($attributes);

        return <<<PHP
<?php

use Ajz\Anthropic\Contracts\Agent;
use Ajz\Anthropic\Traits\HasKnowledgeBase;

class {$className} implements Agent
{
    use HasKnowledgeBase;

    protected array \$attributes;
    protected array \$capabilities = {$capabilities};
    protected string \$systemPrompt = '{$systemPrompt}';

    public function __construct(array \$attributes, \$knowledgeService)
    {
        \$this->attributes = \$attributes;
        \$this->knowledgeService = \$knowledgeService;
        \$this->initializeKnowledgeBase();
    }

    public function getId(): string
    {
        return \$this->attributes['id'];
    }

    public function handleRequest(array \$request): array
    {
        \$this->setKnowledgeContext([
            'topic' => \$request['topic'] ?? null,
            'context' => \$request['context'] ?? [],
            'attributes' => \$this->attributes
        ]);

        \$knowledge = \$this->getRelevantKnowledge();

        \$response = \$this->generateResponse(
            \$this->systemPrompt,
            \$request['content'],
            \$knowledge
        );

        return [
            'content' => \$response,
            'knowledge_used' => \$knowledge->count(),
            'metadata' => [
                'agent_type' => get_class(\$this),
                'capabilities' => \$this->capabilities
            ]
        ];
    }

    protected function generateResponse(string \$systemPrompt, string \$userMessage, \$knowledge): string
    {
        \$anthropic = app(\Ajz\Anthropic\Services\AnthropicClaudeApiService::class);

        \$response = \$anthropic->messages()->create([
            'model' => 'claude-3-5-sonnet-20241022',
            'system' => \$systemPrompt,
            'messages' => [[
                'role' => 'user',
                'content' => \$this->formatPrompt(\$userMessage, \$knowledge)
            ]]
        ]);

        return \$response->content;
    }

    protected function formatPrompt(string \$userMessage, \$knowledge): string
    {
        \$knowledgeContext = \$this->formatKnowledgeForPrompt(\$knowledge);

        return <<<EOT
Based on the following knowledge:

{\$knowledgeContext}

User request:
{\$userMessage}
EOT;
    }
}
PHP;
    }

    protected function generateCapabilities(array $attributes): string
    {
        $capabilities = [
            $attributes['role'],
            ...($attributes['specializations'] ?? []),
            ...($attributes['principles'] ?? [])
        ];

        return var_export($capabilities, true);
    }

    protected function generateSystemPrompt(array $attributes): string
    {
        $role = $attributes['role'];
        $seniority = $attributes['seniority'] ?? 'Professional';
        $specializations = implode(', ', $attributes['specializations'] ?? []);
        $principles = implode(', ', $attributes['principles'] ?? []);

        return <<<EOT
You are a {$seniority} {$role} with expertise in {$specializations}.
You follow these principles: {$principles}.
Provide detailed, technically accurate responses based on your knowledge and expertise.
EOT;
    }
}
