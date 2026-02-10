<?php

namespace App\Plugins\HabitTracker;

use App\Services\Plugin\BasePlugin;
use App\Services\TelegramService;

class HabitTrackerPlugin extends BasePlugin
{
    public function getName(): string
    {
        return 'Habit Tracker';
    }

    public function getSlug(): string
    {
        return 'habit-tracker';
    }

    public function getDescription(): string
    {
        return 'Bangun kebiasaan baik dengan sistem streak dan pengingat harian. Track progress Anda dan capai target!';
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
        return 'check-circle';
    }

    public function getConfigSchema(): array
    {
        return [
            'habits' => [
                'type' => 'textarea',
                'label' => 'Daftar Kebiasaan (satu per baris)',
                'placeholder' => "Olahraga pagi\nBaca buku 30 menit\nMeditasi\nJournal",
                'rows' => 6,
                'default' => '',
                'required' => true,
            ],
            'reminder_enabled' => [
                'type' => 'boolean',
                'label' => 'Aktifkan Pengingat',
                'default' => true,
            ],
            'reminder_time' => [
                'type' => 'time',
                'label' => 'Waktu Pengingat',
                'default' => '20:00',
                'condition' => 'reminder_enabled === true',
            ],
            'reminder_message' => [
                'type' => 'text',
                'label' => 'Pesan Pengingat',
                'default' => 'Jangan lupa check-in kebiasaan harian Anda! 💪',
                'condition' => 'reminder_enabled === true',
            ],
            'celebrate_streaks' => [
                'type' => 'boolean',
                'label' => 'Rayakan Streak Milestone',
                'default' => true,
            ],
            'streak_milestones' => [
                'type' => 'text',
                'label' => 'Milestone (pisahkan dengan koma)',
                'default' => '7,14,30,60,90,180,365',
                'placeholder' => '7,14,30,60,90,180,365',
                'condition' => 'celebrate_streaks === true',
            ],
            'weekly_review' => [
                'type' => 'boolean',
                'label' => 'Review Mingguan',
                'default' => true,
            ],
            'weekly_review_day' => [
                'type' => 'select',
                'label' => 'Hari Review',
                'options' => [
                    'monday' => 'Senin',
                    'sunday' => 'Minggu',
                ],
                'default' => 'sunday',
                'condition' => 'weekly_review === true',
            ],
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            'habits' => '',
            'reminder_enabled' => true,
            'reminder_time' => '20:00',
            'reminder_message' => 'Jangan lupa check-in kebiasaan harian Anda! 💪',
            'celebrate_streaks' => true,
            'streak_milestones' => '7,14,30,60,90,180,365',
            'weekly_review' => true,
            'weekly_review_day' => 'sunday',
        ];
    }

    public function validateConfig(array $config): array
    {
        $errors = [];

        if (! isset($config['habits']) || empty(trim($config['habits']))) {
            $errors['habits'] = 'Daftar kebiasaan tidak boleh kosong';
        }

        if (isset($config['reminder_time']) && ! preg_match('/^\d{2}:\d{2}$/', $config['reminder_time'])) {
            $errors['reminder_time'] = 'Format waktu tidak valid (harus HH:MM)';
        }

        return $errors;
    }

    public function activate(int $userId): void
    {
        $config = $this->getUserConfig($userId);

        // Daily reminder
        if ($config['reminder_enabled']) {
            $this->createSchedule($userId, [
                'schedule_type' => 'daily',
                'schedule_value' => $config['reminder_time'],
                'metadata' => [
                    'type' => 'daily_reminder',
                ],
            ]);
        }

        // Weekly review
        if ($config['weekly_review']) {
            $this->createSchedule($userId, [
                'schedule_type' => 'weekly',
                'schedule_value' => $config['weekly_review_day'].',09:00',
                'metadata' => [
                    'type' => 'weekly_review',
                ],
            ]);
        }

        $this->log($userId, 'info', 'Habit Tracker activated with '.count(explode("\n", trim($config['habits']))).' habits');
    }

    public function deactivate(int $userId): void
    {
        $this->deleteSchedules($userId);
        $this->log($userId, 'info', 'Habit Tracker deactivated');
    }

    public function execute(int $userId, array $config, array $context = []): void
    {
        try {
            $type = $context['type'] ?? 'daily_reminder';

            if ($type === 'daily_reminder') {
                $this->sendDailyReminder($userId);
            } elseif ($type === 'weekly_review') {
                $this->sendWeeklyReview($userId);
            }

            $this->log($userId, 'info', "Executed: {$type}");
        } catch (\Exception $e) {
            $this->log($userId, 'error', 'Execution failed: '.$e->getMessage());
        }
    }

    private function sendDailyReminder(int $userId): void
    {
        $config = $this->getUserConfig($userId);
        $telegramService = app(TelegramService::class);

        $habits = array_filter(array_map('trim', explode("\n", $config['habits'])));

        $message = "✅ *Check-in Kebiasaan Harian*\n\n";
        $message .= $config['reminder_message']."\n\n";
        $message .= "📋 Kebiasaan hari ini:\n\n";

        foreach ($habits as $index => $habit) {
            $message .= ($index + 1).'. '.$habit."\n";
        }

        $message .= "\nKetik kebiasaan yang sudah Anda lakukan ke ASPRI!";

        $telegramService->sendMessage($userId, $message);
    }

    private function sendWeeklyReview(int $userId): void
    {
        $config = $this->getUserConfig($userId);
        $telegramService = app(TelegramService::class);

        $habits = array_filter(array_map('trim', explode("\n", $config['habits'])));

        $message = "📊 *Review Kebiasaan Mingguan*\n\n";
        $message .= 'Minggu ini Anda melacak '.count($habits)." kebiasaan:\n\n";

        foreach ($habits as $index => $habit) {
            $message .= ($index + 1).'. '.$habit."\n";
        }

        $message .= "\n💪 Terus pertahankan konsistensi Anda!\n";
        $message .= "🔥 Setiap hari yang Anda check-in adalah kemenangan!\n\n";
        $message .= 'Tip: Kebiasaan terbentuk dari konsistensi kecil setiap hari.';

        $telegramService->sendMessage($userId, $message);
    }
}
