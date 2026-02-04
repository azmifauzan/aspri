<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatUsageLog extends Model
{
    protected $fillable = [
        'user_id',
        'usage_date',
        'response_count',
    ];

    protected function casts(): array
    {
        return [
            'usage_date' => 'date',
            'response_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get or create today's usage log for a user.
     */
    public static function getOrCreateToday(int $userId): self
    {
        $log = static::where('user_id', $userId)
            ->whereDate('usage_date', now()->toDateString())
            ->first();

        if (! $log) {
            $log = static::create([
                'user_id' => $userId,
                'usage_date' => now()->toDateString(),
                'response_count' => 0,
            ]);
        }

        return $log;
    }

    /**
     * Increment the response count for today.
     */
    public static function incrementForUser(int $userId): void
    {
        $log = static::getOrCreateToday($userId);
        $log->increment('response_count');
    }

    /**
     * Get today's usage count for a user.
     */
    public static function getTodayCount(int $userId): int
    {
        return static::where('user_id', $userId)
            ->whereDate('usage_date', now()->toDateString())
            ->value('response_count') ?? 0;
    }
}
