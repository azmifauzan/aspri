<?php

namespace App\Plugins\PrayerTimes;

use App\Services\Plugin\BasePlugin;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class PrayerTimesPlugin extends BasePlugin
{
    private const API_URL = 'https://api.aladhan.com/v1/timings';

    private const CALENDAR_API = 'https://api.aladhan.com/v1/calendar';

    public function getName(): string
    {
        return 'Prayer Times';
    }

    public function getSlug(): string
    {
        return 'prayer-times';
    }

    public function getDescription(): string
    {
        return 'Jadwal waktu solat akurat berdasarkan lokasi Anda. Dengan pengingat otomatis dan notifikasi adzan.';
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
        return 'mosque';
    }

    public function getConfigSchema(): array
    {
        return [
            'location' => [
                'type' => 'text',
                'label' => 'Lokasi',
                'placeholder' => 'Jakarta, Indonesia',
                'required' => true,
            ],
            'latitude' => [
                'type' => 'number',
                'label' => 'Latitude',
                'default' => -6.2088,
                'step' => 0.0001,
                'required' => true,
            ],
            'longitude' => [
                'type' => 'number',
                'label' => 'Longitude',
                'default' => 106.8456,
                'step' => 0.0001,
                'required' => true,
            ],
            'calculation_method' => [
                'type' => 'select',
                'label' => 'Metode Perhitungan',
                'options' => [
                    '3' => 'Muslim World League',
                    '2' => 'Islamic Society of North America',
                    '5' => 'Egyptian General Authority',
                    '4' => 'Umm Al-Qura University, Makkah',
                    '1' => 'University of Islamic Sciences, Karachi',
                    '7' => 'Institute of Geophysics, University of Tehran',
                    '0' => 'Shia Ithna-Ashari, Leva Institute, Qum',
                    '20' => 'Kementerian Agama Indonesia',
                ],
                'default' => '20',
                'required' => true,
            ],
            'reminder_enabled' => [
                'type' => 'boolean',
                'label' => 'Aktifkan Pengingat',
                'default' => true,
            ],
            'reminder_minutes' => [
                'type' => 'select',
                'label' => 'Ingatkan Sebelum',
                'options' => [
                    '5' => '5 menit',
                    '10' => '10 menit',
                    '15' => '15 menit',
                    '30' => '30 menit',
                ],
                'default' => '10',
                'condition' => 'reminder_enabled === true',
            ],
            'adhan_notification' => [
                'type' => 'boolean',
                'label' => 'Notifikasi Saat Adzan',
                'default' => true,
            ],
            'daily_schedule' => [
                'type' => 'boolean',
                'label' => 'Jadwal Harian Pagi',
                'default' => true,
            ],
            'schedule_time' => [
                'type' => 'time',
                'label' => 'Waktu Pengiriman Jadwal',
                'default' => '04:30',
                'condition' => 'daily_schedule === true',
            ],
            'prayers_to_remind' => [
                'type' => 'multiselect',
                'label' => 'Waktu Solat yang Diingatkan',
                'options' => [
                    'Fajr' => 'Subuh',
                    'Dhuhr' => 'Dzuhur',
                    'Asr' => 'Ashar',
                    'Maghrib' => 'Maghrib',
                    'Isha' => 'Isya',
                ],
                'default' => ['Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'],
            ],
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            'location' => 'Jakarta, Indonesia',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'calculation_method' => '20',
            'reminder_enabled' => true,
            'reminder_minutes' => '10',
            'adhan_notification' => true,
            'daily_schedule' => true,
            'schedule_time' => '04:30',
            'prayers_to_remind' => ['Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'],
        ];
    }

    public function validateConfig(array $config): array
    {
        $errors = [];

        if (! isset($config['latitude']) || $config['latitude'] < -90 || $config['latitude'] > 90) {
            $errors['latitude'] = 'Latitude must be between -90 and 90';
        }

        if (! isset($config['longitude']) || $config['longitude'] < -180 || $config['longitude'] > 180) {
            $errors['longitude'] = 'Longitude must be between -180 and 180';
        }

        if (isset($config['schedule_time']) && ! preg_match('/^\d{2}:\d{2}$/', $config['schedule_time'])) {
            $errors['schedule_time'] = 'Schedule time must be in HH:MM format';
        }

        return $errors;
    }

    public function activate(int $userId): void
    {
        $config = $this->getUserConfig($userId);

        // Schedule daily prayer times
        if ($config['daily_schedule']) {
            $this->createSchedule($userId, [
                'schedule_type' => 'daily',
                'schedule_value' => $config['schedule_time'],
                'metadata' => [
                    'type' => 'daily_schedule',
                ],
            ]);
        }

        $this->log($userId, 'info', 'Prayer Times activated');
    }

    public function deactivate(int $userId): void
    {
        $this->deleteSchedules($userId);
        $this->log($userId, 'info', 'Prayer Times deactivated');
    }

    public function execute(int $userId, array $config, array $context = []): void
    {
        try {
            $type = $context['type'] ?? 'daily_schedule';

            if ($type === 'daily_schedule') {
                $this->sendDailySchedule($userId);
            } elseif ($type === 'check_times') {
                $this->checkPrayerTimes($userId);
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
            'value' => '04:30',
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
                'description' => 'Cek jadwal solat untuk tanggal tertentu',
                'entities' => [
                    'date' => 'string|null',
                ],
                'examples' => [
                    'jadwal solat hari ini',
                    'cek jadwal solat besok',
                    'prayer times today',
                ],
            ],
        ];
    }

    public function handleChatIntent(int $userId, string $action, array $entities): array
    {
        $slugPrefix = str_replace('-', '_', $this->getSlug());

        if ($action !== "plugin_{$slugPrefix}_check") {
            return [
                'success' => false,
                'message' => 'Action not supported',
            ];
        }

        $date = $entities['date'] ?? null;
        $result = $this->checkPrayerTimes($userId, $date);

        if ($result['success']) {
            $timings = $result['timings'];

            $now = Carbon::now();
            $nextPrayer = null;
            $nextTime = null;

            foreach (['Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'] as $prayer) {
                $prayerTime = Carbon::createFromFormat('H:i', $timings[$prayer]);
                if ($prayerTime->greaterThan($now)) {
                    $nextPrayer = $prayer;
                    $nextTime = $timings[$prayer];
                    break;
                }
            }

            $prayerNames = [
                'Fajr' => 'Subuh',
                'Dhuhr' => 'Dzuhur',
                'Asr' => 'Ashar',
                'Maghrib' => 'Maghrib',
                'Isha' => 'Isya',
            ];

            $response = "🕌 *Jadwal Solat*\n\n";
            $response .= "📍 {$result['location']}\n";
            $response .= "📅 {$result['date']}\n\n";
            $response .= "🌅 Subuh: {$timings['Fajr']}\n";
            $response .= "☀️ Dzuhur: {$timings['Dhuhr']}\n";
            $response .= "🌤️ Ashar: {$timings['Asr']}\n";
            $response .= "🌆 Maghrib: {$timings['Maghrib']}\n";
            $response .= "🌙 Isya: {$timings['Isha']}\n";

            if ($nextPrayer && $nextTime) {
                $response .= "\n⏰ Solat berikutnya: {$prayerNames[$nextPrayer]} ({$nextTime})\n";
            }

            return [
                'success' => true,
                'message' => $response,
                'data' => $result,
            ];
        }

        return [
            'success' => false,
            'message' => '❌ Maaf, gagal mengambil jadwal solat. Coba lagi nanti.',
        ];
    }

    public function checkPrayerTimes(int $userId, ?string $date = null): array
    {
        $config = $this->getUserConfig($userId);

        $latitude = $config['latitude'];
        $longitude = $config['longitude'];
        $method = $config['calculation_method'];
        $targetDate = $date ?? now()->format('d-m-Y');

        try {
            $response = Http::timeout(10)->get(self::API_URL.'/'.$targetDate, [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'method' => $method,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $timings = $data['data']['timings'];

                $this->log($userId, 'info', 'Prayer times fetched successfully');

                return [
                    'success' => true,
                    'location' => $config['location'],
                    'date' => $data['data']['date']['readable'] ?? $targetDate,
                    'timings' => [
                        'Fajr' => $this->cleanTime($timings['Fajr']),
                        'Sunrise' => $this->cleanTime($timings['Sunrise']),
                        'Dhuhr' => $this->cleanTime($timings['Dhuhr']),
                        'Asr' => $this->cleanTime($timings['Asr']),
                        'Maghrib' => $this->cleanTime($timings['Maghrib']),
                        'Isha' => $this->cleanTime($timings['Isha']),
                    ],
                    'hijri' => $data['data']['date']['hijri'] ?? null,
                ];
            }

            throw new \Exception('Failed to fetch prayer times');
        } catch (\Exception $e) {
            $this->log($userId, 'error', 'Prayer times check failed: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function cleanTime(string $time): string
    {
        // Remove timezone information (e.g., "05:30 (WIB)" -> "05:30")
        return trim(explode('(', $time)[0]);
    }

    private function sendDailySchedule(int $userId): void
    {
        $result = $this->checkPrayerTimes($userId);

        if ($result['success']) {
            $timings = $result['timings'];
            $hijri = $result['hijri'];

            $message = "🕌 *Jadwal Solat Hari Ini*\n\n";
            $message .= "📍 {$result['location']}\n";
            $message .= "📅 {$result['date']}\n";

            if ($hijri) {
                $message .= "📆 {$hijri['day']} {$hijri['month']['en']} {$hijri['year']} H\n";
            }

            $message .= "\n";
            $message .= "🌅 Subuh: *{$timings['Fajr']}*\n";
            $message .= "🌄 Terbit: {$timings['Sunrise']}\n";
            $message .= "☀️ Dzuhur: *{$timings['Dhuhr']}*\n";
            $message .= "🌤️ Ashar: *{$timings['Asr']}*\n";
            $message .= "🌆 Maghrib: *{$timings['Maghrib']}*\n";
            $message .= "🌙 Isya: *{$timings['Isha']}*\n\n";
            $message .= '_Data dari AlAdhan API_';

            $this->sendTelegramMessage($userId, $message);

            $this->log($userId, 'info', 'Daily schedule sent');
        }
    }
}
