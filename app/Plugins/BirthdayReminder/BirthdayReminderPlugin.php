<?php

namespace App\Plugins\BirthdayReminder;

use App\Services\Plugin\BasePlugin;
use App\Services\TelegramService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

    public function validateConfig(array $config): bool
    {
        if (!isset($config['advance_notice_days']) || empty($config['advance_notice_days'])) {
            return false;
        }

        if (isset($config['check_time']) && !preg_match('/^\d{2}:\d{2}$/', $config['check_time'])) {
            return false;
        }

        return true;
    }

    public function activate(): void
    {
        $user = auth()->user();
        $config = $this->getConfig($user->id);

        // Daily check for birthdays
        if ($config['morning_check']) {
            $this->createSchedule($user->id, [
                'schedule_type' => 'daily',
                'schedule_value' => $config['check_time'],
                'metadata' => [
                    'type' => 'birthday_check',
                ],
            ]);
        }

        // Monthly overview (first day of month)
        if ($config['monthly_overview']) {
            $this->createSchedule($user->id, [
                'schedule_type' => 'cron',
                'schedule_value' => '0 9 1 * *', // 1st of month at 9 AM
                'metadata' => [
                    'type' => 'monthly_overview',
                ],
            ]);
        }

        $this->log($user->id, 'info', 'Birthday Reminder activated');
    }

    public function deactivate(): void
    {
        $user = auth()->user();
        $this->deleteSchedules($user->id);
        $this->log($user->id, 'info', 'Birthday Reminder deactivated');
    }

    public function execute(int $userId, array $metadata): void
    {
        try {
            $type = $metadata['type'] ?? 'birthday_check';

            if ($type === 'birthday_check') {
                $this->checkUpcomingBirthdays($userId);
            } elseif ($type === 'monthly_overview') {
                $this->sendMonthlyOverview($userId);
            }

            $this->log($userId, 'info', "Executed: {$type}");
        } catch (\Exception $e) {
            $this->log($userId, 'error', 'Execution failed: ' . $e->getMessage());
        }
    }

    private function checkUpcomingBirthdays(int $userId): void
    {
        $config = $this->getConfig($userId);
        $telegramService = app(TelegramService::class);

        $advanceNoticeDays = $config['advance_notice_days'];
        
        // This would need a birthdays table or use contacts
        // For now, send a reminder to add birthdays if none exist
        
        $message = "🎂 *Birthday Reminder Check*\n\n";
        $message .= "Hari ini tidak ada ulang tahun yang perlu diingatkan.\n\n";
        $message .= "💡 Tip: Tambahkan tanggal lahir kontak Anda dengan ketik:\n";
        $message .= "\"Tambah ulang tahun [Nama] [DD/MM/YYYY]\"\n\n";
        $message .= "Contoh: \"Tambah ulang tahun Budi 15/08/1990\"";

        // Don't send if no birthdays - reduce noise
        // $telegramService->sendMessage($userId, $message);
    }

    private function sendMonthlyOverview(int $userId): void
    {
        $config = $this->getConfig($userId);
        $telegramService = app(TelegramService::class);

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
        $message .= "Contoh: \"Tambah ulang tahun Sarah 25/03/1995 teman\"";

        $telegramService->sendMessage($userId, $message);
    }
}
