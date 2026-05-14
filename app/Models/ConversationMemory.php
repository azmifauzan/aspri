<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMemory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'memory_type',
        'content',
        'source_thread_id',
        'importance',
        'access_count',
        'last_accessed_at',
        'valid_until',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'last_accessed_at' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'importance' => 'integer',
        'access_count' => 'integer',
    ];

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('memory_type', $type);
    }

    public function scopeMostImportant(Builder $query): Builder
    {
        return $query->orderBy('importance', 'desc')
            ->orderBy('last_accessed_at', 'desc')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Methods
     */
    public function recordAccess(): void
    {
        $this->increment('access_count');
        $this->update(['last_accessed_at' => now()]);
    }
}
