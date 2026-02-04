<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Admin\SystemMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QueueMonitorController extends Controller
{
    public function __construct(
        private SystemMonitoringService $monitoringService
    ) {}

    /**
     * Display queue monitoring page.
     */
    public function index(): Response
    {
        $queueStats = $this->monitoringService->getQueueStats();

        return Inertia::render('admin/queues/Index', [
            'stats' => $queueStats,
        ]);
    }

    /**
     * Get real-time queue data.
     */
    public function data(): JsonResponse
    {
        return response()->json($this->monitoringService->getQueueStats());
    }

    /**
     * Retry a failed job.
     */
    public function retryJob(int $id): RedirectResponse
    {
        $job = DB::table('failed_jobs')->find($id);

        if (! $job) {
            return back()->with('error', 'Failed job not found.');
        }

        Artisan::call('queue:retry', ['id' => [$job->uuid]]);

        ActivityLog::log('retry', "Retried failed job: {$job->uuid}");

        return back()->with('success', 'Job has been queued for retry.');
    }

    /**
     * Retry all failed jobs.
     */
    public function retryAll(): RedirectResponse
    {
        $count = DB::table('failed_jobs')->count();

        if ($count === 0) {
            return back()->with('info', 'No failed jobs to retry.');
        }

        Artisan::call('queue:retry', ['id' => ['all']]);

        ActivityLog::log('retry', "Retried all {$count} failed jobs");

        return back()->with('success', "Queued {$count} failed jobs for retry.");
    }

    /**
     * Delete a failed job.
     */
    public function deleteJob(int $id): RedirectResponse
    {
        $job = DB::table('failed_jobs')->find($id);

        if (! $job) {
            return back()->with('error', 'Failed job not found.');
        }

        Artisan::call('queue:forget', ['id' => $job->uuid]);

        ActivityLog::log('delete', "Deleted failed job: {$job->uuid}");

        return back()->with('success', 'Failed job deleted.');
    }

    /**
     * Flush all failed jobs.
     */
    public function flushFailed(): RedirectResponse
    {
        $count = DB::table('failed_jobs')->count();

        Artisan::call('queue:flush');

        ActivityLog::log('flush', "Flushed all {$count} failed jobs");

        return back()->with('success', 'All failed jobs have been deleted.');
    }

    /**
     * Clear all pending jobs (be careful!).
     */
    public function clearPending(): RedirectResponse
    {
        $count = DB::table('jobs')->count();

        DB::table('jobs')->truncate();

        ActivityLog::log('clear', "Cleared all {$count} pending jobs");

        return back()->with('success', 'All pending jobs have been cleared.');
    }
}
