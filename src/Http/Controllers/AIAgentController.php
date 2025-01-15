<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Http\Controllers;

use Ajz\Anthropic\Facades\AI;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;


final class AIAgentController extends Controller
{
    public function index()
    {
        $agents = AI::getAllAgents();
        return response()->json($agents);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'type' => 'required|string',
            'capabilities' => 'required|array',
        ]);

        $agent = AI::createAgent($validated);
        return response()->json($agent);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'string',
            'status' => 'string',
            'capabilities' => 'array',
        ]);

        $agent = AI::updateAgent($id, $validated);
        return response()->json($agent);
    }

    public function destroy($id)
    {
        AI::deleteAgent($id);
        return response()->json(['status' => 'success']);
    }
}
