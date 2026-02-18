<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    /** @use HasFactory<\Database\Factories\PromoCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'duration_days',
        'max_usages',
        'usage_count',
        'is_active',
        'expires_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'max_usages' => 'integer',
            'usage_count' => 'integer',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoCodeRedemption::class);
    }

    public function isValid(): bool
    {
        return $this->is_active
            && $this->expires_at->isFuture()
            && $this->usage_count < $this->max_usages;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isFullyRedeemed(): bool
    {
        return $this->usage_count >= $this->max_usages;
    }

    public function hasBeenRedeemedBy(User $user): bool
    {
        return $this->redemptions()->where('user_id', $user->id)->exists();
    }

    public function getRemainingUsagesAttribute(): int
    {
        return max(0, $this->max_usages - $this->usage_count);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeValid($query)
    {
        return $query->where('is_active', true)
            ->where('expires_at', '>', now())
            ->whereColumn('usage_count', '<', 'max_usages');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }
}
