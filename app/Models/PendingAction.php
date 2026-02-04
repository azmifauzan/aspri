<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingAction extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'thread_id',
        'action_type',
        'module',
        'payload',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }

    /**
     * Scope for pending actions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '>', now());
    }

    /**
     * Check if action is still valid
     */
    public function isValid(): bool
    {
        return $this->status === 'pending' && $this->expires_at > now();
    }

    /**
     * Mark action as confirmed
     */
    public function confirm(): void
    {
        $this->update(['status' => 'confirmed']);
    }

    /**
     * Mark action as cancelled
     */
    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }
}
