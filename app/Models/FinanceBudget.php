<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceBudget extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'period_year',
        'period_month',
        'amount',
        'alert_threshold_pct',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'alert_threshold_pct' => 'integer',
        'period_year' => 'integer',
        'period_month' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForPeriod(Builder $query, int $year, int $month): Builder
    {
        return $query->where('period_year', $year)->where('period_month', $month);
    }
}
