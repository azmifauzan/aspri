<?php

namespace App\Plugins\NewsHeadlines;

use App\Services\Plugin\BasePlugin;
use Illuminate\Support\Facades\Http;

class NewsHeadlinesPlugin extends BasePlugin
{
    private const API_URL = 'https://newsapi.org/v2';

    public function getName(): string
    {
        return 'News Headlines';
    }

    public function getSlug(): string
    {
        return 'news-headlines';
    }

    public function getDescription(): string
    {
        return 'Berita terkini dari berbagai sumber terpercaya. Dapatkan update berita sesuai minat Anda setiap hari.';
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
        return 'newspaper';
    }

    public function getConfigSchema(): array
    {
        return [
            'api_key' => [
                'type' => 'text',
                'label' => 'NewsAPI Key',
                'placeholder' => 'Dapatkan gratis di newsapi.org',
                'required' => true,
                'help' => 'Daftar gratis di newsapi.org untuk mendapatkan API key',
            ],
            'country' => [
                'type' => 'select',
                'label' => 'Negara',
                'options' => [
                    'id' => 'Indonesia',
                    'us' => 'United States',
                    'gb' => 'United Kingdom',
                    'au' => 'Australia',
                    'ca' => 'Canada',
                    'my' => 'Malaysia',
                    'sg' => 'Singapore',
                ],
                'default' => 'id',
            ],
            'categories' => [
                'type' => 'multiselect',
                'label' => 'Kategori Berita',
                'options' => [
                    'general' => 'Umum',
                    'business' => 'Bisnis',
                    'technology' => 'Teknologi',
                    'entertainment' => 'Hiburan',
                    'sports' => 'Olahraga',
                    'science' => 'Sains',
                    'health' => 'Kesehatan',
                ],
                'default' => ['general', 'technology'],
            ],
            'morning_brief' => [
                'type' => 'boolean',
                'label' => 'Ringkasan Pagi',
                'default' => true,
            ],
            'brief_time' => [
                'type' => 'time',
                'label' => 'Waktu Pengiriman',
                'default' => '07:00',
                'condition' => 'morning_brief === true',
            ],
            'evening_brief' => [
                'type' => 'boolean',
                'label' => 'Ringkasan Sore',
                'default' => false,
            ],
            'evening_time' => [
                'type' => 'time',
                'label' => 'Waktu Pengiriman Sore',
                'default' => '18:00',
                'condition' => 'evening_brief === true',
            ],
            'max_headlines' => [
                'type' => 'number',
                'label' => 'Jumlah Berita',
                'default' => 5,
                'min' => 3,
                'max' => 10,
            ],
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            'api_key' => '',
            'country' => 'id',
            'categories' => ['general', 'technology'],
            'morning_brief' => true,
            'brief_time' => '07:00',
            'evening_brief' => false,
            'evening_time' => '18:00',
            'max_headlines' => 5,
        ];
    }

    public function validateConfig(array $config): array
    {
        $errors = [];

        if (! isset($config['api_key']) || empty($config['api_key'])) {
            $errors['api_key'] = 'API key is required. Get it free from newsapi.org';
        }

        if (isset($config['brief_time']) && ! preg_match('/^\d{2}:\d{2}$/', $config['brief_time'])) {
            $errors['brief_time'] = 'Brief time must be in HH:MM format';
        }

        if (isset($config['max_headlines']) && ($config['max_headlines'] < 3 || $config['max_headlines'] > 10)) {
            $errors['max_headlines'] = 'Max headlines must be between 3 and 10';
        }

        return $errors;
    }

    public function activate(int $userId): void
    {
        $config = $this->getUserConfig($userId);

        // Schedule morning brief
        if ($config['morning_brief']) {
            $this->createSchedule($userId, [
                'schedule_type' => 'daily',
                'schedule_value' => $config['brief_time'],
                'metadata' => [
                    'type' => 'morning_brief',
                ],
            ]);
        }

        // Schedule evening brief
        if ($config['evening_brief']) {
            $this->createSchedule($userId, [
                'schedule_type' => 'daily',
                'schedule_value' => $config['evening_time'],
                'metadata' => [
                    'type' => 'evening_brief',
                ],
            ]);
        }

        $this->log($userId, 'info', 'News Headlines activated');
    }

    public function deactivate(int $userId): void
    {
        $this->deleteSchedules($userId);
        $this->log($userId, 'info', 'News Headlines deactivated');
    }

    public function execute(int $userId, array $config, array $context = []): void
    {
        try {
            $type = $context['type'] ?? 'morning_brief';

            if (in_array($type, ['morning_brief', 'evening_brief'])) {
                $this->sendNewsBrief($userId, $type);
            } elseif ($type === 'search_news') {
                $this->searchNews($userId, $context);
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
                'action' => "plugin_{$slugPrefix}_latest",
                'description' => 'Berita terbaru sesuai kategori',
                'entities' => [
                    'category' => 'string|null',
                ],
                'examples' => [
                    'berita terkini',
                    'latest news technology',
                    'berita bisnis hari ini',
                ],
            ],
            [
                'action' => "plugin_{$slugPrefix}_search",
                'description' => 'Cari berita berdasarkan kata kunci',
                'entities' => [
                    'query' => 'string',
                ],
                'examples' => [
                    'cari berita AI',
                    'search news about startup',
                ],
            ],
        ];
    }

    public function handleChatIntent(int $userId, string $action, array $entities): array
    {
        $slugPrefix = str_replace('-', '_', $this->getSlug());

        if ($action === "plugin_{$slugPrefix}_latest") {
            $category = $entities['category'] ?? null;
            $result = $this->getTopHeadlines($userId, $category);

            if ($result['success'] && ! empty($result['articles'])) {
                $message = "📰 *Berita Terkini*\n\n";

                foreach (array_slice($result['articles'], 0, 5) as $index => $article) {
                    $num = $index + 1;
                    $message .= "*{$num}. {$article['title']}*\n";
                    $message .= '📰 '.($article['source']['name'] ?? 'Unknown')."\n";

                    if (! empty($article['url'])) {
                        $message .= "🔗 {$article['url']}\n";
                    }

                    $message .= "\n";
                }

                return [
                    'success' => true,
                    'message' => $message,
                    'data' => $result,
                ];
            }

            return [
                'success' => false,
                'message' => '❌ Maaf, gagal mengambil berita. Pastikan API key sudah dikonfigurasi.',
            ];
        }

        if ($action === "plugin_{$slugPrefix}_search") {
            $query = $entities['query'] ?? '';
            $result = $this->searchNews($userId, ['query' => $query]);

            if ($result['success'] && ! empty($result['articles'])) {
                $message = "📰 *Hasil Pencarian Berita*\n\n";

                foreach (array_slice($result['articles'], 0, 5) as $index => $article) {
                    $num = $index + 1;
                    $message .= "*{$num}. {$article['title']}*\n";
                    $message .= '📰 '.($article['source']['name'] ?? 'Unknown')."\n";

                    if (! empty($article['url'])) {
                        $message .= "🔗 {$article['url']}\n";
                    }

                    $message .= "\n";
                }

                return [
                    'success' => true,
                    'message' => $message,
                    'data' => $result,
                ];
            }

            return [
                'success' => false,
                'message' => '❌ Tidak ditemukan berita yang sesuai.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Action not supported',
        ];
    }

    public function getTopHeadlines(int $userId, ?string $category = null): array
    {
        $config = $this->getUserConfig($userId);

        if (empty($config['api_key'])) {
            return [
                'success' => false,
                'error' => 'API key not configured',
            ];
        }

        $country = $config['country'];
        $limit = $config['max_headlines'];

        try {
            $params = [
                'country' => $country,
                'pageSize' => $limit,
                'apiKey' => $config['api_key'],
            ];

            if ($category) {
                $params['category'] = $category;
            }

            $response = Http::timeout(10)->get(self::API_URL.'/top-headlines', $params);

            if ($response->successful()) {
                $data = $response->json();

                if ($data['status'] === 'ok') {
                    $this->log($userId, 'info', 'Headlines fetched successfully');

                    return [
                        'success' => true,
                        'articles' => $data['articles'],
                        'total' => $data['totalResults'] ?? 0,
                    ];
                }
            }

            throw new \Exception('Failed to fetch news: '.($response->json()['message'] ?? 'Unknown error'));
        } catch (\Exception $e) {
            $this->log($userId, 'error', 'Failed to fetch headlines: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function sendNewsBrief(int $userId, string $type): void
    {
        $config = $this->getUserConfig($userId);
        $categories = $config['categories'];

        $allArticles = [];

        foreach ($categories as $category) {
            $result = $this->getTopHeadlines($userId, $category);

            if ($result['success'] && ! empty($result['articles'])) {
                $allArticles = array_merge($allArticles, array_slice($result['articles'], 0, 2));
            }
        }

        if (empty($allArticles)) {
            return;
        }

        $greeting = $type === 'morning_brief' ? 'Pagi' : 'Sore';
        $icon = $type === 'morning_brief' ? '🌅' : '🌆';

        $message = "{$icon} *Berita {$greeting} Hari Ini*\n\n";

        foreach (array_slice($allArticles, 0, $config['max_headlines']) as $index => $article) {
            $num = $index + 1;
            $title = $article['title'];
            $source = $article['source']['name'] ?? 'Unknown';

            $message .= "*{$num}. {$title}*\n";
            $message .= "📰 {$source}\n";

            if (! empty($article['url'])) {
                $message .= "🔗 {$article['url']}\n";
            }

            $message .= "\n";
        }

        $message .= '_Powered by NewsAPI_';

        $this->sendTelegramMessage($userId, $message);

        $this->log($userId, 'info', 'News brief sent');
    }

    public function searchNews(int $userId, array $context): array
    {
        $config = $this->getUserConfig($userId);

        if (empty($config['api_key'])) {
            return [
                'success' => false,
                'error' => 'API key not configured',
            ];
        }

        $query = $context['query'] ?? '';
        $limit = $context['limit'] ?? 5;

        try {
            $response = Http::timeout(10)->get(self::API_URL.'/everything', [
                'q' => $query,
                'language' => 'id',
                'sortBy' => 'publishedAt',
                'pageSize' => $limit,
                'apiKey' => $config['api_key'],
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if ($data['status'] === 'ok') {
                    return [
                        'success' => true,
                        'articles' => $data['articles'],
                        'total' => $data['totalResults'] ?? 0,
                    ];
                }
            }

            throw new \Exception('Failed to search news');
        } catch (\Exception $e) {
            $this->log($userId, 'error', 'News search failed: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
