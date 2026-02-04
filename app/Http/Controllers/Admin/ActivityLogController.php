<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of activity logs.
     */
    public function index(Request $request): Response
    {
        $query = ActivityLog::query()->with('user:id,name,email');

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%");
                    });
            });
        }

        // Filter by action
        if ($action = $request->get('action')) {
            $query->where('action', $action);
        }

        // Filter by date range
        if ($startDate = $request->get('start_date')) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate = $request->get('end_date')) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $logs = $query
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        // Get unique actions for filter dropdown
        $actions = ActivityLog::distinct()->pluck('action')->sort()->values();

        return Inertia::render('admin/activity/Index', [
            'logs' => $logs,
            'actions' => $actions,
            'filters' => $request->only(['search', 'action', 'start_date', 'end_date']),
        ]);
    }

    /**
     * Display a specific activity log.
     */
    public function show(ActivityLog $activityLog): Response
    {
        $activityLog->load('user:id,name,email');

        return Inertia::render('admin/activity/Show', [
            'log' => $activityLog,
        ]);
    }
}
