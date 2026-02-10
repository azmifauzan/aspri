<?php

namespace App\Plugins\CurrencyConverter;

use App\Services\Plugin\BasePlugin;
use Illuminate\Support\Facades\Http;

class CurrencyConverterPlugin extends BasePlugin
{
    private const API_URL = 'https://api.exchangerate-api.com/v4/latest/';

    public function getName(): string
    {
        return 'Currency Converter';
    }

    public function getSlug(): string
    {
        return 'currency-converter';
    }

    public function getDescription(): string
    {
        return 'Konversi mata uang dengan nilai tukar realtime dari berbagai negara. Mendukung 150+ mata uang dunia.';
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
        return 'banknote';
    }

    public function getConfigSchema(): array
    {
        return [
            'base_currency' => [
                'type' => 'select',
                'label' => 'Mata Uang Dasar',
                'options' => [
                    'IDR' => 'Indonesian Rupiah (IDR)',
                    'USD' => 'US Dollar (USD)',
                    'EUR' => 'Euro (EUR)',
                    'GBP' => 'British Pound (GBP)',
                    'JPY' => 'Japanese Yen (JPY)',
                    'CNY' => 'Chinese Yuan (CNY)',
                    'SGD' => 'Singapore Dollar (SGD)',
                    'MYR' => 'Malaysian Ringgit (MYR)',
                    'AUD' => 'Australian Dollar (AUD)',
                ],
                'default' => 'IDR',
                'required' => true,
            ],
            'favorite_currencies' => [
                'type' => 'multiselect',
                'label' => 'Mata Uang Favorit',
                'options' => [
                    'USD' => 'US Dollar',
                    'EUR' => 'Euro',
                    'GBP' => 'British Pound',
                    'JPY' => 'Japanese Yen',
                    'CNY' => 'Chinese Yuan',
                    'SGD' => 'Singapore Dollar',
                    'MYR' => 'Malaysian Ringgit',
                    'AUD' => 'Australian Dollar',
                    'SAR' => 'Saudi Riyal',
                    'AED' => 'UAE Dirham',
                ],
                'default' => ['USD', 'EUR', 'SGD'],
            ],
            'auto_update' => [
                'type' => 'boolean',
                'label' => 'Update Otomatis Harian',
                'default' => true,
            ],
            'update_time' => [
                'type' => 'time',
                'label' => 'Waktu Update',
                'default' => '09:00',
                'condition' => 'auto_update === true',
            ],
            'notification_enabled' => [
                'type' => 'boolean',
                'label' => 'Notifikasi Perubahan Signifikan',
                'default' => false,
            ],
            'threshold_percentage' => [
                'type' => 'number',
                'label' => 'Persentase Perubahan (%)',
                'default' => 5,
                'min' => 1,
                'max' => 20,
                'condition' => 'notification_enabled === true',
            ],
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            'base_currency' => 'IDR',
            'favorite_currencies' => ['USD', 'EUR', 'SGD'],
            'auto_update' => true,
            'update_time' => '09:00',
            'notification_enabled' => false,
            'threshold_percentage' => 5,
        ];
    }

    public function validateConfig(array $config): array
    {
        $errors = [];

        if (! isset($config['base_currency']) || empty($config['base_currency'])) {
            $errors['base_currency'] = 'Base currency is required';
        }

        if (isset($config['update_time']) && ! preg_match('/^\d{2}:\d{2}$/', $config['update_time'])) {
            $errors['update_time'] = 'Update time must be in HH:MM format';
        }

        if (isset($config['threshold_percentage']) && ($config['threshold_percentage'] < 1 || $config['threshold_percentage'] > 20)) {
            $errors['threshold_percentage'] = 'Threshold must be between 1 and 20';
        }

        return $errors;
    }

    public function activate(int $userId): void
    {
        $config = $this->getUserConfig($userId);

        // Schedule daily updates
        if ($config['auto_update']) {
            $this->createSchedule($userId, [
                'schedule_type' => 'daily',
                'schedule_value' => $config['update_time'],
                'metadata' => [
                    'type' => 'rate_update',
                ],
            ]);
        }

        $this->log($userId, 'info', 'Currency Converter activated');
    }

    public function deactivate(int $userId): void
    {
        $this->deleteSchedules($userId);
        $this->log($userId, 'info', 'Currency Converter deactivated');
    }

    public function execute(int $userId, array $config, array $context = []): void
    {
        try {
            $type = $context['type'] ?? 'rate_update';

            if ($type === 'rate_update') {
                $this->sendDailyRates($userId);
            } elseif ($type === 'convert') {
                $this->convertCurrency($userId, $context);
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
        return [
            [
                'action' => 'plugin_currency_convert',
                'description' => 'Konversi mata uang / cek nilai tukar',
                'entities' => [
                    'amount' => 'number|null',
                    'from_currency' => 'string|null',
                    'to_currency' => 'string|null',
                ],
                'examples' => [
                    'berapa kurs IDR ke USD sekarang',
                    'convert 100 dollar to rupiah',
                    '1000 rupiah ke dolar',
                    'berapa nilai 50 euro dalam yen',
                    'kurs SGD hari ini',
                ],
            ],
        ];
    }

    public function handleChatIntent(int $userId, string $action, array $entities): array
    {
        if ($action !== 'plugin_currency_convert') {
            return [
                'success' => false,
                'message' => 'Action not supported',
            ];
        }

        $config = $this->getUserConfig($userId);

        $amount = $entities['amount'] ?? 1;
        $from = strtoupper($entities['from_currency'] ?? $entities['from'] ?? $config['base_currency'] ?? 'IDR');
        $to = strtoupper($entities['to_currency'] ?? $entities['to'] ?? 'USD');

        $result = $this->convertCurrency($userId, [
            'amount' => $amount,
            'from' => $from,
            'to' => $to,
        ]);

        if ($result['success']) {
            $message = sprintf(
                "💱 **Konversi Mata Uang**\n\n%s %s = **%s %s**\n\nNilai tukar: 1 %s = %s %s\n\n_Data realtime dari ExchangeRate-API_",
                number_format($result['amount'], 2, ',', '.'),
                $result['from'],
                number_format($result['result'], 2, ',', '.'),
                $result['to'],
                $result['from'],
                number_format($result['rate'], 4),
                $result['to']
            );

            return [
                'success' => true,
                'message' => $message,
                'data' => $result,
            ];
        }

        return [
            'success' => false,
            'message' => '❌ Maaf, gagal mengambil data kurs. Silakan coba lagi nanti.',
        ];
    }

    public function convertCurrency(int $userId, array $context): array
    {
        $config = $this->getUserConfig($userId);

        $amount = $context['amount'] ?? 1;
        $from = $context['from'] ?? $config['base_currency'];
        $to = $context['to'] ?? 'USD';

        try {
            $response = Http::timeout(10)
                ->withOptions([
                    'verify' => config('app.env') === 'production',
                ])
                ->get(self::API_URL.$from);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['rates'][$to])) {
                    $rate = $data['rates'][$to];
                    $result = $amount * $rate;

                    $this->log($userId, 'info', "Converted {$amount} {$from} to {$result} {$to}");

                    return [
                        'success' => true,
                        'amount' => $amount,
                        'from' => $from,
                        'to' => $to,
                        'rate' => $rate,
                        'result' => round($result, 2),
                        'timestamp' => now()->toIso8601String(),
                    ];
                }
            }

            throw new \Exception('Failed to fetch exchange rates');
        } catch (\Exception $e) {
            $this->log($userId, 'error', 'Conversion failed: '.$e->getMessage(), [
                'from' => $from,
                'to' => $to,
                'amount' => $amount,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function sendDailyRates(int $userId): void
    {
        $config = $this->getUserConfig($userId);
        $baseCurrency = $config['base_currency'];
        $favorites = $config['favorite_currencies'];

        try {
            $response = Http::timeout(10)
                ->withOptions([
                    'verify' => config('app.env') === 'production',
                ])
                ->get(self::API_URL.$baseCurrency);

            if ($response->successful()) {
                $data = $response->json();
                $rates = $data['rates'];

                $message = "💱 *Kurs Mata Uang Hari Ini*\n\n";
                $message .= "Nilai Tukar: 1 {$baseCurrency}\n\n";

                foreach ($favorites as $currency) {
                    if (isset($rates[$currency])) {
                        $rate = $rates[$currency];
                        $message .= "• {$currency}: ".number_format($rate, 4)."\n";
                    }
                }

                $message .= "\n_Update: ".now()->format('d M Y H:i').'_';

                // Send via Telegram if service available
                if (app()->bound('telegram')) {
                    app('telegram')->sendMessage($userId, $message);
                }

                $this->log($userId, 'info', 'Daily rates sent');
            }
        } catch (\Exception $e) {
            $this->log($userId, 'error', 'Failed to send daily rates: '.$e->getMessage());
        }
    }

    public function handleIntent(int $userId, array $intent): ?array
    {
        $action = $intent['action'] ?? '';
        $entities = $intent['entities'] ?? [];

        if (in_array($action, ['convert_currency', 'exchange_rate'])) {
            $amount = $entities['amount'] ?? 1;
            $from = strtoupper($entities['from_currency'] ?? 'IDR');
            $to = strtoupper($entities['to_currency'] ?? 'USD');

            $result = $this->convertCurrency($userId, [
                'amount' => $amount,
                'from' => $from,
                'to' => $to,
            ]);

            if ($result['success']) {
                return [
                    'response' => sprintf(
                        "💱 %s %s = %s %s\n\nNilai tukar: 1 %s = %s %s\n\n_Data realtime dari ExchangeRate-API_",
                        number_format($result['amount'], 2),
                        $result['from'],
                        number_format($result['result'], 2),
                        $result['to'],
                        $result['from'],
                        number_format($result['rate'], 4),
                        $result['to']
                    ),
                    'data' => $result,
                ];
            }

            return [
                'response' => '❌ Maaf, gagal mengambil data kurs. Coba lagi nanti.',
                'error' => $result['error'],
            ];
        }

        return null;
    }
}
