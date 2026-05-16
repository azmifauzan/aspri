<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventReminder extends Model
{
    use HasFactory;

    public const CHANNEL_APP = 'app';

    public const CHANNEL_TELEGRAM = 'telegram';

    public const CHANNEL_BOTH = 'both';

    protected $fillable = [
        'schedule_id',
        'user_id',
        'minutes_before',
        'channel',
        'scheduled_for',
        'is_sent',
        'sent_at',
        'error',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
        'is_sent' => 'boolean',
        'minutes_before' => 'integer',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('is_sent', false);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('is_sent', false)
            ->where('scheduled_for', '<=', now());
    }

    public function markSent(): void
    {
        $this->update([
            'is_sent' => true,
            'sent_at' => now(),
            'error' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update(['error' => $error]);
    }
}
