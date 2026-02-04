<?php

namespace App\Services\Admin;

use App\Models\ActivityLog;
use App\Models\ChatMessage;
use App\Models\FinanceTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class SystemMonitoringService
{
    /**
     * Get server health metrics.
     *
     * @return array<string, mixed>
     */
    public function getServerHealth(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'memory_usage' => $this->formatBytes(memory_get_usage(true)),
            'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'memory_limit' => ini_get('memory_limit'),
            'disk_free' => $this->formatBytes(disk_free_space('/')),
            'disk_total' => $this->formatBytes(disk_total_space('/')),
            'uptime' => $this->getUptime(),
            'load_average' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
        ];
    }

    /**
     * Get database statistics.
     *
     * @return array<string, mixed>
     */
    public function getDatabaseStats(): array
    {
        $connection = config('database.default');
        $tables = [];

        if ($connection === 'pgsql') {
            $tables = DB::select('
                SELECT 
                    relname as table_name,
                    n_live_tup as row_count
                FROM pg_stat_user_tables
                ORDER BY n_live_tup DESC
                LIMIT 10
            ');
        } elseif ($connection === 'mysql') {
            $database = config('database.connections.mysql.database');
            $tables = DB::select('
                SELECT 
                    table_name,
                    table_rows as row_count
                FROM information_schema.tables
                WHERE table_schema = ?
                ORDER BY table_rows DESC
                LIMIT 10
            ', [$database]);
        }

        return [
            'connection' => $connection,
            'tables' => collect($tables)->map(fn ($t) => [
                'name' => $t->table_name,
                'rows' => (int) $t->row_count,
            ])->toArray(),
            'total_users' => User::count(),
            'total_messages' => ChatMessage::count(),
            'total_transactions' => FinanceTransaction::count(),
        ];
    }

    /**
     * Get queue statistics.
     *
     * @return array<string, mixed>
     */
    public function getQueueStats(): array
    {
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        $jobsByQueue = DB::table('jobs')
            ->select('queue', DB::raw('count(*) as count'))
            ->groupBy('queue')
            ->get()
            ->mapWithKeys(fn ($j) => [$j->queue => $j->count])
            ->toArray();

        $recentFailedJobs = DB::table('failed_jobs')
            ->select('id', 'queue', 'failed_at', 'exception')
            ->orderByDesc('failed_at')
            ->limit(5)
            ->get()
            ->map(fn ($job) => [
                'id' => $job->id,
                'queue' => $job->queue,
                'failed_at' => $job->failed_at,
                'exception' => \Illuminate\Support\Str::limit($job->exception, 200),
            ])
            ->toArray();

        $batches = DB::table('job_batches')
            ->select('id', 'name', 'total_jobs', 'pending_jobs', 'failed_jobs', 'created_at', 'finished_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->toArray();

        return [
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
            'jobs_by_queue' => $jobsByQueue,
            'recent_failed_jobs' => $recentFailedJobs,
            'recent_batches' => $batches,
        ];
    }

    /**
     * Get cache statistics.
     *
     * @return array<string, mixed>
     */
    public function getCacheStats(): array
    {
        $driver = config('cache.default');

        return [
            'driver' => $driver,
            'prefix' => config('cache.prefix'),
        ];
    }

    /**
     * Get user statistics.
     *
     * @return array<string, mixed>
     */
    public function getUserStats(): array
    {
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $inactiveUsers = User::where('is_active', false)->count();

        $usersByRole = User::query()
            ->select('role', DB::raw('count(*) as count'))
            ->groupBy('role')
            ->get()
            ->mapWithKeys(fn ($u) => [$u->role => $u->count])
            ->toArray();

        $recentUsers = User::query()
            ->with('profile:id,user_id,aspri_name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'name', 'email', 'role', 'is_active', 'created_at'])
            ->toArray();

        $usersToday = User::whereDate('created_at', today())->count();
        $usersThisWeek = User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $usersThisMonth = User::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();

        return [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'inactive' => $inactiveUsers,
            'by_role' => $usersByRole,
            'recent' => $recentUsers,
            'today' => $usersToday,
            'this_week' => $usersThisWeek,
            'this_month' => $usersThisMonth,
        ];
    }

    /**
     * Get activity log statistics.
     *
     * @return array<string, mixed>
     */
    public function getActivityStats(): array
    {
        $recentActivities = ActivityLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'user' => $log->user?->name ?? 'System',
                'action' => $log->action,
                'description' => $log->description,
                'model_type' => class_basename($log->model_type ?? ''),
                'model_id' => $log->model_id,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at->toIso8601String(),
            ])
            ->toArray();

        $activityByAction = ActivityLog::query()
            ->select('action', DB::raw('count(*) as count'))
            ->whereDate('created_at', '>=', now()->subDays(7))
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->mapWithKeys(fn ($a) => [$a->action => $a->count])
            ->toArray();

        $activityByDay = ActivityLog::query()
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->whereDate('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn ($a) => [$a->date => $a->count])
            ->toArray();

        return [
            'recent' => $recentActivities,
            'by_action' => $activityByAction,
            'by_day' => $activityByDay,
            'total_today' => ActivityLog::whereDate('created_at', today())->count(),
            'total_week' => ActivityLog::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
        ];
    }

    /**
     * Get AI usage statistics.
     *
     * @return array<string, mixed>
     */
    public function getAiUsageStats(): array
    {
        $messagesPerDay = ChatMessage::query()
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->whereDate('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn ($m) => [$m->date => $m->count])
            ->toArray();

        $messagesByRole = ChatMessage::query()
            ->select('role', DB::raw('count(*) as count'))
            ->groupBy('role')
            ->get()
            ->mapWithKeys(fn ($m) => [$m->role => $m->count])
            ->toArray();

        $topUsers = ChatMessage::query()
            ->select('user_id', DB::raw('count(*) as message_count'))
            ->with('user:id,name')
            ->groupBy('user_id')
            ->orderByDesc('message_count')
            ->limit(5)
            ->get()
            ->map(fn ($m) => [
                'user' => $m->user?->name ?? 'Unknown',
                'count' => $m->message_count,
            ])
            ->toArray();

        return [
            'total_messages' => ChatMessage::count(),
            'messages_today' => ChatMessage::whereDate('created_at', today())->count(),
            'messages_this_week' => ChatMessage::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'messages_per_day' => $messagesPerDay,
            'messages_by_role' => $messagesByRole,
            'top_users' => $topUsers,
        ];
    }

    /**
     * Get full monitoring dashboard data.
     *
     * @return array<string, mixed>
     */
    public function getDashboardData(): array
    {
        return [
            'server' => $this->getServerHealth(),
            'database' => $this->getDatabaseStats(),
            'queue' => $this->getQueueStats(),
            'cache' => $this->getCacheStats(),
            'users' => $this->getUserStats(),
            'activities' => $this->getActivityStats(),
            'ai_usage' => $this->getAiUsageStats(),
        ];
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision).' '.$units[$pow];
    }

    /**
     * Get server uptime.
     */
    private function getUptime(): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return null;
        }

        $uptime = @file_get_contents('/proc/uptime');
        if ($uptime === false) {
            return null;
        }

        $seconds = (int) explode(' ', $uptime)[0];
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return "{$days}d {$hours}h {$minutes}m";
    }
}
