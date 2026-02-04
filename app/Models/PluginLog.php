<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PluginLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'plugin_id',
        'user_id',
        'level',
        'message',
        'context',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the plugin that owns this log.
     */
    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }

    /**
     * Get the user associated with this log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create an info log.
     *
     * @param  array<string, mixed>  $context
     */
    public static function info(int $pluginId, string $message, ?int $userId = null, array $context = []): self
    {
        return self::createLog($pluginId, 'info', $message, $userId, $context);
    }

    /**
     * Create a warning log.
     *
     * @param  array<string, mixed>  $context
     */
    public static function warning(int $pluginId, string $message, ?int $userId = null, array $context = []): self
    {
        return self::createLog($pluginId, 'warning', $message, $userId, $context);
    }

    /**
     * Create an error log.
     *
     * @param  array<string, mixed>  $context
     */
    public static function error(int $pluginId, string $message, ?int $userId = null, array $context = []): self
    {
        return self::createLog($pluginId, 'error', $message, $userId, $context);
    }

    /**
     * Create a debug log.
     *
     * @param  array<string, mixed>  $context
     */
    public static function debug(int $pluginId, string $message, ?int $userId = null, array $context = []): self
    {
        return self::createLog($pluginId, 'debug', $message, $userId, $context);
    }

    /**
     * Create a log entry.
     *
     * @param  array<string, mixed>  $context
     */
    protected static function createLog(int $pluginId, string $level, string $message, ?int $userId, array $context): self
    {
        return self::create([
            'plugin_id' => $pluginId,
            'user_id' => $userId,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'created_at' => now(),
        ]);
    }
}
