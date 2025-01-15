<?php

namespace Ajz\Anthropic\AIAgents\Sessions;

use Ajz\Anthropic\AIAgents\Communication\AgentMessageBroker;
use App\Models\User;

abstract class BaseSession
{
    protected string $sessionId;
    protected string $status = 'preparing';
    protected array $participants = [];
    protected array $artifacts = [];
    protected array $timeline = [];

    public function __construct(
        protected readonly AgentMessageBroker $broker,
        protected readonly array $configuration = []
    ) {
        $this->sessionId = uniqid('session_');
    }

    abstract public function start(): void;
    abstract protected function processStep(string $step): void;
}
