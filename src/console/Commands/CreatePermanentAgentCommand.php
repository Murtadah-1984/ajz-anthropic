<?php

namespace Ajz\Anthropic\Console\Commands;

use Illuminate\Console\Command;
use Ajz\Anthropic\Models\AIAssistant;
use Ajz\Anthropic\Models\AssistantRole;
use Ajz\Anthropic\Services\AIAssistantService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CreatePermanentAgentCommand extends Command
{
    protected $signature = 'ai:create-agent
                          {name : The name of the AI agent}
                          {--role= : The role of the agent (developer/architect/reviewer/etc)}
                          {--team= : Optional team ID}
                          {--description= : Agent description}
                          {--type=class : Generate as class or config}
                          {--path=app/AIAgents : Path for generated agent class}';

    protected $description = 'Create a new permanent AI agent';

    private AIAssistantService $assistantService;

    public function __construct(AIAssistantService $assistantService)
    {
        parent::__construct();
        $this->assistantService = $assistantService;
    }

    public function handle()
    {
        $name = $this->argument('name');
        $role = $this->option('role');
        $type = $this->option('type');

        if ($type === 'class') {
            $this->generateAgentClass($name, $role);
        } else {
            $this->createAgentConfig($name, $role);
        }

        $this->info("AI Agent '{$name}' created successfully!");
    }

    private function generateAgentClass(string $name, string $role): void
    {
        $className = Str::studly($name) . 'Agent';
        $path = $this->option('path');
        $namespace = str_replace('/', '\\', $path);

        $template = $this->getAgentClassTemplate($className, $namespace, $role);

        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }

        $filePath = base_path($path . '/' . $className . '.php');
        File::put($filePath, $template);

        // Create agent instance in database
        $this->createAgentRecord($name, $role, $className);

        $this->info("Generated agent class at: {$filePath}");
    }

    private function getAgentClassTemplate(string $className, string $namespace, string $role): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use App\Models\AIAssistant;
use App\Models\Conversation;
use Ajz\Anthropic\Services\AnthropicClaudeApiService;

class {$className} extends AIAssistant
{
    protected string \$role = '{$role}';
    protected array \$capabilities = [
        'primary_skills' => [],
        'supported_tasks' => [],
        'knowledge_domains' => []
    ];

    protected array \$configuration = [
        'response_style' => 'professional',
        'expertise_level' => 'expert',
        'interaction_mode' => 'proactive'
    ];

    public function __construct(
        protected readonly AnthropicClaudeApiService \$apiService
    ) {
        \$this->initializeCapabilities();
    }

    protected function initializeCapabilities(): void
    {
        // Define agent-specific capabilities
        \$this->capabilities['primary_skills'] = [
            // Add primary skills
        ];

        \$this->capabilities['supported_tasks'] = [
            // Add supported tasks
        ];

        \$this->capabilities['knowledge_domains'] = [
            // Add knowledge domains
        ];
    }

    public function processRequest(Conversation \$conversation, string \$input): array
    {
        // Add custom processing logic here
        \$context = \$this->buildContext(\$conversation);

        return \$this->apiService->createMessage([
            'role' => 'system',
            'content' => \$this->getSystemPrompt(),
            'messages' => [
                ['role' => 'user', 'content' => \$input]
            ],
            'context' => \$context
        ]);
    }

    protected function getSystemPrompt(): string
    {
        return <<<EOT
You are a specialized AI assistant with the role of {\$this->role}.
Your expertise includes: {\$this->formatCapabilities()}
Please maintain a consistent professional tone and focus on your specialized domain.
EOT;
    }

    protected function buildContext(Conversation \$conversation): array
    {
        return [
            'conversation_id' => \$conversation->id,
            'history' => \$conversation->messages()->recent()->get(),
            'capabilities' => \$this->capabilities,
            'configuration' => \$this->configuration
        ];
    }

    protected function formatCapabilities(): string
    {
        return implode(", ", \$this->capabilities['primary_skills']);
    }
}
PHP;
    }

    private function createAgentRecord(string $name, string $role, string $className): void
    {
        $roleRecord = AssistantRole::where('role_name', $role)->firstOrFail();

        AIAssistant::create([
            'name' => $name,
            'code' => Str::slug($name),
            'assistant_role_id' => $roleRecord->id,
            'team_id' => $this->option('team'),
            'class_name' => $className,
            'configuration' => [
                'description' => $this->option('description'),
                'is_permanent' => true,
                'initialization_time' => now()->toIso8601String(),
            ],
            'capabilities' => [
                'supported_tasks' => $this->getDefaultTasksForRole($role),
                'knowledge_domains' => $this->getDefaultDomainsForRole($role)
            ],
            'memory' => [
                'conversation_history' => [],
                'learned_patterns' => [],
                'performance_metrics' => []
            ],
            'is_active' => true
        ]);
    }

    private function createAgentConfig(string $name, string $role): void
    {
        $config = [
            'name' => $name,
            'role' => $role,
            'description' => $this->option('description'),
            'capabilities' => $this->getDefaultCapabilities($role),
            'configuration' => [
                'response_style' => 'professional',
                'expertise_level' => 'expert',
                'interaction_mode' => 'proactive'
            ],
            'initialization' => [
                'memory_structure' => [
                    'conversation_history' => [],
                    'learned_patterns' => [],
                    'performance_metrics' => []
                ],
                'system_prompt_template' => $this->getDefaultSystemPrompt($role)
            ]
        ];

        $path = config_path('ai_agents');
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }

        $filePath = $path . '/' . Str::slug($name) . '.php';
        File::put($filePath, '<?php return ' . var_export($config, true) . ';');

        // Create agent instance
        $this->createAgentRecord($name, $role, null);

        $this->info("Generated agent configuration at: {$filePath}");
    }

    private function getDefaultCapabilities(string $role): array
    {
        return match ($role) {
            'developer' => [
                'primary_skills' => ['code_generation', 'debugging', 'optimization'],
                'supported_tasks' => ['implementation', 'code_review', 'refactoring'],
                'knowledge_domains' => ['software_development', 'design_patterns', 'best_practices']
            ],
            'architect' => [
                'primary_skills' => ['system_design', 'architecture_patterns', 'technical_planning'],
                'supported_tasks' => ['architecture_review', 'system_planning', 'technical_documentation'],
                'knowledge_domains' => ['system_architecture', 'scalability', 'integration_patterns']
            ],
            'reviewer' => [
                'primary_skills' => ['code_analysis', 'quality_assessment', 'standards_compliance'],
                'supported_tasks' => ['code_review', 'documentation_review', 'security_review'],
                'knowledge_domains' => ['coding_standards', 'security_best_practices', 'quality_metrics']
            ],
            default => [
                'primary_skills' => ['general_assistance', 'task_management', 'communication'],
                'supported_tasks' => ['general_queries', 'task_coordination', 'information_gathering'],
                'knowledge_domains' => ['general_knowledge', 'task_management', 'communication']
            ]
        };
    }

    private function getDefaultSystemPrompt(string $role): string
    {
        return <<<EOT
You are a specialized AI assistant with the role of {$role}.
Your responses should be:
1. Professional and focused on your domain expertise
2. Based on best practices and industry standards
3. Clear and well-structured
4. Accompanied by explanations when needed

Please maintain context across conversations and learn from interactions to provide better assistance.
EOT;
    }

    private function getDefaultTasksForRole(string $role): array
    {
        return $this->getDefaultCapabilities($role)['supported_tasks'];
    }

    private function getDefaultDomainsForRole(string $role): array
    {
        return $this->getDefaultCapabilities($role)['knowledge_domains'];
    }
}
