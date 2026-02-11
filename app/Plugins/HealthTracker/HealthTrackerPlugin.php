<?php

namespace App\Plugins\HealthTracker;

use App\Models\User;
use App\Services\Plugin\BasePlugin;

class HealthTrackerPlugin extends BasePlugin
{
    public function getName(): string
    {
        return 'Health Tracker';
    }

    public function getSlug(): string
    {
        return 'health-tracker';
    }

    public function getDescription(): string
    {
        return 'Lacak kesehatan harian Anda: berat badan, langkah kaki, air minum, dan tidur. Dapatkan pengingat dan insight kesehatan.';
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
        return 'heart';
    }

    public function getConfigSchema(): array
    {
        return [
            'track_weight' => [
                'type' => 'boolean',
                'label' => 'Lacak Berat Badan',
                'default' => true,
            ],
            'track_steps' => [
                'type' => 'boolean',
                'label' => 'Lacak Langkah Kaki',
                'default' => true,
            ],
            'steps_goal' => [
                'type' => 'number',
                'label' => 'Target Langkah/Hari',
                'min' => 1000,
                'max' => 50000,
                'step' => 1000,
                'default' => 10000,
                'condition' => 'track_steps === true',
            ],
            'track_water' => [
                'type' => 'boolean',
                'label' => 'Lacak Air Minum',
                'default' => true,
            ],
            'water_goal' => [
                'type' => 'number',
                'label' => 'Target Air/Hari (gelas)',
                'min' => 4,
                'max' => 16,
                'step' => 1,
                'default' => 8,
                'condition' => 'track_water === true',
            ],
            'track_sleep' => [
                'type' => 'boolean',
                'label' => 'Lacak Jam Tidur',
                'default' => true,
            ],
            'sleep_goal' => [
                'type' => 'number',
                'label' => 'Target Tidur/Hari (jam)',
                'min' => 4,
                'max' => 12,
                'step' => 0.5,
                'default' => 8,
                'condition' => 'track_sleep === true',
            ],
            'reminder_time' => [
                'type' => 'time',
                'label' => 'Waktu Pengingat Harian',
                'default' => '20:00',
                'required' => true,
            ],
            'weekly_report' => [
                'type' => 'boolean',
                'label' => 'Laporan Mingguan',
                'default' => true,
            ],
            'weekly_report_day' => [
                'type' => 'select',
                'label' => 'Hari Laporan Mingguan',
                'options' => [
                    'monday' => 'Senin',
                    'tuesday' => 'Selasa',
                    'wednesday' => 'Rabu',
                    'thursday' => 'Kamis',
                    'friday' => 'Jumat',
                    'saturday' => 'Sabtu',
                    'sunday' => 'Minggu',
                ],
                'default' => 'monday',
                'condition' => 'weekly_report === true',
            ],
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            'track_weight' => true,
            'track_steps' => true,
            'steps_goal' => 10000,
            'track_water' => true,
            'water_goal' => 8,
            'track_sleep' => true,
            'sleep_goal' => 8,
            'reminder_time' => '20:00',
            'weekly_report' => true,
            'weekly_report_day' => 'monday',
        ];
    }

    public function validateConfig(array $config): array
    {
        $errors = [];

        if (! isset($config['reminder_time']) || ! preg_match('/^\d{2}:\d{2}$/', $config['reminder_time'])) {
            $errors['reminder_time'] = 'Format waktu tidak valid (harus HH:MM)';
        }

        if (isset($config['steps_goal']) && ($config['steps_goal'] < 1000 || $config['steps_goal'] > 50000)) {
            $errors['steps_goal'] = 'Target langkah harus antara 1000 dan 50000';
        }

        if (isset($config['water_goal']) && ($config['water_goal'] < 4 || $config['water_goal'] > 16)) {
            $errors['water_goal'] = 'Target air harus antara 4 dan 16 gelas';
        }

        if (isset($config['sleep_goal']) && ($config['sleep_goal'] < 4 || $config['sleep_goal'] > 12)) {
            $errors['sleep_goal'] = 'Target tidur harus antara 4 dan 12 jam';
        }

        return $errors;
    }

    public function activate(int $userId): void
    {
        $config = $this->getUserConfig($userId);

        // Daily reminder
        $this->createSchedule($userId, [
            'schedule_type' => 'daily',
            'schedule_value' => $config['reminder_time'],
            'metadata' => [
                'type' => 'daily_reminder',
            ],
        ]);

        // Weekly report
        if ($config['weekly_report']) {
            $this->createSchedule($userId, [
                'schedule_type' => 'weekly',
                'schedule_value' => $config['weekly_report_day'].',09:00',
                'metadata' => [
                    'type' => 'weekly_report',
                ],
            ]);
        }

        $this->log($userId, 'info', 'Health Tracker activated');
    }

    public function deactivate(int $userId): void
    {
        $this->deleteSchedules($userId);
        $this->log($userId, 'info', 'Health Tracker deactivated');
    }

    public function execute(int $userId, array $config, array $context = []): void
    {
        try {
            $type = $context['type'] ?? 'daily_reminder';

            if ($type === 'daily_reminder') {
                $message = $this->buildDailyReminderMessage($userId);
                $this->sendTelegramMessage($userId, $message);
            } elseif ($type === 'weekly_report') {
                $message = $this->buildWeeklyReportMessage($userId);
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
                'description' => 'Pengingat kesehatan harian',
                'entities' => [
                    'time' => 'string|null',
                ],
                'examples' => [
                    'ingatkan kesehatan harian',
                    'pengingat catat kesehatan',
                    'remind me to log my health',
                ],
            ],
            [
                'action' => "plugin_{$slugPrefix}_weekly_report",
                'description' => 'Laporan kesehatan mingguan',
                'entities' => [
                    'week' => 'string|null',
                ],
                'examples' => [
                    'laporan kesehatan minggu ini',
                    'weekly health report',
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
            "plugin_{$slugPrefix}_weekly_report" => [
                'success' => true,
                'message' => $this->buildWeeklyReportMessage($userId),
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

        $message = "🏥 *Pengingat Kesehatan Harian*\n\n";
        $message .= "Jangan lupa catat kesehatan Anda hari ini:\n\n";

        if ($config['track_weight']) {
            $message .= "⚖️ Berat badan\n";
        }
        if ($config['track_steps']) {
            $message .= '👣 Langkah kaki (Target: '.number_format($config['steps_goal']).")\n";
        }
        if ($config['track_water']) {
            $message .= "💧 Air minum (Target: {$config['water_goal']} gelas)\n";
        }
        if ($config['track_sleep']) {
            $message .= "😴 Jam tidur (Target: {$config['sleep_goal']} jam)\n";
        }

        $message .= "\nKetik data kesehatan Anda ke ASPRI untuk dicatat!";

        return $message;
    }

    private function buildWeeklyReportMessage(int $userId): string
    {
        $config = $this->getUserConfig($userId);

        $user = User::find($userId);
        $startDate = now()->subDays(7);
        $endDate = now();

        // Get health data from plugin_configurations or custom table
        $message = "📊 *Laporan Kesehatan Mingguan*\n";
        $message .= 'Periode: '.$startDate->format('d M').' - '.$endDate->format('d M Y')."\n\n";

        // This would need a separate health_logs table to track actual data
        // For now, send encouragement message
        $message .= "🎯 Tetap jaga kesehatan Anda!\n\n";

        if ($config['track_steps']) {
            $message .= '👣 Target langkah: '.number_format($config['steps_goal'])." langkah/hari\n";
        }
        if ($config['track_water']) {
            $message .= "💧 Target air: {$config['water_goal']} gelas/hari\n";
        }
        if ($config['track_sleep']) {
            $message .= "😴 Target tidur: {$config['sleep_goal']} jam/hari\n";
        }

        $message .= "\nTerus semangat mencapai target kesehatan Anda! 💪";

        return $message;
    }
}
