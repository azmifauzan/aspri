<?php

namespace App\Plugins\BookTracker;

use App\Services\Plugin\BasePlugin;
use App\Services\TelegramService;
use App\Models\User;

class BookTrackerPlugin extends BasePlugin
{
    public function getName(): string
    {
        return 'Book Tracker';
    }

    public function getSlug(): string
    {
        return 'book-tracker';
    }

    public function getDescription(): string
    {
        return 'Lacak buku yang Anda baca, simpan kutipan favorit, dan capai target membaca tahunan Anda.';
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
        return 'book-open';
    }

    public function getConfigSchema(): array
    {
        return [
            'yearly_goal' => [
                'type' => 'number',
                'label' => 'Target Buku/Tahun',
                'min' => 1,
                'max' => 365,
                'step' => 1,
                'default' => 12,
                'required' => true,
            ],
            'reading_reminder' => [
                'type' => 'boolean',
                'label' => 'Pengingat Membaca Harian',
                'default' => true,
            ],
            'reminder_time' => [
                'type' => 'time',
                'label' => 'Waktu Pengingat',
                'default' => '20:00',
                'condition' => 'reading_reminder === true',
            ],
            'reminder_days' => [
                'type' => 'multiselect',
                'label' => 'Hari Pengingat',
                'options' => [
                    'monday' => 'Senin',
                    'tuesday' => 'Selasa',
                    'wednesday' => 'Rabu',
                    'thursday' => 'Kamis',
                    'friday' => 'Jumat',
                    'saturday' => 'Sabtu',
                    'sunday' => 'Minggu',
                ],
                'default' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                'condition' => 'reading_reminder === true',
            ],
            'monthly_summary' => [
                'type' => 'boolean',
                'label' => 'Ringkasan Bulanan',
                'default' => true,
            ],
            'track_genres' => [
                'type' => 'boolean',
                'label' => 'Lacak Genre',
                'default' => true,
            ],
            'save_quotes' => [
                'type' => 'boolean',
                'label' => 'Simpan Kutipan Favorit',
                'default' => true,
            ],
            'share_progress' => [
                'type' => 'boolean',
                'label' => 'Bagikan Progress',
                'default' => false,
            ],
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            'yearly_goal' => 12,
            'reading_reminder' => true,
            'reminder_time' => '20:00',
            'reminder_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'monthly_summary' => true,
            'track_genres' => true,
            'save_quotes' => true,
            'share_progress' => false,
        ];
    }

    public function validateConfig(array $config): bool
    {
        if (!isset($config['yearly_goal']) || $config['yearly_goal'] < 1 || $config['yearly_goal'] > 365) {
            return false;
        }

        if (isset($config['reminder_time']) && !preg_match('/^\d{2}:\d{2}$/', $config['reminder_time'])) {
            return false;
        }

        return true;
    }

    public function activate(): void
    {
        $user = auth()->user();
        $config = $this->getConfig($user->id);

        // Daily reading reminder
        if ($config['reading_reminder']) {
            $this->createSchedule($user->id, [
                'schedule_type' => 'daily',
                'schedule_value' => $config['reminder_time'],
                'metadata' => [
                    'type' => 'daily_reminder',
                    'days' => $config['reminder_days'],
                ],
            ]);
        }

        // Monthly summary (first day of month)
        if ($config['monthly_summary']) {
            $this->createSchedule($user->id, [
                'schedule_type' => 'cron',
                'schedule_value' => '0 9 1 * *', // 1st day of month at 9 AM
                'metadata' => [
                    'type' => 'monthly_summary',
                ],
            ]);
        }

        $this->log($user->id, 'info', 'Book Tracker activated with yearly goal: ' . $config['yearly_goal']);
    }

    public function deactivate(): void
    {
        $user = auth()->user();
        $this->deleteSchedules($user->id);
        $this->log($user->id, 'info', 'Book Tracker deactivated');
    }

    public function execute(int $userId, array $metadata): void
    {
        try {
            $type = $metadata['type'] ?? 'daily_reminder';

            if ($type === 'daily_reminder') {
                // Check if today is in reminder_days
                $days = $metadata['days'] ?? [];
                $today = strtolower(now()->format('l'));
                
                if (in_array($today, $days)) {
                    $this->sendDailyReminder($userId);
                }
            } elseif ($type === 'monthly_summary') {
                $this->sendMonthlySummary($userId);
            }

            $this->log($userId, 'info', "Executed: {$type}");
        } catch (\Exception $e) {
            $this->log($userId, 'error', 'Execution failed: ' . $e->getMessage());
        }
    }

    private function sendDailyReminder(int $userId): void
    {
        $config = $this->getConfig($userId);
        $telegramService = app(TelegramService::class);

        $motivationalQuotes = [
            "📚 \"Membaca adalah jendela dunia.\"",
            "📖 \"Sebuah rumah tanpa buku seperti ruangan tanpa jendela.\" - Heinrich Mann",
            "📚 \"Buku adalah teman terbaik yang tidak pernah mengecewakan.\"",
            "📖 \"Membaca adalah untuk pikiran seperti olahraga untuk tubuh.\" - Joseph Addison",
            "📚 \"Hari ini pembaca, besok pemimpin.\" - Margaret Fuller",
        ];

        $quote = $motivationalQuotes[array_rand($motivationalQuotes)];

        $message = "📚 *Pengingat Membaca Harian*\n\n";
        $message .= $quote . "\n\n";
        $message .= "Target tahun ini: *{$config['yearly_goal']} buku*\n\n";
        $message .= "Luangkan waktu 20-30 menit untuk membaca hari ini! 📖\n\n";
        $message .= "Sedang baca apa sekarang?";

        $telegramService->sendMessage($userId, $message);
    }

    private function sendMonthlySummary(int $userId): void
    {
        $config = $this->getConfig($userId);
        $telegramService = app(TelegramService::class);

        $currentMonth = now()->subMonth()->format('F Y');
        $yearlyGoal = $config['yearly_goal'];
        $monthlyTarget = round($yearlyGoal / 12, 1);

        $message = "📊 *Ringkasan Membaca Bulan {$currentMonth}*\n\n";
        $message .= "🎯 Target tahunan: {$yearlyGoal} buku\n";
        $message .= "📅 Target bulanan: ~{$monthlyTarget} buku\n\n";
        
        // This would need actual tracking data
        $message .= "📚 Terus lanjutkan kebiasaan membaca Anda!\n\n";
        $message .= "💡 Tips: Bawa buku kemana pun Anda pergi, manfaatkan waktu tunggu untuk membaca beberapa halaman.\n\n";
        $message .= "Buku apa yang akan Anda baca bulan ini?";

        $telegramService->sendMessage($userId, $message);
    }
}
