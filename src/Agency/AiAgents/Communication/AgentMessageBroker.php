<?php

namespace App\AIAgents\Communication;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use App\Models\Conversation;
use App\Events\AgentMessageSent;

class AgentMessageBroker
{
    private array $registeredAgents = [];
    private array $messageQueues = [];

    public function registerAgent(string $agentId, array $capabilities): void
    {
        $this->registeredAgents[$agentId] = [
            'capabilities' => $capabilities,
            'status' => 'available',
            'last_seen' => now()
        ];
    }

    public function routeMessage(AgentMessage $message): void
    {
        $targetAgent = $this->findBestAgent($message);

        if ($targetAgent) {
            $this->queueMessage($message, $targetAgent);
            Event::dispatch(new AgentMessageSent($message, $targetAgent));
        } else {
            $this->handleNoAvailableAgent($message);
        }
    }

    private function findBestAgent(AgentMessage $message): ?string
    {
        $matchingAgents = collect($this->registeredAgents)
            ->filter(fn($agent) => $this->agentCanHandle($agent, $message))
            ->sortByDesc(fn($agent) => $this->calculateAgentScore($agent, $message));

        return $matchingAgents->keys()->first();
    }

    private function queueMessage(AgentMessage $message, string $targetAgent): void
    {
        $this->messageQueues[$targetAgent][] = $message;
        Cache::put(
            "agent_queue:{$targetAgent}",
            $this->messageQueues[$targetAgent],
            now()->addHours(1)
        );
    }

    private function agentCanHandle(array $agent, AgentMessage $message): bool
    {
        return collect($agent['capabilities'])
            ->intersect($message->getRequiredCapabilities())
            ->isNotEmpty();
    }
}

// Message class for agent communication
class AgentMessage
{
    public function __construct(
        private readonly string $senderId,
        private readonly string $content,
        private readonly array $metadata,
        private readonly array $requiredCapabilities,
        private readonly ?string $conversationId = null
    ) {}

    public function getRequiredCapabilities(): array
    {
        return $this->requiredCapabilities;
    }

    public function toArray(): array
    {
        return [
            'sender_id' => $this->senderId,
            'content' => $this->content,
            'metadata' => $this->metadata,
            'required_capabilities' => $this->requiredCapabilities,
            'conversation_id' => $this->conversationId,
            'timestamp' => now()->toIso8601String()
        ];
    }
}

// Facade for easy agent access
namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class AI extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'ai.manager';
    }

    public static function agent(string $type)
    {
        return static::$app->make('ai.manager')->getAgent($type);
    }

    public static function team(string $teamId)
    {
        return static::$app->make('ai.manager')->getTeam($teamId);
    }
}

// Service Provider for AI system
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\AIAgents\Communication\AgentMessageBroker;
use App\Services\AIManager;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('ai.manager', function ($app) {
            return new AIManager($app->make(AgentMessageBroker::class));
        });

        $this->app->singleton(AgentMessageBroker::class);
    }
}

// AI Manager Service
namespace App\Services;

use App\AIAgents\Communication\AgentMessageBroker;
use App\AIAgents\Communication\AgentMessage;

class AIManager
{
    private array $agents = [];
    private array $teams = [];

    public function __construct(
        private readonly AgentMessageBroker $messageBroker
    ) {}

    public function getAgent(string $type): ?object
    {
        if (!isset($this->agents[$type])) {
            $this->agents[$type] = $this->createAgent($type);
        }

        return $this->agents[$type];
    }

    public function getTeam(string $teamId): ?object
    {
        if (!isset($this->teams[$teamId])) {
            $this->teams[$teamId] = $this->loadTeam($teamId);
        }

        return $this->teams[$teamId];
    }

    private function createAgent(string $type): object
    {
        $config = config("ai.agents.{$type}");
        $class = $config['class'] ?? "App\\AIAgents\\{$type}Agent";

        $agent = app($class);
        $this->messageBroker->registerAgent($agent->getId(), $agent->getCapabilities());

        return $agent;
    }
}

// Example usage in controller
namespace App\Http\Controllers;

use App\Facades\AI;

class AIController extends Controller
{
    public function handleRequest(Request $request)
    {
        // Use facade to access agents
        $response = match ($request->type) {
            'code' => AI::agent('developer')->handleRequest($request->input),
            'design' => AI::agent('architect')->handleRequest($request->input),
            'team' => AI::team($request->team_id)->handleRequest($request->input),
            default => throw new InvalidArgumentException('Unknown request type')
        };

        return response()->json($response);
    }
}
