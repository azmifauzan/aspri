<?php

namespace App\Plugins\WeatherForecast;

use App\Services\Plugin\BasePlugin;
use Illuminate\Support\Facades\Http;

class WeatherForecastPlugin extends BasePlugin
{
    private const API_URL = 'https://api.open-meteo.com/v1/forecast';

    public function getName(): string
    {
        return 'Weather Forecast';
    }

    public function getSlug(): string
    {
        return 'weather-forecast';
    }

    public function getDescription(): string
    {
        return 'Perkiraan cuaca akurat untuk lokasi manapun di dunia. Mendukung prakiraan 7 hari ke depan dengan detail lengkap.';
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
        return 'cloud-sun';
    }

    public function getConfigSchema(): array
    {
        return [
            'default_location' => [
                'type' => 'text',
                'label' => 'Lokasi Default',
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
            'morning_forecast' => [
                'type' => 'boolean',
                'label' => 'Prakiraan Pagi Hari',
                'default' => true,
            ],
            'forecast_time' => [
                'type' => 'time',
                'label' => 'Waktu Pengiriman',
                'default' => '06:00',
                'condition' => 'morning_forecast === true',
            ],
            'include_hourly' => [
                'type' => 'boolean',
                'label' => 'Prakiraan Per Jam',
                'default' => false,
            ],
            'rain_alert' => [
                'type' => 'boolean',
                'label' => 'Alert Hujan',
                'default' => true,
            ],
            'temperature_unit' => [
                'type' => 'select',
                'label' => 'Satuan Suhu',
                'options' => [
                    'celsius' => 'Celsius (°C)',
                    'fahrenheit' => 'Fahrenheit (°F)',
                ],
                'default' => 'celsius',
            ],
            'wind_speed_unit' => [
                'type' => 'select',
                'label' => 'Satuan Kecepatan Angin',
                'options' => [
                    'kmh' => 'Km/h',
                    'ms' => 'm/s',
                    'mph' => 'mph',
                ],
                'default' => 'kmh',
            ],
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            'default_location' => 'Jakarta, Indonesia',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'morning_forecast' => true,
            'forecast_time' => '06:00',
            'include_hourly' => false,
            'rain_alert' => true,
            'temperature_unit' => 'celsius',
            'wind_speed_unit' => 'kmh',
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

        if (isset($config['forecast_time']) && ! preg_match('/^\d{2}:\d{2}$/', $config['forecast_time'])) {
            $errors['forecast_time'] = 'Forecast time must be in HH:MM format';
        }

        return $errors;
    }

    public function activate(int $userId): void
    {
        $config = $this->getUserConfig($userId);

        // Schedule morning forecast
        if ($config['morning_forecast']) {
            $this->createSchedule($userId, [
                'schedule_type' => 'daily',
                'schedule_value' => $config['forecast_time'],
                'metadata' => [
                    'type' => 'morning_forecast',
                ],
            ]);
        }

        $this->log($userId, 'info', 'Weather Forecast activated');
    }

    public function deactivate(int $userId): void
    {
        $this->deleteSchedules($userId);
        $this->log($userId, 'info', 'Weather Forecast deactivated');
    }

    public function execute(int $userId, array $config, array $context = []): void
    {
        try {
            $type = $context['type'] ?? 'morning_forecast';

            if ($type === 'morning_forecast') {
                $this->sendMorningForecast($userId);
            } elseif ($type === 'check_weather') {
                $this->checkWeather($userId, $context);
            }

            $this->log($userId, 'info', "Executed: {$type}");
        } catch (\Exception $e) {
            $this->log($userId, 'error', 'Execution failed: '.$e->getMessage());
        }
    }

    public function checkWeather(int $userId, array $context = []): array
    {
        $config = $this->getUserConfig($userId);

        $latitude = $context['latitude'] ?? $config['latitude'];
        $longitude = $context['longitude'] ?? $config['longitude'];

        try {
            $response = Http::timeout(10)->get(self::API_URL, [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,weather_code,wind_speed_10m',
                'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,precipitation_probability_max',
                'timezone' => 'auto',
                'forecast_days' => 3,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                $this->log($userId, 'info', 'Weather data fetched successfully');

                return [
                    'success' => true,
                    'location' => $config['default_location'],
                    'current' => $data['current'] ?? [],
                    'daily' => $data['daily'] ?? [],
                    'timestamp' => now()->toIso8601String(),
                ];
            }

            throw new \Exception('Failed to fetch weather data');
        } catch (\Exception $e) {
            $this->log($userId, 'error', 'Weather check failed: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function sendMorningForecast(int $userId): void
    {
        $result = $this->checkWeather($userId);

        if ($result['success']) {
            $current = $result['current'];
            $daily = $result['daily'];

            $weatherDesc = $this->getWeatherDescription($current['weather_code']);
            $temp = round($current['temperature_2m']);
            $feelsLike = round($current['apparent_temperature']);
            $humidity = $current['relative_humidity_2m'];
            $windSpeed = round($current['wind_speed_10m']);

            $message = "🌤️ *Prakiraan Cuaca Hari Ini*\n\n";
            $message .= "📍 {$result['location']}\n\n";
            $message .= "🌡️ Suhu: {$temp}°C (terasa {$feelsLike}°C)\n";
            $message .= "💧 Kelembaban: {$humidity}%\n";
            $message .= "💨 Angin: {$windSpeed} km/h\n";
            $message .= "☁️ Kondisi: {$weatherDesc}\n\n";

            // Today's forecast
            if (isset($daily['temperature_2m_max'][0])) {
                $maxTemp = round($daily['temperature_2m_max'][0]);
                $minTemp = round($daily['temperature_2m_min'][0]);
                $rainProb = $daily['precipitation_probability_max'][0] ?? 0;

                $message .= "*Hari Ini*\n";
                $message .= "📊 {$minTemp}°C - {$maxTemp}°C\n";
                $message .= "🌧️ Kemungkinan hujan: {$rainProb}%\n\n";

                if ($rainProb > 50) {
                    $message .= "☔ *Jangan lupa bawa payung!*\n\n";
                }
            }

            $message .= '_Update: '.now()->format('d M Y H:i').'_';

            // Send via Telegram if service available
            if (app()->bound('telegram')) {
                app('telegram')->sendMessage($userId, $message);
            }

            $this->log($userId, 'info', 'Morning forecast sent');
        }
    }

    private function getWeatherDescription(int $code): string
    {
        $descriptions = [
            0 => 'Cerah',
            1 => 'Sebagian Cerah',
            2 => 'Berawan Sebagian',
            3 => 'Berawan',
            45 => 'Berkabut',
            48 => 'Berkabut Tebal',
            51 => 'Gerimis Ringan',
            53 => 'Gerimis Sedang',
            55 => 'Gerimis Lebat',
            61 => 'Hujan Ringan',
            63 => 'Hujan Sedang',
            65 => 'Hujan Lebat',
            71 => 'Salju Ringan',
            73 => 'Salju Sedang',
            75 => 'Salju Lebat',
            80 => 'Hujan Shower',
            81 => 'Hujan Shower Sedang',
            82 => 'Hujan Shower Lebat',
            95 => 'Badai Petir',
            96 => 'Badai Petir dengan Hujan Es',
            99 => 'Badai Petir Lebat',
        ];

        return $descriptions[$code] ?? 'Tidak Diketahui';
    }

    public function handleIntent(int $userId, array $intent): ?array
    {
        $action = $intent['action'] ?? '';
        $entities = $intent['entities'] ?? [];

        if (in_array($action, ['check_weather', 'weather_forecast'])) {
            $result = $this->checkWeather($userId, $entities);

            if ($result['success']) {
                $current = $result['current'];
                $weatherDesc = $this->getWeatherDescription($current['weather_code']);
                $temp = round($current['temperature_2m']);
                $feelsLike = round($current['apparent_temperature']);

                return [
                    'response' => sprintf(
                        "🌤️ *Cuaca di %s*\n\n🌡️ %s°C (terasa %s°C)\n☁️ %s\n💧 Kelembaban: %s%%\n💨 Angin: %s km/h\n\n_Data dari Open-Meteo_",
                        $result['location'],
                        $temp,
                        $feelsLike,
                        $weatherDesc,
                        $current['relative_humidity_2m'],
                        round($current['wind_speed_10m'])
                    ),
                    'data' => $result,
                ];
            }

            return [
                'response' => '❌ Maaf, gagal mengambil data cuaca. Coba lagi nanti.',
                'error' => $result['error'],
            ];
        }

        return null;
    }
}
