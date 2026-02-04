<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plugin extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'version',
        'author',
        'icon',
        'class_name',
        'is_system',
        'config_schema',
        'default_config',
        'installed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'config_schema' => 'array',
            'default_config' => 'array',
            'installed_at' => 'datetime',
        ];
    }

    /**
     * Get all user plugins for this plugin.
     */
    public function userPlugins(): HasMany
    {
        return $this->hasMany(UserPlugin::class);
    }

    /**
     * Get all logs for this plugin.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(PluginLog::class);
    }

    /**
     * Check if plugin is installed.
     */
    public function isInstalled(): bool
    {
        return $this->installed_at !== null;
    }

    /**
     * Get the count of active users.
     */
    public function getActiveUsersCountAttribute(): int
    {
        return $this->userPlugins()->where('is_active', true)->count();
    }

    /**
     * Get plugin instance.
     */
    public function getInstance(): ?\App\Services\Plugin\Contracts\PluginInterface
    {
        if (! class_exists($this->class_name)) {
            return null;
        }

        return app($this->class_name);
    }
}
