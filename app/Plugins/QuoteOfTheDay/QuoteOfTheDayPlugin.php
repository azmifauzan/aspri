<?php

namespace App\Plugins\QuoteOfTheDay;

use App\Services\Plugin\BasePlugin;
use Illuminate\Support\Facades\Http;

class QuoteOfTheDayPlugin extends BasePlugin
{
    private const API_URL = 'https://api.quotable.io';

    public function getName(): string
    {
        return 'Quote of the Day';
    }

    public function getSlug(): string
    {
        return 'quote-of-the-day';
    }

    public function getDescription(): string
    {
        return 'Kutipan inspiratif dan motivasi setiap hari dari tokoh-tokoh terkenal. Mulai hari dengan semangat positif!';
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
        return 'quote-left';
    }

    public function getConfigSchema(): array
    {
        return [
            'daily_quote' => [
                'type' => 'boolean',
                'label' => 'Kutipan Harian',
                'default' => true,
            ],
            'delivery_time' => [
                'type' => 'time',
                'label' => 'Waktu Pengiriman',
                'default' => '06:00',
                'condition' => 'daily_quote === true',
            ],
            'quote_length' => [
                'type' => 'select',
                'label' => 'Panjang Kutipan',
                'options' => [
                    'short' => 'Pendek (< 100 karakter)',
                    'medium' => 'Sedang (100-300 karakter)',
                    'long' => 'Panjang (> 300 karakter)',
                    'any' => 'Acak',
                ],
                'default' => 'medium',
            ],
            'tags' => [
                'type' => 'multiselect',
                'label' => 'Tema Kutipan',
                'options' => [
                    'inspirational' => 'Inspirasi',
                    'motivational' => 'Motivasi',
                    'wisdom' => 'Kebijaksanaan',
                    'success' => 'Kesuksesan',
                    'life' => 'Kehidupan',
                    'happiness' => 'Kebahagiaan',
                    'love' => 'Cinta',
                    'friendship' => 'Persahabatan',
                    'change' => 'Perubahan',
                    'business' => 'Bisnis',
                ],
                'default' => ['inspirational', 'motivational', 'wisdom'],
            ],
            'include_translation' => [
                'type' => 'boolean',
                'label' => 'Terjemahan Indonesia',
                'default' => true,
                'help' => 'Kutipan akan diterjemahkan ke Bahasa Indonesia',
            ],
            'show_author_bio' => [
                'type' => 'boolean',
                'label' => 'Tampilkan Bio Penulis',
                'default' => false,
            ],
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            'daily_quote' => true,
            'delivery_time' => '06:00',
            'quote_length' => 'medium',
            'tags' => ['inspirational', 'motivational', 'wisdom'],
            'include_translation' => true,
            'show_author_bio' => false,
        ];
    }

    public function validateConfig(array $config): array
    {
        $errors = [];

        if (isset($config['delivery_time']) && ! preg_match('/^\d{2}:\d{2}$/', $config['delivery_time'])) {
            $errors['delivery_time'] = 'Delivery time must be in HH:MM format';
        }

        return $errors;
    }

    public function activate(int $userId): void
    {
        $config = $this->getUserConfig($userId);

        // Schedule daily quote
        if ($config['daily_quote']) {
            $this->createSchedule($userId, [
                'schedule_type' => 'daily',
                'schedule_value' => $config['delivery_time'],
                'metadata' => [
                    'type' => 'daily_quote',
                ],
            ]);
        }

        $this->log($userId, 'info', 'Quote of the Day activated');
    }

    public function deactivate(int $userId): void
    {
        $this->deleteSchedules($userId);
        $this->log($userId, 'info', 'Quote of the Day deactivated');
    }

    public function execute(int $userId, array $config, array $context = []): void
    {
        try {
            $type = $context['type'] ?? 'daily_quote';

            if ($type === 'daily_quote') {
                $this->sendDailyQuote($userId);
            } elseif ($type === 'random_quote') {
                $this->getRandomQuote($userId, $context);
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
            'value' => '06:00',
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
                'action' => "plugin_{$slugPrefix}_random",
                'description' => 'Kutipan inspiratif acak',
                'entities' => [
                    'tags' => 'string|null',
                ],
                'examples' => [
                    'beri saya quote',
                    'quote motivasi',
                    'inspirational quote',
                ],
            ],
            [
                'action' => "plugin_{$slugPrefix}_search",
                'description' => 'Cari kutipan berdasarkan kata kunci',
                'entities' => [
                    'query' => 'string',
                ],
                'examples' => [
                    'cari quote tentang sukses',
                    'search quote about life',
                ],
            ],
        ];
    }

    public function handleChatIntent(int $userId, string $action, array $entities): array
    {
        $slugPrefix = str_replace('-', '_', $this->getSlug());

        if ($action === "plugin_{$slugPrefix}_random") {
            $tags = $entities['tags'] ?? null;
            $result = $this->getRandomQuote($userId, ['tags' => $tags]);

            if ($result['success']) {
                $message = "💡 *Quote for You*\n\n";
                $message .= "_{$result['quote']}_\n\n";
                $message .= "— *{$result['author']}*";

                if (! empty($result['tags'])) {
                    $tagList = array_map(fn ($tag) => "#{$tag}", array_slice($result['tags'], 0, 3));
                    $message .= "\n\n".implode(' ', $tagList);
                }

                return [
                    'success' => true,
                    'message' => $message,
                    'data' => $result,
                ];
            }

            return [
                'success' => false,
                'message' => '❌ Maaf, gagal mengambil kutipan. Coba lagi nanti.',
            ];
        }

        if ($action === "plugin_{$slugPrefix}_search") {
            $query = $entities['query'] ?? '';
            $result = $this->searchQuotes($userId, $query);

            if ($result['success'] && ! empty($result['quotes'])) {
                $message = "💡 *Hasil Pencarian Kutipan*\n\n";

                foreach (array_slice($result['quotes'], 0, 3) as $index => $quote) {
                    $num = $index + 1;
                    $message .= "*{$num}.* _{$quote['content']}_\n";
                    $message .= "   — {$quote['author']}\n\n";
                }

                return [
                    'success' => true,
                    'message' => $message,
                    'data' => $result,
                ];
            }

            return [
                'success' => false,
                'message' => '❌ Tidak ditemukan kutipan yang sesuai.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Action not supported',
        ];
    }

    public function getRandomQuote(int $userId, array $context = []): array
    {
        $config = $this->getUserConfig($userId);

        $tags = $context['tags'] ?? $config['tags'];
        $length = $context['length'] ?? $config['quote_length'];

        try {
            $params = [];

            // Add tags filter
            if (! empty($tags)) {
                $params['tags'] = is_array($tags) ? implode('|', $tags) : $tags;
            }

            // Add length filter
            if ($length !== 'any') {
                $lengthMap = [
                    'short' => ['maxLength' => 100],
                    'medium' => ['minLength' => 100, 'maxLength' => 300],
                    'long' => ['minLength' => 300],
                ];

                if (isset($lengthMap[$length])) {
                    $params = array_merge($params, $lengthMap[$length]);
                }
            }

            $response = Http::timeout(10)->get(self::API_URL.'/random', $params);

            if ($response->successful()) {
                $data = $response->json();

                $this->log($userId, 'info', 'Quote fetched successfully');

                return [
                    'success' => true,
                    'quote' => $data['content'],
                    'author' => $data['author'],
                    'tags' => $data['tags'] ?? [],
                    'length' => strlen($data['content']),
                ];
            }

            throw new \Exception('Failed to fetch quote');
        } catch (\Exception $e) {
            $this->log($userId, 'error', 'Failed to fetch quote: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function sendDailyQuote(int $userId): void
    {
        $config = $this->getUserConfig($userId);
        $result = $this->getRandomQuote($userId);

        if ($result['success']) {
            $quote = $result['quote'];
            $author = $result['author'];

            $message = "💡 *Quote of the Day*\n\n";
            $message .= "_{$quote}_\n\n";
            $message .= "— *{$author}*\n";

            // Add tags
            if (! empty($result['tags'])) {
                $tags = array_map(fn ($tag) => "#{$tag}", array_slice($result['tags'], 0, 3));
                $message .= "\n".implode(' ', $tags)."\n";
            }

            // Add translation if enabled (simple approximation)
            if ($config['include_translation']) {
                $translation = $this->translateQuote($quote);
                if ($translation) {
                    $message .= "\n📖 _".$translation."_\n";
                }
            }

            $message .= "\n_From Quotable API_";

            $this->sendTelegramMessage($userId, $message);

            $this->log($userId, 'info', 'Daily quote sent');
        }
    }

    private function translateQuote(string $quote): ?string
    {
        // Simple translations for common inspirational words
        // In production, you'd use a translation API like Google Translate
        $commonTranslations = [
            'The only way to do great work is to love what you do.' => 'Satu-satunya cara untuk melakukan pekerjaan hebat adalah mencintai apa yang Anda lakukan.',
            'Success is not final, failure is not fatal: it is the courage to continue that counts.' => 'Kesuksesan bukanlah akhir, kegagalan bukanlah fatal: yang penting adalah keberanian untuk melanjutkan.',
            'Believe you can and you\'re halfway there.' => 'Percayalah Anda bisa dan Anda sudah setengah jalan menuju kesana.',
        ];

        return $commonTranslations[$quote] ?? null;
    }

    public function getQuoteByAuthor(int $userId, string $authorSlug): array
    {
        try {
            $response = Http::timeout(10)->get(self::API_URL.'/quotes', [
                'author' => $authorSlug,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'quotes' => $data['results'] ?? [],
                    'count' => $data['count'] ?? 0,
                ];
            }

            throw new \Exception('Failed to fetch quotes by author');
        } catch (\Exception $e) {
            $this->log($userId, 'error', 'Failed to fetch quotes by author: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function searchQuotes(int $userId, string $query): array
    {
        try {
            $response = Http::timeout(10)->get(self::API_URL.'/search/quotes', [
                'query' => $query,
                'limit' => 10,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'quotes' => $data['results'] ?? [],
                    'count' => $data['count'] ?? 0,
                ];
            }

            throw new \Exception('Failed to search quotes');
        } catch (\Exception $e) {
            $this->log($userId, 'error', 'Quote search failed: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
