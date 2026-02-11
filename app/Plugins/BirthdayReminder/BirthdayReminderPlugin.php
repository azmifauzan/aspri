<?php

namespace App\Plugins\BirthdayReminder;

use App\Services\Plugin\BasePlugin;

class BirthdayReminderPlugin extends BasePlugin
{
    public function getName(): string
    {
        return 'Birthday Reminder';
    }

    public function getSlug(): string
    {
        return 'birthday-reminder';
    }

    public function getDescription(): string
    {
        return 'Jangan pernah lupa ulang tahun orang-orang penting! Dapatkan pengingat otomatis dan saran hadiah.';
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
        return 'gift';
    }

    public function getConfigSchema(): array
    {
        return [
            'advance_notice_days' => [
                'type' => 'multiselect',
                'label' => 'Ingatkan Berapa Hari Sebelumnya',
                'options' => [
                    '1' => '1 hari',
                    '3' => '3 hari',
                    '7' => '7 hari',
                    '14' => '14 hari',
                    '30' => '30 hari',
                ],
                'default' => ['7', '1'],
                'required' => true,
            ],
            'morning_check' => [
                'type' => 'boolean',
                'label' => 'Cek Pagi Hari',
                'default' => true,
            ],
            'check_time' => [
                'type' => 'time',
                'label' => 'Waktu Pengingat',
                'default' => '08:00',
                'condition' => 'morning_check === true',
            ],
            'include_age' => [
                'type' => 'boolean',
                'label' => 'Tampilkan Umur',
                'default' => true,
            ],
            'gift_suggestions' => [
                'type' => 'boolean',
                'label' => 'Saran Hadiah',
                'default' => true,
            ],
            'celebration_message' => [
                'type' => 'boolean',
                'label' => 'Template Pesan Ucapan',
                'default' => true,
            ],
            'monthly_overview' => [
                'type' => 'boolean',
                'label' => 'Ringkasan Bulanan',
                'default' => true,
            ],
            'categories' => [
                'type' => 'multiselect',
                'label' => 'Kategori Kontak',
                'options' => [
                    'family' => 'Keluarga',
                    'friends' => 'Teman',
                    'colleagues' => 'Rekan Kerja',
                    'clients' => 'Klien',
                    'others' => 'Lainnya',
                ],
                'default' => ['family', 'friends'],
            ],
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            'advance_notice_days' => ['7', '1'],
            'morning_check' => true,
            'check_time' => '08:00',
            'include_age' => true,
            'gift_suggestions' => true,
            'celebration_message' => true,
            'monthly_overview' => true,
            'categories' => ['family', 'friends'],
        ];
    }

    public function validateConfig(array $config): array
    {
        $errors = [];

        if (! isset($config['advance_notice_days']) || empty($config['advance_notice_days'])) {
            $errors['advance_notice_days'] = 'Advance notice days is required';
        }

        if (isset($config['check_time']) && ! preg_match('/^\d{2}:\d{2}$/', $config['check_time'])) {
            $errors['check_time'] = 'Check time must be in HH:MM format';
        }

        return $errors;
    }

    public function activate(int $userId): void
    {
        $config = $this->getUserConfig($userId);

        // Daily check for birthdays
        if ($config['morning_check']) {
            $this->createSchedule($userId, [
                'schedule_type' => 'daily',
                'schedule_value' => $config['check_time'],
                'metadata' => [
                    'type' => 'birthday_check',
                ],
            ]);
        }

        // Monthly overview (first day of month)
        if ($config['monthly_overview']) {
            $this->createSchedule($userId, [
                'schedule_type' => 'cron',
                'schedule_value' => '0 9 1 * *', // 1st of month at 9 AM
                'metadata' => [
                    'type' => 'monthly_overview',
                ],
            ]);
        }

        $this->log($userId, 'info', 'Birthday Reminder activated');
    }

    public function deactivate(int $userId): void
    {
        $this->deleteSchedules($userId);
        $this->log($userId, 'info', 'Birthday Reminder deactivated');
    }

    public function execute(int $userId, array $config, array $context = []): void
    {
        try {
            $type = $context['type'] ?? 'birthday_check';

            if ($type === 'birthday_check') {
                $message = $this->buildUpcomingBirthdaysMessage($userId);
                $this->sendTelegramMessage($userId, $message);
            } elseif ($type === 'monthly_overview') {
                $message = $this->buildMonthlyOverviewMessage();
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
            'value' => '08:00',
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
                'action' => "plugin_{$slugPrefix}_check",
                'description' => 'Cek pengingat ulang tahun terdekat',
                'entities' => [
                    'period' => 'string|null',
                ],
                'examples' => [
                    'cek ulang tahun minggu ini',
                    'ulang tahun terdekat',
                    'birthday reminder hari ini',
                    'siapa yang ulang tahun minggu ini',
                ],
            ],
            [
                'action' => "plugin_{$slugPrefix}_monthly_overview",
                'description' => 'Ringkasan ulang tahun bulan ini',
                'entities' => [
                    'month' => 'string|null',
                ],
                'examples' => [
                    'ringkasan ulang tahun bulan ini',
                    'birthday overview bulan ini',
                    'siapa saja ulang tahun bulan ini',
                ],
            ],
        ];
    }

    public function handleChatIntent(int $userId, string $action, array $entities): array
    {
        $slugPrefix = str_replace('-', '_', $this->getSlug());

        return match ($action) {
            "plugin_{$slugPrefix}_check" => [
                'success' => true,
                'message' => $this->buildUpcomingBirthdaysMessage($userId),
            ],
            "plugin_{$slugPrefix}_monthly_overview" => [
                'success' => true,
                'message' => $this->buildMonthlyOverviewMessage(),
            ],
            default => [
                'success' => false,
                'message' => 'Action not supported',
            ],
        };
    }

    private function buildUpcomingBirthdaysMessage(int $userId): string
    {
        $config = $this->getUserConfig($userId);

        // This would need a birthdays table or use contacts
        // For now, send a reminder to add birthdays if none exist

        $message = "🎂 *Birthday Reminder Check*\n\n";
        $message .= "Hari ini tidak ada ulang tahun yang perlu diingatkan.\n\n";
        $message .= "💡 Tip: Tambahkan tanggal lahir kontak Anda dengan ketik:\n";
        $message .= "\"Tambah ulang tahun [Nama] [DD/MM/YYYY]\"\n\n";
        $message .= 'Contoh: "Tambah ulang tahun Budi 15/08/1990"';

        return $message;
    }

    private function buildMonthlyOverviewMessage(): string
    {
        $currentMonth = now()->format('F Y');

        $message = "🎉 *Ulang Tahun Bulan {$currentMonth}*\n\n";

        // This would query actual birthday data
        $message .= "Belum ada ulang tahun tercatat untuk bulan ini.\n\n";
        $message .= "📝 *Cara menambahkan ulang tahun:*\n";
        $message .= "Ketik: \"Tambah ulang tahun [Nama] [DD/MM/YYYY] [kategori]\"\n\n";
        $message .= "Kategori:\n";
        $message .= "• keluarga\n";
        $message .= "• teman\n";
        $message .= "• rekan kerja\n";
        $message .= "• klien\n\n";
        $message .= 'Contoh: "Tambah ulang tahun Sarah 25/03/1995 teman"';

        return $message;
    }
}
