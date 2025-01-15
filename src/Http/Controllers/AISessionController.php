<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Http\Controllers;

use Ajz\Anthropic\Facades\AI;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class AISessionController extends Controller
{
    public function index()
    {
        $sessions = AI::getAllSessions();
        return response()->json($sessions);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'options' => 'required|array',
        ]);

        $session = AI::startSession($validated['type'], $validated['options']);
        return response()->json($session);
    }

    public function show($id)
    {
        $session = AI::getSession($id);
        return response()->json($session);
    }

    public function destroy($id)
    {
        AI::endSession($id);
        return response()->json(['status' => 'success']);
    }
}
