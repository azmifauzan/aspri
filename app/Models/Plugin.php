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
     * Get all ratings for this plugin.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(PluginRating::class);
    }

    /**
     * Check if plugin is installed.
     */
    public function isInstalled(): bool
    {
        return $this->installed_at !== null;
    }

    /**
     * Get the average rating of the plugin.
     */
    public function getAverageRatingAttribute(): float
    {
        return $this->ratings()->avg('rating') ?? 0;
    }

    /**
     * Get the total number of ratings.
     */
    public function getTotalRatingsAttribute(): int
    {
        return $this->ratings()->count();
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
