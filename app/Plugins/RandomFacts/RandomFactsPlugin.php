<?php

namespace App\Plugins\RandomFacts;

use App\Services\Plugin\BasePlugin;
use Illuminate\Support\Facades\Http;

class RandomFactsPlugin extends BasePlugin
{
    private const API_URL = 'https://api.api-ninjas.com/v1/facts';

    public function getName(): string
    {
        return 'Random Facts';
    }

    public function getSlug(): string
    {
        return 'random-facts';
    }

    public function getDescription(): string
    {
        return 'Fakta menarik dan unik setiap hari. Perluas wawasan dengan pengetahuan baru yang menghibur dan edukatif!';
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
        return 'lightbulb';
    }

    public function getConfigSchema(): array
    {
        return [
            'api_key' => [
                'type' => 'text',
                'label' => 'API Ninjas Key',
                'placeholder' => 'Dapatkan gratis di api-ninjas.com',
                'required' => true,
                'help' => 'Daftar gratis di api-ninjas.com untuk mendapatkan API key',
            ],
            'daily_fact' => [
                'type' => 'boolean',
                'label' => 'Fakta Harian',
                'default' => true,
            ],
            'delivery_time' => [
                'type' => 'time',
                'label' => 'Waktu Pengiriman',
                'default' => '09:00',
                'condition' => 'daily_fact === true',
            ],
            'fact_count' => [
                'type' => 'number',
                'label' => 'Jumlah Fakta Per Hari',
                'default' => 3,
                'min' => 1,
                'max' => 5,
            ],
            'fun_fact_mode' => [
                'type' => 'boolean',
                'label' => 'Mode Fun Facts',
                'default' => true,
                'help' => 'Prioritaskan fakta yang menghibur',
            ],
            'educational_focus' => [
                'type' => 'boolean',
                'label' => 'Fokus Edukatif',
                'default' => true,
                'help' => 'Sertakan fakta yang lebih mendidik',
            ],
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            'api_key' => '',
            'daily_fact' => true,
            'delivery_time' => '09:00',
            'fact_count' => 3,
            'fun_fact_mode' => true,
            'educational_focus' => true,
        ];
    }

    public function validateConfig(array $config): array
    {
        $errors = [];

        if (! isset($config['api_key']) || empty($config['api_key'])) {
            $errors['api_key'] = 'API key is required. Get it free from api-ninjas.com';
        }

        if (isset($config['delivery_time']) && ! preg_match('/^\d{2}:\d{2}$/', $config['delivery_time'])) {
            $errors['delivery_time'] = 'Delivery time must be in HH:MM format';
        }

        if (isset($config['fact_count']) && ($config['fact_count'] < 1 || $config['fact_count'] > 5)) {
            $errors['fact_count'] = 'Fact count must be between 1 and 5';
        }

        return $errors;
    }

    public function activate(int $userId): void
    {
        $config = $this->getUserConfig($userId);

        // Schedule daily facts
        if ($config['daily_fact']) {
            $this->createSchedule($userId, [
                'schedule_type' => 'daily',
                'schedule_value' => $config['delivery_time'],
                'metadata' => [
                    'type' => 'daily_fact',
                ],
            ]);
        }

        $this->log($userId, 'info', 'Random Facts activated');
    }

    public function deactivate(int $userId): void
    {
        $this->deleteSchedules($userId);
        $this->log($userId, 'info', 'Random Facts deactivated');
    }

    public function execute(int $userId, array $config, array $context = []): void
    {
        try {
            $type = $context['type'] ?? 'daily_fact';

            if ($type === 'daily_fact') {
                $this->sendDailyFacts($userId);
            } elseif ($type === 'get_fact') {
                $this->getRandomFact($userId);
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
            'value' => '09:00',
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
                'description' => 'Fakta menarik acak',
                'entities' => [
                    'count' => 'number|null',
                ],
                'examples' => [
                    'beri saya fakta menarik',
                    'random fact',
                    'fakta unik 3',
                ],
            ],
            [
                'action' => "plugin_{$slugPrefix}_animal",
                'description' => 'Fakta hewan acak',
                'entities' => [
                    'type' => 'string|null',
                ],
                'examples' => [
                    'fakta hewan',
                    'animal fact',
                ],
            ],
        ];
    }

    public function handleChatIntent(int $userId, string $action, array $entities): array
    {
        $slugPrefix = str_replace('-', '_', $this->getSlug());

        if ($action === "plugin_{$slugPrefix}_random") {
            $count = (int) ($entities['count'] ?? 1);
            $result = $this->getRandomFact($userId, min(max($count, 1), 5));

            if ($result['success'] && ! empty($result['facts'])) {
                $message = "🧠 *Fakta Menarik*\n\n";

                foreach ($result['facts'] as $index => $factData) {
                    $num = $index + 1;
                    $fact = $factData['fact'];
                    $emoji = $this->getFactEmoji($fact);

                    if ($count > 1) {
                        $message .= "*{$num}.* {$emoji} {$fact}\n\n";
                    } else {
                        $message .= "{$emoji} {$fact}\n\n";
                    }
                }

                $message .= '💡 _Tahukah Anda?_';

                return [
                    'success' => true,
                    'message' => $message,
                    'data' => $result,
                ];
            }

            return [
                'success' => false,
                'message' => '❌ Maaf, gagal mengambil fakta. Pastikan API key sudah dikonfigurasi.',
            ];
        }

        if ($action === "plugin_{$slugPrefix}_animal") {
            $result = $this->getAnimalFact($userId);

            if ($result['success'] && ! empty($result['animals'])) {
                $animal = $result['animals'][0];

                $message = "🐾 *Fakta Hewan*\n\n";
                $message .= "*{$animal['name']}*\n\n";

                if (isset($animal['characteristics'])) {
                    $chars = $animal['characteristics'];

                    if (isset($chars['type'])) {
                        $message .= "📋 Tipe: {$chars['type']}\n";
                    }
                    if (isset($chars['habitat'])) {
                        $message .= "🏞️ Habitat: {$chars['habitat']}\n";
                    }
                    if (isset($chars['lifespan'])) {
                        $message .= "⏳ Umur: {$chars['lifespan']}\n";
                    }
                }

                return [
                    'success' => true,
                    'message' => $message,
                    'data' => $result,
                ];
            }

            return [
                'success' => false,
                'message' => '❌ Maaf, gagal mengambil fakta hewan.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Action not supported',
        ];
    }

    public function getRandomFact(int $userId, int $limit = 1): array
    {
        $config = $this->getUserConfig($userId);

        if (empty($config['api_key'])) {
            return [
                'success' => false,
                'error' => 'API key not configured',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Api-Key' => $config['api_key'],
                ])
                ->get(self::API_URL, [
                    'limit' => $limit,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                $this->log($userId, 'info', 'Facts fetched successfully');

                return [
                    'success' => true,
                    'facts' => $data,
                    'count' => count($data),
                ];
            }

            throw new \Exception('Failed to fetch facts: '.($response->body() ?? 'Unknown error'));
        } catch (\Exception $e) {
            $this->log($userId, 'error', 'Failed to fetch facts: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function sendDailyFacts(int $userId): void
    {
        $config = $this->getUserConfig($userId);
        $count = $config['fact_count'];

        $result = $this->getRandomFact($userId, $count);

        if ($result['success'] && ! empty($result['facts'])) {
            $message = "🧠 *Fakta Menarik Hari Ini*\n\n";

            foreach ($result['facts'] as $index => $factData) {
                $num = $index + 1;
                $fact = $factData['fact'];

                // Add emoji based on content (simple keyword matching)
                $emoji = $this->getFactEmoji($fact);

                $message .= "*{$num}.* {$emoji} {$fact}\n\n";
            }

            $message .= "💡 _Tahukah Anda?_\n";
            $message .= '_Powered by API Ninjas_';

            $this->sendTelegramMessage($userId, $message);

            $this->log($userId, 'info', 'Daily facts sent');
        }
    }

    private function getFactEmoji(string $fact): string
    {
        $fact = strtolower($fact);

        // Simple keyword matching for appropriate emoji
        $emojiMap = [
            'space' => '🚀',
            'ocean' => '🌊',
            'animal' => '🐾',
            'earth' => '🌍',
            'sun' => '☀️',
            'moon' => '🌙',
            'star' => '⭐',
            'brain' => '🧠',
            'heart' => '❤️',
            'water' => '💧',
            'tree' => '🌳',
            'mountain' => '⛰️',
            'human' => '👤',
            'body' => '🫀',
            'eye' => '👁️',
            'food' => '🍽️',
            'fruit' => '🍎',
            'insect' => '🐛',
            'bird' => '🐦',
            'fish' => '🐟',
            'book' => '📚',
            'music' => '🎵',
            'art' => '🎨',
            'science' => '🔬',
            'computer' => '💻',
            'phone' => '📱',
            'world' => '🌎',
            'country' => '🗺️',
            'city' => '🏙️',
            'history' => '📜',
            'ancient' => '🏛️',
            'gold' => '🥇',
            'diamond' => '💎',
            'time' => '⏰',
            'speed' => '⚡',
            'power' => '💪',
            'language' => '🗣️',
        ];

        foreach ($emojiMap as $keyword => $emoji) {
            if (str_contains($fact, $keyword)) {
                return $emoji;
            }
        }

        return '✨'; // Default emoji
    }

    public function getAnimalFact(int $userId): array
    {
        $config = $this->getUserConfig($userId);

        if (empty($config['api_key'])) {
            return [
                'success' => false,
                'error' => 'API key not configured',
            ];
        }

        try {
            // API Ninjas also has specific animal facts endpoint
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Api-Key' => $config['api_key'],
                ])
                ->get('https://api.api-ninjas.com/v1/animals');

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'animals' => $data,
                ];
            }

            throw new \Exception('Failed to fetch animal facts');
        } catch (\Exception $e) {
            $this->log($userId, 'error', 'Failed to fetch animal facts: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
