# Laravel Anthropic

[Previous installation and basic usage sections remain the same...]

## Available Methods

### AI Facade Methods

```php
use Ajz\Anthropic\Facades\AI;

// Agent Management
AI::agent(string $type)                    // Get an AI agent instance
AI::team(string $teamId)                   // Get an AI team instance
AI::broker()                               // Get message broker instance
AI::integrate(string $provider, array $data) // Use external integration

// Session Management
AI::startSession(string $type, array $options = [])
AI::brainstorm(string $topic, array $options = [])
```

### Session Types and Methods

#### API Design Session
```php
$session = AI::startSession('api_design', [
    'resource' => string,      // Resource name
    'features' => array,       // Required features
    'output_path' => string,   // Output directory
    'create_git_commit' => bool
]);

// Available methods
$session->start(): void
$session->getSpecification(): array
$session->getDocumentation(): array
```

#### Brainstorming Session
```php
$session = AI::startSession('brainstorm', [
    'topic' => string,
    'participants' => array,
    'constraints' => array
]);

// Available methods
$session->start(): void
$session->submitIdea(string $agentId, array $idea): void
$session->commentOnIdea(string $agentId, string $ideaId, string $comment): void
$session->buildUponIdea(string $agentId, string $ideaId, array $enhancement): void
$session->startVoting(): void
$session->submitVote(string $agentId, array $votes): void
```

#### Code Review Session
```php
$session = AI::startSession('code_review', [
    'repository' => string,
    'pull_request' => int,
    'focus_areas' => array
]);

// Available methods
$session->start(): void
$session->reviewFile(string $path): array
$session->suggestImprovements(): array
$session->generateTestCases(): array
```

#### Architecture Review Session
```php
$session = AI::startSession('architecture_review', [
    'system_name' => string,
    'scalability_requirements' => string,
    'security_level' => string
]);

// Available methods
$session->start(): void
$session->reviewArchitecture(): array
$session->suggestImprovements(): array
$session->generateDiagrams(): array
```

#### Technical Debt Session
```php
$session = AI::startSession('tech_debt', [
    'codebase' => string,
    'priority_areas' => array
]);

// Available methods
$session->start(): void
$session->analyzeTechDebt(): array
$session->prioritizeIssues(): array
$session->createRefactoringPlan(): array
```

#### Security Audit Session
```php
$session = AI::startSession('security_audit', [
    'scope' => array,
    'compliance' => array
]);

// Available methods
$session->start(): void
$session->performAudit(): array
$session->generateReport(): array
$session->suggestMitigations(): array
```

#### Performance Optimization Session
```php
$session = AI::startSession('performance_optimization', [
    'metrics' => array,
    'targets' => array
]);

// Available methods
$session->start(): void
$session->analyzePerformance(): array
$session->suggestOptimizations(): array
$session->createImplementationPlan(): array
```

#### Documentation Sprint Session
```php
$session = AI::startSession('documentation', [
    'scope' => array,
    'format' => string,
    'audience' => string
]);

// Available methods
$session->start(): void
$session->generateDocumentation(): array
$session->reviewDocumentation(): array
$session->suggestImprovements(): array
```

#### Knowledge Transfer Session
```php
$session = AI::startSession('knowledge_transfer', [
    'subject' => string,
    'priority_areas' => array,
    'timeline' => string
]);

// Available methods
$session->start(): void
$session->createTransferPlan(): array
$session->generateMaterials(): array
$session->assessProgress(): array
```

#### Feature Discovery Session
```php
$session = AI::startSession('feature_discovery', [
    'product' => string,
    'feature' => string,
    'stakeholders' => array
]);

// Available methods
$session->start(): void
$session->gatherRequirements(): array
$session->createUserStories(): array
$session->estimateEffort(): array
```

#### System Design Session
```php
$session = AI::startSession('system_design', [
    'requirements' => array,
    'constraints' => array
]);

// Available methods
$session->start(): void
$session->createDesign(): array
$session->reviewDesign(): array
$session->generateDiagrams(): array
```

#### Quality Assurance Session
```php
$session = AI::startSession('quality_assurance', [
    'scope' => array,
    'requirements' => array
]);

// Available methods
$session->start(): void
$session->createTestPlan(): array
$session->generateTestCases(): array
$session->reviewCoverage(): array
```

#### Release Planning Session
```php
$session = AI::startSession('release_planning', [
    'version' => string,
    'features' => array
]);

// Available methods
$session->start(): void
$session->planRelease(): array
$session->generateChangelog(): array
$session->createDeploymentPlan(): array
```

### Integration Methods

#### Google Docs Integration
```php
AI::integrate('google_docs', [
    'title' => string,
    'content' => string|array,
    'template' => string,
    'sharing' => array
]): array
```

#### Airtable Integration
```php
AI::integrate('airtable', [
    'table' => string,
    'records' => array,
    'view' => string
]): array
```

#### Make.com Integration
```php
AI::integrate('make', [
    'trigger' => string,
    'data' => array
]): array
```

#### N8N Integration
```php
AI::integrate('n8n', [
    'workflow' => string,
    'data' => array
]): array
```

# Laravel Anthropic

A Laravel package for integrating with the Anthropic AI API, providing access to Claude, AI Agents, automated sessions, and external system integrations.

## Installation

You can install the package via composer:

```bash
composer require ajz/anthropic
```

## Configuration

Publish the configuration files:

```bash
php artisan vendor:publish --tag="anthropic-config"
php artisan vendor:publish --tag="integrations-config"
```

Add these environment variables to your `.env` file:

```env
ANTHROPIC_API_KEY=your-api-key
ANTHROPIC_ADMIN_API_KEY=your-admin-api-key
ANTHROPIC_API_VERSION=2023-06-01

# External Integrations
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
AIRTABLE_API_KEY=your-airtable-key
MAKE_WEBHOOK_KEY=your-make-webhook-key
N8N_API_KEY=your-n8n-api-key
```

## Basic Usage

```php
// Using the facade
use Ajz\Anthropic\Facades\Anthropic;

// Send a message to Claude
$response = Anthropic::messages()->createMessage(
    'claude-3-5-sonnet-20241022',
    [
        ['role' => 'user', 'content' => 'Hello, Claude']
    ]
);
```

## AI Agents and Sessions

```php
use Ajz\Anthropic\Facades\AI;

// Start an API design session
$session = AI::startSession('api_design', [
    'resource' => 'products',
    'features' => ['crud', 'bulk_operations', 'versioning'],
    'output_path' => base_path('api')
]);

// Start brainstorming session
$brainstorm = AI::startSession('brainstorm', [
    'topic' => 'API Security Improvements',
    'participants' => ['security_expert', 'architect', 'developer']
]);

// Create permanent agent
$agent = AI::createAgent('developer', [
    'name' => 'Senior PHP Developer',
    'capabilities' => ['api_design', 'code_review', 'refactoring']
]);

// Use specific agent
$response = AI::agent('developer')->handleRequest($input);

// Use team of agents
$response = AI::team('development')->handleRequest($input);
```

## External Integrations

```php
// Google Docs Integration
$docResult = AI::integrate('google_docs', [
    'title' => 'API Documentation',
    'content' => $apiSpec,
    'template' => 'api_documentation'
]);

// Airtable Integration
$airtableResult = AI::integrate('airtable', [
    'table' => 'projects',
    'records' => [
        [
            'fields' => [
                'Name' => 'API Development',
                'Status' => 'In Progress'
            ]
        ]
    ]
]);

// Make.com Integration
$makeResult = AI::integrate('make', [
    'trigger' => 'task_created',
    'data' => [
        'task' => 'Review API Security',
        'priority' => 'high'
    ]
]);

// N8N Integration
$n8nResult = AI::integrate('n8n', [
    'workflow' => 'api_integration',
    'data' => [
        'action' => 'update_documentation',
        'payload' => $changes
    ]
]);
```

## Available Sessions

- `api_design` - API design and implementation
- `brainstorm` - Team brainstorming sessions
- `code_review` - Code review sessions
- `architecture_review` - Architecture review sessions
- `feature_discovery` - Feature planning sessions
- `security_audit` - Security review sessions
- `performance_optimization` - Performance analysis
- `documentation` - Documentation sprints
- `knowledge_transfer` - Knowledge transfer sessions

## Available Agents

- `developer` - Code generation and review
- `architect` - System design and architecture
- `security_expert` - Security analysis
- `performance_expert` - Performance optimization
- `documentation_expert` - Technical writing
- `front_desk` - Request routing and coordination

## Session Outputs

Sessions can generate structured outputs in designated directories:

```php
// API Design Session Output Structure
api/
├── Controllers/
├── Models/
├── Requests/
├── Resources/
├── Services/
├── Tests/
│   ├── Feature/
│   └── Unit/
├── docs/
│   ├── openapi.yaml
│   ├── API.md
│   └── postman_collection.json
├── routes/
└── config/
```


## Permanent Agents

### Creating Agents

You can create new AI agents using the provided artisan command:

```bash
# Basic agent creation
php artisan ai:create-agent "Senior PHP Developer" --role=developer

# Create agent with team assignment
php artisan ai:create-agent "Security Expert" --role=security --team=security_team

# Create agent as class
php artisan ai:create-agent "API Architect" --role=architect --type=class --path=app/AIAgents

# Create agent as config
php artisan ai:create-agent "Code Reviewer" --role=reviewer --type=config
```

### Command Options

```bash
Options:
  --role=         The role of the agent (developer/architect/reviewer/etc)
  --team=         Optional team ID
  --description=  Agent description
  --type=        Generate as class or config (default: class)
  --path=        Path for generated agent class (default: app/AIAgents)
```

### Agent Types and Roles

```php
// Available Roles
enum AssistantRole: string
{
    case DEVELOPER = 'developer';
    case ARCHITECT = 'architect';
    case CODE_REVIEWER = 'code_reviewer';
    case TECHNICAL_WRITER = 'technical_writer';
    case SECURITY_EXPERT = 'security_expert';
}
```

### Agent Class Structure

When creating an agent as a class, it will generate:

```php
namespace Ajz\Anthropic\AIAgents;

use Ajz\Anthropic\Models\AIAssistant;
use Ajz\Anthropic\Models\Conversation;
use Ajz\Anthropic\Services\AnthropicClaudeApiService;

class SeniorPhpDeveloperAgent extends AIAssistant
{
    protected string $role = 'developer';
    protected array $capabilities = [
        'primary_skills' => [
            'code_generation',
            'debugging',
            'optimization'
        ],
        'supported_tasks' => [
            'implementation',
            'code_review',
            'refactoring'
        ],
        'knowledge_domains' => [
            'software_development',
            'design_patterns',
            'best_practices'
        ]
    ];

    protected array $configuration = [
        'response_style' => 'professional',
        'expertise_level' => 'expert',
        'interaction_mode' => 'proactive'
    ];

    public function processRequest(Conversation $conversation, string $input): array
    {
        // Custom processing logic
    }

    protected function buildContext(Conversation $conversation): array
    {
        // Custom context building
    }
}
```

### Using Permanent Agents

```php
// Get agent instance
$developer = AI::agent('senior_php_developer');

// Process request
$response = $developer->processRequest($conversation, $input);

// Direct interaction
$response = $developer->handleRequest([
    'type' => 'code_review',
    'content' => $codeSnippet,
    'context' => [
        'language' => 'php',
        'framework' => 'laravel'
    ]
]);

// Team collaboration
$team = AI::team('development');
$team->addAgent($developer);
$response = $team->handleRequest($input);
```

### Agent Configuration

```php
// config/ai_agents/senior_php_developer.php
return [
    'name' => 'Senior PHP Developer',
    'role' => 'developer',
    'description' => 'Expert PHP developer specializing in Laravel',
    'capabilities' => [
        'primary_skills' => [
            'code_generation',
            'debugging',
            'optimization'
        ],
        'supported_tasks' => [
            'implementation',
            'code_review',
            'refactoring'
        ],
        'knowledge_domains' => [
            'software_development',
            'design_patterns',
            'best_practices'
        ]
    ],
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
        'system_prompt_template' => [
            'role_definition' => 'You are an expert PHP developer...',
            'expertise_areas' => ['Laravel', 'PHP', 'Design Patterns'],
            'communication_style' => 'professional and detailed'
        ]
    ]
];
```

### Agent Memory and Learning

Permanent agents maintain state and learn from interactions:

```php
// Access agent memory
$patterns = $developer->getLearnedPatterns();
$metrics = $developer->getPerformanceMetrics();

// Update agent configuration
$developer->updateConfiguration([
    'expertise_level' => 'senior',
    'response_style' => 'detailed'
]);

// Provide feedback
$developer->learnFromFeedback([
    'rating' => 4,
    'improvements' => ['more_examples', 'simpler_explanations'],
    'successful_patterns' => ['code_examples', 'step_by_step']
]);
```

### Agent Teams

```php
// Create a team of permanent agents
$team = AI::createTeam('development', [
    'name' => 'Development Team',
    'agents' => [
        'senior_php_developer',
        'security_expert',
        'code_reviewer'
    ],
    'workflow' => 'collaborative'
]);

// Use team for complex tasks
$response = $team->handleRequest([
    'type' => 'feature_implementation',
    'description' => 'Implement secure payment gateway',
    'requirements' => [
        'security_level' => 'PCI-DSS',
        'performance' => 'high'
    ]
]);
```

## Original Services

- `messages()` - Claude messaging API
- `workspaces()` - Workspace management
- `workspaceMembers()` - Workspace member management
- `organization()` - Organization management
- `invites()` - Organization invite management
- `apiKeys()` - API key management

# Laravel Anthropic

[Previous sections remain the same...]

## Agent Communication Components

### Agent Message

Agent messages are used for inter-agent communication:

```php
use Ajz\Anthropic\AIAgents\Communication\AgentMessage;

// Create a message
$message = new AgentMessage(
    senderId: 'architect',                    // ID of sending agent
    content: 'Message content',               // Message content
    metadata: [                               // Additional metadata
        'type' => 'implementation_request',
        'priority' => 'high'
    ],
    requiredCapabilities: [                   // Required capabilities
        'code_generation',
        'system_design'
    ],
    conversationId: '123'                     // Optional conversation ID
);

// Available methods
$message->getRequiredCapabilities(): array    // Get required capabilities
$message->toArray(): array                    // Convert message to array
```

### Message Broker

The message broker handles agent communication and message routing:

```php
use App\AIAgents\Communication\AgentMessageBroker;

// Register an agent
$broker->registerAgent(
    agentId: 'developer_1',
    capabilities: [
        'code_generation',
        'debugging',
        'optimization'
    ]
);

// Route a message
$broker->routeMessage($message);

// Methods
$broker->registerAgent(string $agentId, array $capabilities): void
$broker->routeMessage(AgentMessage $message): void
```

### Asynchronous Communication

For handling asynchronous agent communication:

```php
use Ajz\Anthropic\AIAgents\Communication\AsyncAgentCommunication;
use Ajz\Anthropic\AIAgents\Communication\ProcessAgentMessage;

// Queue async communication
$asyncComm = new AsyncAgentCommunication();
$asyncComm->handle($message, 'target_agent');

// Process message job
$processor = new ProcessAgentMessage($message, 'target_agent');
$processor->handle($broker);

// Agent Response
$response = new AgentResponse(
    content: 'Response content',
    metadata: ['status' => 'completed'],
    requiresFollowUp: true
);

// Add follow-up messages
$response->addFollowUpMessage($followUpMessage);

// Check if follow-up needed
if ($response->requiresFollowUp()) {
    $messages = $response->getFollowUpMessages();
}
```

### Example Communication Flow

```php
// Project planning example
class ProjectManager
{
    public function planProject(string $description): void
    {
        // Get architecture plan
        $architectResponse = AI::agent('architect')->createPlan($description);

        // Send to developer
        $developerMessage = new AgentMessage(
            senderId: 'architect',
            content: $architectResponse->content,
            metadata: [
                'type' => 'implementation_request',
                'priority' => 'high'
            ],
            requiredCapabilities: ['code_generation', 'system_design']
        );

        AI::agent('developer')->receiveMessage($developerMessage);

        // Send for security review
        $securityMessage = new AgentMessage(
            senderId: 'architect',
            content: $architectResponse->content,
            metadata: [
                'type' => 'security_review',
                'priority' => 'high'
            ],
            requiredCapabilities: ['security_analysis']
        );

        AI::agent('security')->receiveMessage($securityMessage);
    }
}
```

### AI Service Manager

The AI Manager handles agent and team instantiation:

```php
use App\Services\AIManager;

class AIManager
{
    // Available methods
    public function getAgent(string $type): ?object
    public function getTeam(string $teamId): ?object
    private function createAgent(string $type): object
}

// Usage via facade
$developer = AI::agent('developer');
$team = AI::team('development');
```

### Controller Usage

```php
use App\Facades\AI;

class AIController extends Controller
{
    public function handleRequest(Request $request)
    {
        $response = match ($request->type) {
            'code' => AI::agent('developer')->handleRequest($request->input),
            'design' => AI::agent('architect')->handleRequest($request->input),
            'team' => AI::team($request->team_id)->handleRequest($request->input),
            default => throw new InvalidArgumentException('Unknown request type')
        };

        return response()->json($response);
    }
}
```

### Message Queue Processing

```php
// Queue configuration (config/queue.php)
'agent_communication' => [
    'driver' => 'redis',
    'connection' => 'default',
    'queue' => 'agent_messages',
    'retry_after' => 90,
    'block_for' => null,
]

// Process message
class ProcessAgentMessage implements ShouldQueue
{
    public function __construct(
        private readonly AgentMessage $message,
        private readonly string $targetAgent
    ) {}

    public function handle(AgentMessageBroker $broker): void
    {
        // Process message
        $agent = app('ai.manager')->getAgent($this->targetAgent);
        $response = $agent->processMessage($this->message);

        // Handle follow-ups
        if ($response->requiresFollowUp()) {
            foreach ($response->getFollowUpMessages() as $message) {
                $broker->routeMessage($message);
            }
        }
    }
}
```

### Event Handling

```php
// Listen for agent messages
Event::listen(function (AgentMessageSent $event) {
    Log::info('Agent message sent', [
        'message' => $event->message->toArray(),
        'target' => $event->targetAgent
    ]);
});
```
# Laravel Anthropic

[Previous sections remain the same...]

## Agency Services

### AIManager Service

Core service for managing AI agents and teams:

```php
use Ajz\Anthropic\Services\Agency\AIManager;

// Available Methods
$manager->agent(string $type): object              // Get or create AI agent instance
$manager->team(string $teamId): object             // Get or create AI team instance
$manager->broker(): AgentMessageBroker             // Get message broker instance
$manager->startBrainstorming(string $topic, array $options = []): BrainstormSession  // Start brainstorming
$manager->createSession(string $type, array $options = []): BaseSession  // Create session

// Usage
$manager = app(AIManager::class);
$developer = $manager->agent('developer');
$team = $manager->team('development');
$broker = $manager->broker();

// Start brainstorming
$session = $manager->startBrainstorming('API Security Improvements', [
    'agents' => ['security_expert', 'architect']
]);

// Create session
$session = $manager->createSession('code_review', [
    'repository' => 'github.com/org/project',
    'pull_request' => 123
]);
```

### AITeamService

Service for managing AI teams and task delegation:

```php
use Ajz\Anthropic\Services\Agency\AITeamService;

// Available Methods
$service->createTeam(string $name, string $code, array $configuration): Team
$service->handleTeamTask(Team $team, string $task, array $context = []): Conversation
$service->monitorTeamProgress(Team $team): array

// Usage
$teamService = app(AITeamService::class);

// Create team
$team = $teamService->createTeam('Development', 'dev_team', [
    'capabilities' => ['code_review', 'architecture'],
    'workflow' => 'collaborative',
    'assistants' => [
        [
            'name' => 'Senior Developer',
            'role' => 'developer'
        ],
        [
            'name' => 'Security Expert',
            'role' => 'security'
        ]
    ]
]);

// Handle team task
$conversation = $teamService->handleTeamTask($team, 
    'Review security implementation of payment gateway',
    ['priority' => 'high']
);

// Monitor progress
$metrics = $teamService->monitorTeamProgress($team);
```

### FrontDeskAIService

Service for handling initial user requests and routing:

```php
use Ajz\Anthropic\Services\Agency\FrontDeskAIService;

// Available Methods
$service->handleRequest(User $user, string $request): Conversation

// Usage
$frontDesk = app(FrontDeskAIService::class);

// Handle user request
$conversation = $frontDesk->handleRequest(
    $user,
    'I need help optimizing our database queries'
);
```

### PersonalAIAssistantService

Service for managing personal AI assistants:

```php
use Ajz\Anthropic\Services\Agency\PersonalAIAssistantService;

// Available Methods
$service->createPersonalAssistant(User $user, array $configuration): AIAssistant
$service->handleRequest(AIAssistant $assistant, string $request): array
$service->learnFromFeedback(AIAssistant $assistant, array $feedback): void
$service->analyzeInteractionPatterns(AIAssistant $assistant): array

// Usage
$personalAssistant = app(PersonalAIAssistantService::class);

// Create personal assistant
$assistant = $personalAssistant->createPersonalAssistant($user, [
    'preferences' => [
        'communication_style' => 'casual',
        'response_format' => 'concise'
    ]
]);

// Handle request
$response = $personalAssistant->handleRequest($assistant, 
    'What meetings do I have today?'
);

// Provide feedback
$personalAssistant->learnFromFeedback($assistant, [
    'rating' => 5,
    'preferences' => [
        'response_style' => 'detailed'
    ],
    'successful_patterns' => ['step_by_step_explanation']
]);

// Analyze patterns
$patterns = $personalAssistant->analyzeInteractionPatterns($assistant);
```

### WorkflowOrchestrationService

Service for managing complex AI conversation workflows:

```php
use Ajz\Anthropic\Services\Agency\WorkflowOrchestrationService;

// Available Methods
$service->orchestrateWorkflow(Conversation $conversation): void

// Usage
$orchestrator = app(WorkflowOrchestrationService::class);

// Orchestrate workflow
$orchestrator->orchestrateWorkflow($conversation);

// Example workflow configuration
$workflow = [
    'type' => 'sequential',
    'steps' => [
        [
            'id' => 'understanding',
            'description' => 'Analyze request',
            'requirements' => [
                'capabilities' => ['comprehension']
            ]
        ],
        [
            'id' => 'planning',
            'description' => 'Develop strategy',
            'requirements' => [
                'capabilities' => ['planning']
            ]
        ]
    ],
    'conditions' => [
        'prerequisites' => [],
        'transitions' => [],
        'completion_criteria' => []
    ],
    'fallback' => [
        'validation_failure' => [
            'max_retries' => 3
        ]
    ]
];
```

## Events

Available events for monitoring and hooks:

```php
use Ajz\Anthropic\Events\WorkflowStepCompleted;
use Ajz\Anthropic\Events\WorkflowCompleted;
use Ajz\Anthropic\Events\AgentMessageSent;

// Listen for workflow events
Event::listen(function (WorkflowStepCompleted $event) {
    Log::info('Workflow step completed', [
        'step' => $event->step['id'],
        'result' => $event->result
    ]);
});

Event::listen(function (WorkflowCompleted $event) {
    Log::info('Workflow completed', [
        'conversation_id' => $event->conversation->id,
        'results' => $event->workflowData
    ]);
});

Event::listen(function (AgentMessageSent $event) {
    Log::info('Agent message sent', [
        'from' => $event->message->senderId,
        'to' => $event->targetAgent
    ]);
});
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
