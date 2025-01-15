<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AgentStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $agent;
    public $status;

    public function __construct($agent, $status)
    {
        $this->agent = $agent;
        $this->status = $status;
    }

    public function broadcastOn()
    {
        return new Channel('agent-status');
    }
}
