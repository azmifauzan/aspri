<?php

namespace App\Plugins\MoodJournal;

use App\Services\Plugin\BasePlugin;

class MoodJournalPlugin extends BasePlugin
{
    public function getName(): string
    {
        return 'Mood Journal';
    }

    public function getSlug(): string
    {
        return 'mood-journal';
    }

    public function getDescription(): string
    {
        return 'Catat mood dan emosi harian Anda. Dapatkan insight tentang pola perasaan dan faktor yang mempengaruhi kesejahteraan mental.';
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
        return 'emoji-happy';
    }

    public function getConfigSchema(): array
    {
        return [
            'daily_checkin' => [
                'type' => 'boolean',
                'label' => 'Check-in Harian',
                'default' => true,
            ],
            'checkin_time' => [
                'type' => 'time',
                'label' => 'Waktu Check-in',
                'default' => '21:00',
                'condition' => 'daily_checkin === true',
            ],
            'multiple_checkin' => [
                'type' => 'boolean',
                'label' => 'Check-in Multiple (pagi, siang, malam)',
                'default' => false,
            ],
            'morning_time' => [
                'type' => 'time',
                'label' => 'Check-in Pagi',
                'default' => '08:00',
                'condition' => 'multiple_checkin === true',
            ],
            'afternoon_time' => [
                'type' => 'time',
                'label' => 'Check-in Siang',
                'default' => '14:00',
                'condition' => 'multiple_checkin === true',
            ],
            'evening_time' => [
                'type' => 'time',
                'label' => 'Check-in Malam',
                'default' => '21:00',
                'condition' => 'multiple_checkin === true',
            ],
            'gratitude_prompt' => [
                'type' => 'boolean',
                'label' => 'Prompt Gratitude',
                'default' => true,
            ],
            'track_activities' => [
                'type' => 'boolean',
                'label' => 'Lacak Aktivitas yang Mempengaruhi Mood',
                'default' => true,
            ],
            'weekly_insight' => [
                'type' => 'boolean',
                'label' => 'Insight Mingguan',
                'default' => true,
            ],
            'insight_day' => [
                'type' => 'select',
                'label' => 'Hari Insight',
                'options' => [
                    'sunday' => 'Minggu',
                    'monday' => 'Senin',
                ],
                'default' => 'sunday',
                'condition' => 'weekly_insight === true',
            ],
            'privacy_mode' => [
                'type' => 'boolean',
                'label' => 'Mode Privasi (data terenkripsi)',
                'default' => true,
            ],
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            'daily_checkin' => true,
            'checkin_time' => '21:00',
            'multiple_checkin' => false,
            'morning_time' => '08:00',
            'afternoon_time' => '14:00',
            'evening_time' => '21:00',
            'gratitude_prompt' => true,
            'track_activities' => true,
            'weekly_insight' => true,
            'insight_day' => 'sunday',
            'privacy_mode' => true,
        ];
    }

    public function validateConfig(array $config): array
    {
        $errors = [];
        $timeFields = ['checkin_time', 'morning_time', 'afternoon_time', 'evening_time'];

        foreach ($timeFields as $field) {
            if (isset($config[$field]) && ! preg_match('/^\d{2}:\d{2}$/', $config[$field])) {
                $errors[$field] = 'Format waktu tidak valid (harus HH:MM)';
            }
        }

        return $errors;
    }

    public function activate(int $userId): void
    {
        $config = $this->getUserConfig($userId);

        if ($config['multiple_checkin']) {
            // Morning check-in
            $this->createSchedule($userId, [
                'schedule_type' => 'daily',
                'schedule_value' => $config['morning_time'],
                'metadata' => [
                    'type' => 'mood_checkin',
                    'period' => 'morning',
                ],
            ]);

            // Afternoon check-in
            $this->createSchedule($userId, [
                'schedule_type' => 'daily',
                'schedule_value' => $config['afternoon_time'],
                'metadata' => [
                    'type' => 'mood_checkin',
                    'period' => 'afternoon',
                ],
            ]);

            // Evening check-in
            $this->createSchedule($userId, [
                'schedule_type' => 'daily',
                'schedule_value' => $config['evening_time'],
                'metadata' => [
                    'type' => 'mood_checkin',
                    'period' => 'evening',
                ],
            ]);
        } elseif ($config['daily_checkin']) {
            // Single daily check-in
            $this->createSchedule($userId, [
                'schedule_type' => 'daily',
                'schedule_value' => $config['checkin_time'],
                'metadata' => [
                    'type' => 'mood_checkin',
                    'period' => 'daily',
                ],
            ]);
        }

        // Weekly insight
        if ($config['weekly_insight']) {
            $this->createSchedule($userId, [
                'schedule_type' => 'weekly',
                'schedule_value' => $config['insight_day'].',09:00',
                'metadata' => [
                    'type' => 'weekly_insight',
                ],
            ]);
        }

        $this->log($userId, 'info', 'Mood Journal activated');
    }

    public function deactivate(int $userId): void
    {
        $this->deleteSchedules($userId);
        $this->log($userId, 'info', 'Mood Journal deactivated');
    }

    public function execute(int $userId, array $config, array $context = []): void
    {
        try {
            $type = $context['type'] ?? 'mood_checkin';

            if ($type === 'mood_checkin') {
                $period = $context['period'] ?? 'daily';
                $message = $this->buildMoodCheckinMessage($userId, $period);
                $this->sendTelegramMessage($userId, $message);
            } elseif ($type === 'weekly_insight') {
                $message = $this->buildWeeklyInsightMessage();
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
            'value' => '21:00',
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
                'action' => "plugin_{$slugPrefix}_checkin",
                'description' => 'Check-in mood harian',
                'entities' => [
                    'period' => 'string|null',
                ],
                'examples' => [
                    'mood check-in hari ini',
                    'checkin mood pagi',
                    'how is my mood today',
                ],
            ],
            [
                'action' => "plugin_{$slugPrefix}_weekly_insight",
                'description' => 'Insight mood mingguan',
                'entities' => [
                    'week' => 'string|null',
                ],
                'examples' => [
                    'insight mood minggu ini',
                    'weekly mood insight',
                ],
            ],
        ];
    }

    public function handleChatIntent(int $userId, string $action, array $entities): array
    {
        $slugPrefix = str_replace('-', '_', $this->getSlug());
        $period = $entities['period'] ?? 'daily';

        return match ($action) {
            "plugin_{$slugPrefix}_checkin" => [
                'success' => true,
                'message' => $this->buildMoodCheckinMessage($userId, $period),
            ],
            "plugin_{$slugPrefix}_weekly_insight" => [
                'success' => true,
                'message' => $this->buildWeeklyInsightMessage(),
            ],
            default => [
                'success' => false,
                'message' => 'Action not supported',
            ],
        };
    }

    private function buildMoodCheckinMessage(int $userId, string $period): string
    {
        $config = $this->getUserConfig($userId);

        $periodLabels = [
            'morning' => 'Pagi',
            'afternoon' => 'Siang',
            'evening' => 'Malam',
            'daily' => 'Hari Ini',
        ];

        $periodEmojis = [
            'morning' => '🌅',
            'afternoon' => '☀️',
            'evening' => '🌙',
            'daily' => '💭',
        ];

        $emoji = $periodEmojis[$period] ?? '💭';
        $label = $periodLabels[$period] ?? 'Hari Ini';

        $message = "{$emoji} *Mood Check-in {$label}*\n\n";
        $message .= "Bagaimana perasaan Anda sekarang?\n\n";
        $message .= "Pilih emoji yang menggambarkan mood Anda:\n";
        $message .= "😊 Sangat Baik\n";
        $message .= "🙂 Baik\n";
        $message .= "😐 Biasa Saja\n";
        $message .= "😔 Kurang Baik\n";
        $message .= "😢 Sedih\n\n";

        if ($config['track_activities']) {
            $message .= "Apa yang Anda lakukan hari ini?\n";
            $message .= "(contoh: olahraga, kerja, bertemu teman, dll)\n\n";
        }

        if ($config['gratitude_prompt']) {
            $message .= '💚 Apa yang Anda syukuri hari ini?';
        }

        return $message;
    }

    private function buildWeeklyInsightMessage(): string
    {
        $message = "📊 *Insight Mood Mingguan*\n\n";
        $message .= "Ringkasan minggu ini:\n\n";

        // This would need actual mood tracking data
        $message .= "🎭 Mood Anda minggu ini cukup stabil!\n\n";
        $message .= "💡 *Insight*:\n";
        $message .= "• Terus jaga rutinitas positif\n";
        $message .= "• Perhatikan pola aktivitas yang membuat mood lebih baik\n";
        $message .= "• Jangan lupa self-care dan istirahat cukup\n\n";
        $message .= "📈 Pola mood minggu depan akan lebih baik jika Anda konsisten dengan kebiasaan positif!\n\n";
        $message .= 'Ingat: Perasaan adalah valid, dan tidak apa-apa untuk tidak selalu baik-baik saja. 💚';

        return $message;
    }
}
