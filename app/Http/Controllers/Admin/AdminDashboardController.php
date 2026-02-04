<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Admin\SettingsService;
use App\Services\Admin\SystemMonitoringService;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __construct(
        private SystemMonitoringService $monitoringService,
        private SettingsService $settingsService
    ) {}

    /**
     * Display the admin dashboard.
     */
    public function index(): Response
    {
        ActivityLog::log('view', 'Viewed admin dashboard');

        $dashboardData = $this->monitoringService->getDashboardData();

        return Inertia::render('admin/Dashboard', [
            'serverHealth' => $dashboardData['server'],
            'databaseStats' => $dashboardData['database'],
            'queueStats' => $dashboardData['queue'],
            'cacheStats' => $dashboardData['cache'],
            'userStats' => $dashboardData['users'],
            'activityStats' => $dashboardData['activities'],
            'aiUsageStats' => $dashboardData['ai_usage'],
        ]);
    }

    /**
     * Get real-time monitoring data (for polling).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function monitoringData()
    {
        return response()->json([
            'server' => $this->monitoringService->getServerHealth(),
            'queue' => $this->monitoringService->getQueueStats(),
            'activities' => $this->monitoringService->getActivityStats(),
        ]);
    }
}
