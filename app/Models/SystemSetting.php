<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'is_encrypted',
        'group',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    /**
     * Get a setting value by key.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $cacheKey = "system_setting_{$key}";

        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            if (! $setting) {
                return $default;
            }

            $value = $setting->is_encrypted
                ? decrypt($setting->value)
                : $setting->value;

            return match ($setting->type) {
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'json' => json_decode($value, true),
                'integer' => (int) $value,
                default => $value,
            };
        });
    }

    /**
     * Set a setting value.
     */
    public static function setValue(string $key, mixed $value, array $options = []): void
    {
        $type = $options['type'] ?? 'string';
        $encrypted = $options['encrypted'] ?? false;
        $group = $options['group'] ?? null;
        $description = $options['description'] ?? null;

        $storeValue = match ($type) {
            'json' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            'integer' => (string) $value,
            default => (string) $value,
        };

        if ($encrypted) {
            $storeValue = encrypt($storeValue);
        }

        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $storeValue,
                'type' => $type,
                'is_encrypted' => $encrypted,
                'group' => $group,
                'description' => $description,
            ]
        );

        Cache::forget("system_setting_{$key}");
    }

    /**
     * Get all settings by group.
     */
    public static function getByGroup(string $group): array
    {
        return static::where('group', $group)
            ->get()
            ->mapWithKeys(fn ($setting) => [$setting->key => static::getValue($setting->key)])
            ->toArray();
    }
}
