<?php

namespace App\Plugins\PomodoroTimer;

use App\Services\Plugin\BasePlugin;
use App\Services\TelegramService;
use App\Models\User;

class PomodoroTimerPlugin extends BasePlugin
{
    public function getName(): string
    {
        return 'Pomodoro Timer';
    }

    public function getSlug(): string
    {
        return 'pomodoro-timer';
    }

    public function getDescription(): string
    {
        return 'Tingkatkan produktivitas dengan teknik Pomodoro. 25 menit fokus, 5 menit istirahat. Track sesi dan capai target harian!';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getAuthor(): string
    {
        return 'ASPRI Team';
    }

    public function getIcon(): string
    {
        return 'clock';
    }

    public function getConfigSchema(): array
    {
        return [
            'work_duration' => [
                'type' => 'number',
                'label' => 'Durasi Kerja (menit)',
                'min' => 15,
                'max' => 60,
                'step' => 5,
                'default' => 25,
                'required' => true,
            ],
            'short_break' => [
                'type' => 'number',
                'label' => 'Istirahat Pendek (menit)',
                'min' => 3,
                'max' => 15,
                'step' => 1,
                'default' => 5,
                'required' => true,
            ],
            'long_break' => [
                'type' => 'number',
                'label' => 'Istirahat Panjang (menit)',
                'min' => 10,
                'max' => 60,
                'step' => 5,
                'default' => 15,
                'required' => true,
            ],
            'sessions_before_long_break' => [
                'type' => 'number',
                'label' => 'Sesi Sebelum Istirahat Panjang',
                'min' => 2,
                'max' => 8,
                'step' => 1,
                'default' => 4,
                'required' => true,
            ],
            'daily_goal' => [
                'type' => 'number',
                'label' => 'Target Pomodoro/Hari',
                'min' => 1,
                'max' => 20,
                'step' => 1,
                'default' => 8,
                'required' => true,
            ],
            'auto_start_break' => [
                'type' => 'boolean',
                'label' => 'Auto-start Istirahat',
                'default' => false,
            ],
            'auto_start_work' => [
                'type' => 'boolean',
                'label' => 'Auto-start Kerja Setelah Istirahat',
                'default' => false,
            ],
            'notification_sound' => [
                'type' => 'boolean',
                'label' => 'Notifikasi Suara',
                'default' => true,
            ],
            'daily_summary' => [
                'type' => 'boolean',
                'label' => 'Ringkasan Harian',
                'default' => true,
            ],
            'summary_time' => [
                'type' => 'time',
                'label' => 'Waktu Ringkasan',
                'default' => '21:00',
                'condition' => 'daily_summary === true',
            ],
            'motivational_quotes' => [
                'type' => 'boolean',
                'label' => 'Quotes Motivasi',
                'default' => true,
            ],
            'track_tasks' => [
                'type' => 'boolean',
                'label' => 'Lacak Task per Pomodoro',
                'default' => true,
            ],
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            'work_duration' => 25,
            'short_break' => 5,
            'long_break' => 15,
            'sessions_before_long_break' => 4,
            'daily_goal' => 8,
            'auto_start_break' => false,
            'auto_start_work' => false,
            'notification_sound' => true,
            'daily_summary' => true,
            'summary_time' => '21:00',
            'motivational_quotes' => true,
            'track_tasks' => true,
        ];
    }

    public function validateConfig(array $config): bool
    {
        if (!isset($config['work_duration']) || $config['work_duration'] < 15 || $config['work_duration'] > 60) {
            return false;
        }

        if (!isset($config['short_break']) || $config['short_break'] < 3 || $config['short_break'] > 15) {
            return false;
        }

        if (!isset($config['long_break']) || $config['long_break'] < 10 || $config['long_break'] > 60) {
            return false;
        }

        if (isset($config['summary_time']) && !preg_match('/^\d{2}:\d{2}$/', $config['summary_time'])) {
            return false;
        }

        return true;
    }

    public function activate(): void
    {
        $user = auth()->user();
        $config = $this->getConfig($user->id);

        // Daily summary
        if ($config['daily_summary']) {
            $this->createSchedule($user->id, [
                'schedule_type' => 'daily',
                'schedule_value' => $config['summary_time'],
                'metadata' => [
                    'type' => 'daily_summary',
                ],
            ]);
        }

        $this->log($user->id, 'info', 'Pomodoro Timer activated with daily goal: ' . $config['daily_goal']);
    }

    public function deactivate(): void
    {
        $user = auth()->user();
        $this->deleteSchedules($user->id);
        $this->log($user->id, 'info', 'Pomodoro Timer deactivated');
    }

    public function execute(int $userId, array $metadata): void
    {
        try {
            $type = $metadata['type'] ?? 'daily_summary';

            if ($type === 'daily_summary') {
                $this->sendDailySummary($userId);
            }

            $this->log($userId, 'info', "Executed: {$type}");
        } catch (\Exception $e) {
            $this->log($userId, 'error', 'Execution failed: ' . $e->getMessage());
        }
    }

    private function sendDailySummary(int $userId): void
    {
        $config = $this->getConfig($userId);
        $telegramService = app(TelegramService::class);

        $dailyGoal = $config['daily_goal'];
        $workDuration = $config['work_duration'];
        $totalWorkMinutes = $dailyGoal * $workDuration;
        $totalHours = floor($totalWorkMinutes / 60);
        $totalMinutes = $totalWorkMinutes % 60;

        $motivationalQuotes = [
            "🎯 \"Focus is the gateway to productivity.\"",
            "⏱️ \"Work expands to fill the time available.\" - Parkinson's Law",
            "🚀 \"The secret of getting ahead is getting started.\" - Mark Twain",
            "💪 \"Discipline is choosing between what you want now and what you want most.\"",
            "🎨 \"Quality is not an act, it is a habit.\" - Aristotle",
        ];

        $message = "⏱️ *Ringkasan Pomodoro Hari Ini*\n\n";
        
        // This would need actual tracking data
        $message .= "🎯 Target: {$dailyGoal} pomodoro\n";
        $message .= "⏳ Total waktu fokus target: {$totalHours}j {$totalMinutes}m\n\n";
        
        $message .= "📊 *Konfigurasi Anda*:\n";
        $message .= "• Kerja: {$config['work_duration']} menit\n";
        $message .= "• Istirahat pendek: {$config['short_break']} menit\n";
        $message .= "• Istirahat panjang: {$config['long_break']} menit\n";
        $message .= "• Long break setiap: {$config['sessions_before_long_break']} sesi\n\n";

        if ($config['motivational_quotes']) {
            $quote = $motivationalQuotes[array_rand($motivationalQuotes)];
            $message .= $quote . "\n\n";
        }

        $message .= "💡 Siap untuk produktif besok? Mulai sesi Pomodoro dengan ketik 'start pomodoro'!";

        $telegramService->sendMessage($userId, $message);
    }
}
