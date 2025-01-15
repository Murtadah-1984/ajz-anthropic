<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Http\Controllers;

use Ajz\Anthropic\Facades\AI;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Ajz\Anthropic\Events\AgentMessageReceived;
use Ajz\Anthropic\Events\InterAgentCommunication;

final class AgentCommunicationController extends Controller
{
    public function getActiveAgents()
    {
        $agents = AI::getActiveAgents();
        return response()->json($agents);
    }

    public function getAvailableSessions()
    {
        $sessions = [
            ['id' => 'general', 'name' => 'General Chat'],
            ['id' => 'code_review', 'name' => 'Code Review'],
            ['id' => 'architecture', 'name' => 'Architecture Design'],
            ['id' => 'security', 'name' => 'Security Review']
        ];
        return response()->json($sessions);
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
            'session_id' => 'required|string',
        ]);

        // Create a session if it doesn't exist
        $session = AI::getOrCreateSession($request->session_id);

        // Process the message through the front desk service
        $frontDesk = app(FrontDeskAIService::class);
        $response = $frontDesk->handleRequest(auth()->user(), $request->content);

        // Broadcast the message to all listeners
        broadcast(new AgentMessageReceived([
            'id' => uniqid(),
            'content' => $request->content,
            'sender' => 'user',
            'session_id' => $request->session_id,
            'timestamp' => now(),
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Message sent successfully'
        ]);
    }

    public function getSessionMessages(Request $request, $sessionId)
    {
        $messages = AI::getSessionMessages($sessionId);
        return response()->json($messages);
    }
}
