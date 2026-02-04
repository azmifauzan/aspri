<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserPlugin extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plugin_id',
        'is_active',
        'activated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this plugin installation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the plugin.
     */
    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }

    /**
     * Get all configurations for this user plugin.
     */
    public function configurations(): HasMany
    {
        return $this->hasMany(PluginConfiguration::class);
    }

    /**
     * Get all schedules for this user plugin.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(PluginSchedule::class);
    }

    /**
     * Get the active schedule for this user plugin.
     */
    public function activeSchedule(): HasOne
    {
        return $this->hasOne(PluginSchedule::class)->where('is_active', true);
    }

    /**
     * Get configuration value by key.
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        $config = $this->configurations()->where('config_key', $key)->first();

        return $config?->config_value ?? $default;
    }

    /**
     * Set configuration value.
     */
    public function setConfig(string $key, mixed $value): PluginConfiguration
    {
        return $this->configurations()->updateOrCreate(
            ['config_key' => $key],
            ['config_value' => $value]
        );
    }

    /**
     * Get all configuration as array.
     *
     * @return array<string, mixed>
     */
    public function getAllConfig(): array
    {
        $configs = $this->configurations()->get();

        return $configs->pluck('config_value', 'config_key')->toArray();
    }

    /**
     * Activate this user plugin.
     */
    public function activate(): bool
    {
        return $this->update([
            'is_active' => true,
            'activated_at' => now(),
        ]);
    }

    /**
     * Deactivate this user plugin.
     */
    public function deactivate(): bool
    {
        // Also deactivate schedules
        $this->schedules()->update(['is_active' => false]);

        return $this->update([
            'is_active' => false,
        ]);
    }
}
