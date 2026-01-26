<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'account_id',
        'category_id',
        'tx_type',
        'amount',
        'occurred_at',
        'note',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'category_id');
    }

    /**
     * Scope for income transactions
     */
    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('tx_type', 'income');
    }

    /**
     * Scope for expense transactions
     */
    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('tx_type', 'expense');
    }

    /**
     * Scope for this month's transactions
     */
    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('occurred_at', Carbon::now()->month)
            ->whereYear('occurred_at', Carbon::now()->year);
    }

    /**
     * Scope for this week's transactions
     */
    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('occurred_at', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek(),
        ]);
    }

    /**
     * Scope for last N days
     */
    public function scopeLastDays(Builder $query, int $days = 7): Builder
    {
        return $query->where('occurred_at', '>=', Carbon::now()->subDays($days)->startOfDay());
    }
}
