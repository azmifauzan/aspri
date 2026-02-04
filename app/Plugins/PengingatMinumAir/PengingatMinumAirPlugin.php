<?php

namespace App\Plugins\PengingatMinumAir;

use App\Models\User;
use App\Services\Plugin\BasePlugin;
use Carbon\Carbon;
use Telegram\Bot\Api;

class PengingatMinumAirPlugin extends BasePlugin
{
    public function getSlug(): string
    {
        return 'pengingat-minum-air';
    }

    public function getName(): string
    {
        return 'Pengingat Minum Air';
    }

    public function getDescription(): string
    {
        return 'Plugin yang mengirimkan pengingat untuk minum air secara berkala, membantu Anda mencapai target hidrasi harian.';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getIcon(): string
    {
        return 'droplets';
    }

    /**
     * @return array<string, array{type: string, label: string, description?: string, default?: mixed, required?: bool, options?: array<string>, multiple?: bool, min?: int, max?: int}>
     */
    public function getConfigSchema(): array
    {
        return [
            'daily_target' => [
                'type' => 'number',
                'label' => 'Target Harian (gelas)',
                'description' => 'Jumlah gelas air yang ingin Anda minum per hari (1 gelas = 250ml)',
                'default' => 8,
                'required' => true,
                'min' => 4,
                'max' => 20,
            ],
            'reminder_interval' => [
                'type' => 'select',
                'label' => 'Interval Pengingat',
                'description' => 'Seberapa sering Anda ingin diingatkan',
                'options' => ['60', '90', '120', '180'],
                'default' => '120',
                'required' => true,
            ],
            'start_time' => [
                'type' => 'time',
                'label' => 'Waktu Mulai',
                'description' => 'Waktu mulai mengirim pengingat',
                'default' => '07:00',
                'required' => true,
            ],
            'end_time' => [
                'type' => 'time',
                'label' => 'Waktu Selesai',
                'description' => 'Waktu berhenti mengirim pengingat',
                'default' => '21:00',
                'required' => true,
            ],
            'include_tips' => [
                'type' => 'boolean',
                'label' => 'Sertakan Tips Kesehatan',
                'description' => 'Tambahkan tips kesehatan tentang manfaat minum air',
                'default' => true,
            ],
            'encouraging_messages' => [
                'type' => 'boolean',
                'label' => 'Pesan Penyemangat',
                'description' => 'Tambahkan pesan penyemangat dalam pengingat',
                'default' => true,
            ],
        ];
    }

    public function supportsScheduling(): bool
    {
        return true;
    }

    /**
     * @return array{type: string, value: string}
     */
    public function getDefaultSchedule(): ?array
    {
        return [
            'type' => 'interval',
            'value' => '120', // 2 hours
        ];
    }

    /**
     * Execute the plugin - send a water reminder.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     */
    public function execute(int $userId, array $config, array $context = []): void
    {
        $user = $this->getUser($userId);

        if (! $user) {
            $this->logError('User not found', $userId);

            return;
        }

        if (! $user->telegram_chat_id) {
            $this->logWarning('User has no Telegram connected', $userId);

            return;
        }

        // Check if current time is within active hours
        if (! $this->isWithinActiveHours($config)) {
            $this->logDebug('Outside active hours, skipping', $userId);

            return;
        }

        try {
            $message = $this->formatMessage($config, $user);
            $this->sendTelegramMessage($user->telegram_chat_id, $message);

            $this->logInfo('Water reminder sent', $userId);
        } catch (\Exception $e) {
            $this->logError('Failed to send reminder: '.$e->getMessage(), $userId, [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if current time is within active hours.
     *
     * @param  array<string, mixed>  $config
     */
    protected function isWithinActiveHours(array $config): bool
    {
        $startTime = $config['start_time'] ?? '07:00';
        $endTime = $config['end_time'] ?? '21:00';

        $now = Carbon::now();
        $start = Carbon::createFromFormat('H:i', $startTime);
        $end = Carbon::createFromFormat('H:i', $endTime);

        return $now->between($start, $end);
    }

    /**
     * Format the reminder message.
     *
     * @param  array<string, mixed>  $config
     */
    protected function formatMessage(array $config, User $user): string
    {
        $callPreference = $user->profile?->call_preference ?? '';
        $aspriName = $user->profile?->aspri_name ?? 'ASPRI';

        $messages = [
            '💧 Waktunya minum air!',
            '🥤 Jangan lupa minum air ya!',
            '💦 Saatnya hidrasi tubuh!',
            '🌊 Sudah minum air belum?',
            '💧 Yuk, teguk air putihnya!',
        ];

        $message = $messages[array_rand($messages)];

        if ($callPreference) {
            $message .= " {$callPreference}";
        }

        $message .= "\n\n";

        $dailyTarget = $config['daily_target'] ?? 8;
        $message .= "🎯 Target hari ini: {$dailyTarget} gelas\n";

        if ($config['encouraging_messages'] ?? true) {
            $encouragements = [
                '💪 Tubuh yang terhidrasi adalah tubuh yang sehat!',
                '✨ Setiap tegukan mendekatkan Anda ke target!',
                '🌟 Anda melakukan hal baik untuk tubuh Anda!',
                '💚 Air putih adalah pilihan terbaik!',
                '🎊 Terus semangat mencapai target!',
            ];
            $message .= "\n".$encouragements[array_rand($encouragements)]."\n";
        }

        if ($config['include_tips'] ?? true) {
            $tips = [
                '📌 Tips: Minum air sebelum makan membantu pencernaan.',
                '📌 Tips: Air dingin dapat membakar lebih banyak kalori.',
                '📌 Tips: Dehidrasi ringan dapat menurunkan konsentrasi.',
                '📌 Tips: Air membantu menjaga kesehatan kulit.',
                '📌 Tips: Minum air sebelum tidur mencegah kram kaki.',
                '📌 Tips: Air membantu proses detoksifikasi tubuh.',
            ];
            $message .= "\n".$tips[array_rand($tips)]."\n";
        }

        $message .= "\n— {$aspriName}";

        return $message;
    }

    /**
     * Send message via Telegram.
     */
    protected function sendTelegramMessage(int $chatId, string $message): void
    {
        $token = config('services.telegram.bot_token');

        if (! $token) {
            throw new \RuntimeException('Telegram bot token not configured');
        }

        $telegram = new Api($token);

        // Disable SSL verification if configured
        if (config('services.telegram.http_client_verify') === false) {
            $telegram->setHttpClientHandler(
                new \Telegram\Bot\HttpClients\GuzzleHttpClient(
                    new \GuzzleHttp\Client(['verify' => false])
                )
            );
        }

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
        ]);
    }

    /**
     * Format interval label for display.
     */
    public static function formatIntervalLabel(string $minutes): string
    {
        return match ($minutes) {
            '60' => 'Setiap 1 jam',
            '90' => 'Setiap 1.5 jam',
            '120' => 'Setiap 2 jam',
            '180' => 'Setiap 3 jam',
            default => "Setiap {$minutes} menit",
        };
    }
}
