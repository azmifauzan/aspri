<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceCategory extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'tx_type',
        'icon',
        'color',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class, 'category_id');
    }

    /**
     * Scope for income categories
     */
    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('tx_type', 'income');
    }

    /**
     * Scope for expense categories
     */
    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('tx_type', 'expense');
    }
}
