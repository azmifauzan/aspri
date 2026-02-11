<?php

namespace App\Plugins\KataMotivasi;

use App\Models\User;
use App\Services\Plugin\BasePlugin;

class KataMotivasiPlugin extends BasePlugin
{
    /**
     * @var array<int, array{quote: string, author: string, category: string}>
     */
    protected array $quotes = [];

    public function __construct()
    {
        $this->loadQuotes();
    }

    public function getSlug(): string
    {
        return 'kata-motivasi';
    }

    public function getName(): string
    {
        return 'Kata Motivasi';
    }

    public function getDescription(): string
    {
        return 'Plugin yang mengirimkan kata-kata motivasi secara berkala melalui Telegram bot untuk memotivasi Anda sepanjang hari.';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getIcon(): string
    {
        return 'sparkles';
    }

    /**
     * @return array<string, array{type: string, label: string, description?: string, default?: mixed, required?: bool, options?: array<string>, multiple?: bool, min?: int, max?: int}>
     */
    public function getConfigSchema(): array
    {
        return [
            'delivery_time' => [
                'type' => 'time',
                'label' => 'Waktu Pengiriman',
                'description' => 'Waktu untuk menerima kata motivasi setiap hari (format: HH:MM)',
                'default' => '07:00',
                'required' => true,
            ],
            'categories' => [
                'type' => 'multiselect',
                'label' => 'Kategori Motivasi',
                'description' => 'Pilih kategori quotes yang ingin Anda terima',
                'options' => ['general', 'business', 'health', 'productivity', 'spiritual'],
                'default' => ['general'],
                'required' => true,
            ],
            'include_author' => [
                'type' => 'boolean',
                'label' => 'Tampilkan Nama Penulis',
                'description' => 'Sertakan nama penulis quote dalam pesan',
                'default' => true,
            ],
            'include_custom' => [
                'type' => 'boolean',
                'label' => 'Sertakan Quotes Custom',
                'description' => 'Tambahkan quotes pribadi Anda ke dalam rotasi',
                'default' => false,
            ],
            'custom_quotes' => [
                'type' => 'textarea',
                'label' => 'Quotes Custom',
                'description' => 'Masukkan quotes pribadi Anda, satu per baris',
                'default' => '',
            ],
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            'delivery_time' => '07:00',
            'categories' => ['general'],
            'include_author' => true,
            'include_custom' => false,
            'custom_quotes' => '',
        ];
    }

    public function validateConfig(array $config): array
    {
        $errors = [];

        if (isset($config['delivery_time']) && ! preg_match('/^\d{2}:\d{2}$/', $config['delivery_time'])) {
            $errors['delivery_time'] = 'Format waktu tidak valid (harus HH:MM)';
        }

        return $errors;
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
            'type' => 'daily',
            'value' => '07:00',
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
                'action' => "plugin_{$slugPrefix}_quote",
                'description' => 'Minta kata motivasi acak',
                'entities' => [
                    'category' => 'string|null',
                ],
                'examples' => [
                    'beri saya kata motivasi',
                    'kata motivasi hari ini',
                    'motivational quote',
                ],
            ],
        ];
    }

    public function handleChatIntent(int $userId, string $action, array $entities): array
    {
        $slugPrefix = str_replace('-', '_', $this->getSlug());

        if ($action !== "plugin_{$slugPrefix}_quote") {
            return [
                'success' => false,
                'message' => 'Action not supported',
            ];
        }

        $config = $this->getUserConfig($userId);

        if (! empty($entities['category'])) {
            $config['categories'] = [$entities['category']];
        }

        $quote = $this->getRandomQuote($config);

        if (! $quote) {
            return [
                'success' => false,
                'message' => 'Belum ada kata motivasi untuk kategori tersebut.',
            ];
        }

        $user = $this->getUser($userId);

        if (! $user) {
            return [
                'success' => false,
                'message' => 'User tidak ditemukan.',
            ];
        }

        return [
            'success' => true,
            'message' => $this->formatMessage($quote, $config, $user),
            'data' => $quote,
        ];
    }

    public function activate(int $userId): void
    {
        $config = $this->getUserConfig($userId);

        $this->createSchedule($userId, [
            'schedule_type' => 'daily',
            'schedule_value' => $config['delivery_time'],
            'metadata' => [
                'type' => 'daily_quote',
            ],
        ]);

        $this->log($userId, 'info', 'Kata Motivasi activated');
    }

    public function deactivate(int $userId): void
    {
        $this->deleteSchedules($userId);
        $this->log($userId, 'info', 'Kata Motivasi deactivated');
    }

    /**
     * Execute the plugin - send a motivational quote.
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

        try {
            $quote = $this->getRandomQuote($config);

            if (! $quote) {
                $this->logWarning('No quotes available for selected categories', $userId, ['config' => $config]);

                return;
            }

            $message = $this->formatMessage($quote, $config, $user);
            $this->sendTelegramMessage($userId, $message);

            $this->logInfo('Motivational quote sent', $userId, [
                'quote' => $quote['quote'],
                'author' => $quote['author'] ?? 'Unknown',
            ]);
        } catch (\Exception $e) {
            $this->logError('Failed to send quote: '.$e->getMessage(), $userId, [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get a random quote based on config.
     *
     * @param  array<string, mixed>  $config
     * @return array{quote: string, author: string, category: string}|null
     */
    protected function getRandomQuote(array $config): ?array
    {
        $categories = $config['categories'] ?? ['general'];
        $includeCustom = $config['include_custom'] ?? false;
        $customQuotes = $config['custom_quotes'] ?? '';

        // Filter quotes by category
        $availableQuotes = array_filter($this->quotes, function ($quote) use ($categories) {
            return in_array($quote['category'], $categories);
        });

        // Add custom quotes if enabled
        if ($includeCustom && ! empty($customQuotes)) {
            $customLines = array_filter(array_map('trim', explode("\n", $customQuotes)));
            foreach ($customLines as $line) {
                $availableQuotes[] = [
                    'quote' => $line,
                    'author' => 'Anda',
                    'category' => 'custom',
                ];
            }
        }

        if (empty($availableQuotes)) {
            return null;
        }

        return $availableQuotes[array_rand($availableQuotes)];
    }

    /**
     * Format the message with emoji and styling.
     *
     * @param  array{quote: string, author: string, category: string}  $quote
     * @param  array<string, mixed>  $config
     */
    protected function formatMessage(array $quote, array $config, User $user): string
    {
        $callPreference = $user->profile?->call_preference ?? '';
        $aspriName = $user->profile?->aspri_name ?? 'ASPRI';
        $greeting = $this->getGreetingByTime();

        $message = "🌟 *{$greeting}";
        if ($callPreference) {
            $message .= ", {$callPreference}";
        }
        $message .= "!*\n\n";

        $message .= "✨ _{$quote['quote']}_\n";

        if (($config['include_author'] ?? true) && ! empty($quote['author'])) {
            $message .= "\n— *{$quote['author']}*";
        }

        $message .= "\n\n🎯 Semoga hari Anda penuh semangat!\n";
        $message .= "— {$aspriName}";

        return $message;
    }

    /**
     * Get greeting based on current time.
     */
    protected function getGreetingByTime(): string
    {
        $hour = (int) now()->format('H');

        return match (true) {
            $hour >= 5 && $hour < 11 => 'Selamat Pagi',
            $hour >= 11 && $hour < 15 => 'Selamat Siang',
            $hour >= 15 && $hour < 18 => 'Selamat Sore',
            default => 'Selamat Malam',
        };
    }

    /**
     * Load quotes from JSON file.
     */
    protected function loadQuotes(): void
    {
        $quotesFile = __DIR__.'/quotes.json';

        if (file_exists($quotesFile)) {
            $content = file_get_contents($quotesFile);
            $this->quotes = json_decode($content, true) ?? [];
        }
    }
}
