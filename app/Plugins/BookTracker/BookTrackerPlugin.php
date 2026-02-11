<?php

namespace App\Plugins\BookTracker;

use App\Services\Plugin\BasePlugin;

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

    public function validateConfig(array $config): array
    {
        $errors = [];

        if (! isset($config['yearly_goal']) || $config['yearly_goal'] < 1 || $config['yearly_goal'] > 365) {
            $errors['yearly_goal'] = 'Target buku per tahun harus antara 1 dan 365';
        }

        if (isset($config['reminder_time']) && ! preg_match('/^\d{2}:\d{2}$/', $config['reminder_time'])) {
            $errors['reminder_time'] = 'Format waktu tidak valid (harus HH:MM)';
        }

        return $errors;
    }

    public function activate(int $userId): void
    {
        $config = $this->getUserConfig($userId);

        // Daily reading reminder
        if ($config['reading_reminder']) {
            $this->createSchedule($userId, [
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
            $this->createSchedule($userId, [
                'schedule_type' => 'cron',
                'schedule_value' => '0 9 1 * *', // 1st day of month at 9 AM
                'metadata' => [
                    'type' => 'monthly_summary',
                ],
            ]);
        }

        $this->log($userId, 'info', 'Book Tracker activated with yearly goal: '.$config['yearly_goal']);
    }

    public function deactivate(int $userId): void
    {
        $this->deleteSchedules($userId);
        $this->log($userId, 'info', 'Book Tracker deactivated');
    }

    public function execute(int $userId, array $config, array $context = []): void
    {
        try {
            $type = $context['type'] ?? 'daily_reminder';

            if ($type === 'daily_reminder') {
                // Check if today is in reminder_days
                $days = $context['days'] ?? [];
                $today = strtolower(now()->format('l'));

                if (in_array($today, $days)) {
                    $message = $this->buildDailyReminderMessage($userId);
                    $this->sendTelegramMessage($userId, $message);
                }
            } elseif ($type === 'monthly_summary') {
                $message = $this->buildMonthlySummaryMessage($userId);
                $this->sendTelegramMessage($userId, $message);
            }

            $this->log($userId, 'info', "Executed: {$type}");
        } catch (\Exception $e) {
            $this->log($userId, 'error', 'Execution failed: '.$e->getMessage());
        }
    }

    public function supportsScheduling(): bool
    {
        return true;
    }

    public function getDefaultSchedule(): ?array
    {
        return [
            'type' => 'daily',
            'value' => '20:00',
        ];
    }

    public function supportsChatIntegration(): bool
    {
        return true;
    }

    public function getChatIntents(): array
    {
        $slugPrefix = str_replace('-', '_', $this->getSlug());

        return [
            [
                'action' => "plugin_{$slugPrefix}_reminder",
                'description' => 'Pengingat membaca harian dan target buku',
                'entities' => [
                    'time' => 'string|null',
                ],
                'examples' => [
                    'ingatkan saya membaca hari ini',
                    'pengingat baca buku',
                    'remind me to read today',
                ],
            ],
            [
                'action' => "plugin_{$slugPrefix}_monthly_summary",
                'description' => 'Ringkasan membaca bulanan',
                'entities' => [
                    'month' => 'string|null',
                ],
                'examples' => [
                    'ringkasan membaca bulan ini',
                    'monthly reading summary',
                ],
            ],
        ];
    }

    public function handleChatIntent(int $userId, string $action, array $entities): array
    {
        $slugPrefix = str_replace('-', '_', $this->getSlug());

        return match ($action) {
            "plugin_{$slugPrefix}_reminder" => [
                'success' => true,
                'message' => $this->buildDailyReminderMessage($userId),
            ],
            "plugin_{$slugPrefix}_monthly_summary" => [
                'success' => true,
                'message' => $this->buildMonthlySummaryMessage($userId),
            ],
            default => [
                'success' => false,
                'message' => 'Action not supported',
            ],
        };
    }

    private function buildDailyReminderMessage(int $userId): string
    {
        $config = $this->getUserConfig($userId);

        $motivationalQuotes = [
            '📚 "Membaca adalah jendela dunia."',
            '📖 "Sebuah rumah tanpa buku seperti ruangan tanpa jendela." - Heinrich Mann',
            '📚 "Buku adalah teman terbaik yang tidak pernah mengecewakan."',
            '📖 "Membaca adalah untuk pikiran seperti olahraga untuk tubuh." - Joseph Addison',
            '📚 "Hari ini pembaca, besok pemimpin." - Margaret Fuller',
        ];

        $quote = $motivationalQuotes[array_rand($motivationalQuotes)];

        $message = "📚 *Pengingat Membaca Harian*\n\n";
        $message .= $quote."\n\n";
        $message .= "Target tahun ini: *{$config['yearly_goal']} buku*\n\n";
        $message .= "Luangkan waktu 20-30 menit untuk membaca hari ini! 📖\n\n";
        $message .= 'Sedang baca apa sekarang?';

        return $message;
    }

    private function buildMonthlySummaryMessage(int $userId): string
    {
        $config = $this->getUserConfig($userId);

        $currentMonth = now()->subMonth()->format('F Y');
        $yearlyGoal = $config['yearly_goal'];
        $monthlyTarget = round($yearlyGoal / 12, 1);

        $message = "📊 *Ringkasan Membaca Bulan {$currentMonth}*\n\n";
        $message .= "🎯 Target tahunan: {$yearlyGoal} buku\n";
        $message .= "📅 Target bulanan: ~{$monthlyTarget} buku\n\n";

        // This would need actual tracking data
        $message .= "📚 Terus lanjutkan kebiasaan membaca Anda!\n\n";
        $message .= "💡 Tips: Bawa buku kemana pun Anda pergi, manfaatkan waktu tunggu untuk membaca beberapa halaman.\n\n";
        $message .= 'Buku apa yang akan Anda baca bulan ini?';

        return $message;
    }
}
