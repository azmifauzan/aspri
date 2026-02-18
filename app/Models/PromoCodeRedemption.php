<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCodeRedemption extends Model
{
    protected $fillable = [
        'promo_code_id',
        'user_id',
        'subscription_id',
        'days_added',
        'previous_ends_at',
        'new_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'days_added' => 'integer',
            'previous_ends_at' => 'datetime',
            'new_ends_at' => 'datetime',
        ];
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
