<?php

namespace Ajz\Anthropic\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Carbon\Carbon;
use Ajz\Anthropic\Services\MaintenanceWindowService;
use Ajz\Anthropic\Http\Requests\MaintenanceWindow\CreateRequest;
use Ajz\Anthropic\Http\Requests\MaintenanceWindow\UpdateRequest;

class MaintenanceWindowController extends Controller
{
    private MaintenanceWindowService $service;

    public function __construct(MaintenanceWindowService $service)
    {
        $this->service = $service;
        $this->middleware('auth');
        $this->middleware('can:manage-maintenance-windows');
    }

    /**
     * Display maintenance window dashboard
     */
    public function index(): View
    {
        $windows = $this->service->getActiveWindows();
        $environments = $this->service->getAvailableEnvironments();

        return view('anthropic::maintenance.index', [
            'windows' => $windows,
            'environments' => $environments,
            'stats' => [
                'active' => $windows->where('status', 'active')->count(),
                'pending' => $windows->where('status', 'pending')->count(),
                'expired' => $windows->where('status', 'expired')->count()
            ]
        ]);
    }

    /**
     * Create new maintenance window
     */
    public function store(CreateRequest $request): JsonResponse
    {
        try {
            $window = $this->service->createWindow(
                start: Carbon::parse($request->input('start_time')),
                duration: (int) $request->input('duration'),
                environment: $request->input('environment'),
                comment: $request->input('comment')
            );

            return response()->json([
                'message' => 'Maintenance window created successfully',
                'window' => $window
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create maintenance window',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update existing maintenance window
     */
    public function update(UpdateRequest $request, string $id): JsonResponse
    {
        try {
            $window = $this->service->updateWindow(
                id: $id,
                start: $request->has('start_time') ? Carbon::parse($request->input('start_time')) : null,
                duration: $request->has('duration') ? (int) $request->input('duration') : null,
                comment: $request->input('comment')
            );

            return response()->json([
                'message' => 'Maintenance window updated successfully',
                'window' => $window
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update maintenance window',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete maintenance window
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->service->deleteWindow($id);

            return response()->json([
                'message' => 'Maintenance window deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete maintenance window',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active maintenance windows
     */
    public function active(Request $request): JsonResponse
    {
        $windows = $this->service->getActiveWindows(
            environment: $request->query('environment')
        );

        return response()->json([
            'windows' => $windows
        ]);
    }

    /**
     * Get maintenance window details
     */
    public function show(string $id): JsonResponse
    {
        try {
            $window = $this->service->getWindow($id);

            return response()->json([
                'window' => $window
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch maintenance window',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get maintenance window metrics
     */
    public function metrics(): JsonResponse
    {
        $metrics = $this->service->getMetrics();

        return response()->json([
            'metrics' => $metrics
        ]);
    }

    /**
     * Extend maintenance window duration
     */
    public function extend(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'duration' => 'required|integer|min:1|max:72'
        ]);

        try {
            $window = $this->service->extendWindow(
                id: $id,
                additionalHours: (int) $request->input('duration')
            );

            return response()->json([
                'message' => 'Maintenance window extended successfully',
                'window' => $window
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to extend maintenance window',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * End maintenance window early
     */
    public function end(string $id): JsonResponse
    {
        try {
            $window = $this->service->endWindow($id);

            return response()->json([
                'message' => 'Maintenance window ended successfully',
                'window' => $window
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to end maintenance window',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get maintenance window history
     */
    public function history(Request $request): JsonResponse
    {
        $history = $this->service->getHistory(
            environment: $request->query('environment'),
            startDate: $request->query('start_date') ? Carbon::parse($request->query('start_date')) : null,
            endDate: $request->query('end_date') ? Carbon::parse($request->query('end_date')) : null,
            limit: (int) $request->query('limit', 50)
        );

        return response()->json([
            'history' => $history
        ]);
    }

    /**
     * Get upcoming maintenance windows
     */
    public function upcoming(Request $request): JsonResponse
    {
        $windows = $this->service->getUpcomingWindows(
            environment: $request->query('environment'),
            days: (int) $request->query('days', 7)
        );

        return response()->json([
            'windows' => $windows
        ]);
    }

    /**
     * Validate maintenance window timing
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'start_time' => 'required|date',
            'duration' => 'required|integer|min:1|max:72',
            'environment' => 'required|string'
        ]);

        $conflicts = $this->service->validateTiming(
            start: Carbon::parse($request->input('start_time')),
            duration: (int) $request->input('duration'),
            environment: $request->input('environment')
        );

        return response()->json([
            'conflicts' => $conflicts
        ]);
    }
}
