<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceAccount extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'currency',
        'initial_balance',
    ];

    protected $casts = [
        'initial_balance' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class, 'account_id');
    }

    /**
     * Get the current balance (initial + all transactions)
     */
    public function getCurrentBalanceAttribute(): float
    {
        $income = $this->transactions()->where('tx_type', 'income')->sum('amount');
        $expense = $this->transactions()->where('tx_type', 'expense')->sum('amount');

        return (float) $this->initial_balance + $income - $expense;
    }
}
