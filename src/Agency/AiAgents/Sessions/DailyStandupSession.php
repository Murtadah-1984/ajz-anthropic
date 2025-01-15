<?php

namespace Ajz\Anthropic\AIAgents\Sessions;

class DailyStandupSession extends BaseSession
{
    public function start(): void
    {
        $this->status = 'standup';
        $this->conductStandup();
    }

    private function conductStandup(): void
    {
        // Each Team Member (AI) reports:
        // - Yesterday's work
        // - Today's plan
        // - Blockers
    }
}
