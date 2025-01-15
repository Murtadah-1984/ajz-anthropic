<?php

use Ajz\Anthropic\Http\Controllers\{AIDashboardController,AIAgentController,AISessionController,AgentCommunicationController};

Route::middleware(['auth'])->group(function () {
    Route::get('/ai-dashboard', [AIDashboardController::class, 'index'])->name('ai-dashboard');
});

// routes/api.php
Route::middleware(['auth:sanctum'])->prefix('ai')->group(function () {
    // Dashboard Data
    Route::get('/stats', [AIDashboardController::class, 'getSystemStats']);
    Route::get('/activities', [AIDashboardController::class, 'getRecentActivities']);
    Route::get('/metrics', [AIDashboardController::class, 'getAgentMetrics']);

    // Agents
    Route::apiResource('agents', AIAgentController::class);
    Route::get('/active-agents', [AIDashboardController::class, 'getActiveAgents']);

    // Sessions
    Route::apiResource('sessions', AISessionController::class);
    Route::get('/active-sessions', [AIDashboardController::class, 'getActiveSessions']);

    // Agent Communication
    Route::post('/messages', [AgentCommunicationController::class, 'sendMessage']);
    Route::get('/sessions/{sessionId}/messages', [AgentCommunicationController::class, 'getSessionMessages']);
});
