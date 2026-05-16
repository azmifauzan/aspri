<?php

namespace App\Services\Finance;

use App\Models\FinanceBudget;
use App\Models\FinanceTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FinanceBudgetService
{
    /**
     * Get progress summary for a single budget.
     *
     * @return array{spent: float, remaining: float, used_pct: float, is_over: bool, is_approaching: bool}
     */
    public function getProgress(FinanceBudget $budget): array
    {
        $spent = $this->calculateSpent($budget);
        $amount = (float) $budget->amount;
        $remaining = $amount - $spent;
        $usedPct = $amount > 0 ? round(($spent / $amount) * 100, 2) : 0.0;

        return [
            'spent' => $spent,
            'remaining' => $remaining,
            'used_pct' => $usedPct,
            'is_over' => $spent > $amount,
            'is_approaching' => $usedPct >= $budget->alert_threshold_pct && $spent <= $amount,
        ];
    }

    /**
     * Get all active budgets for a user in a given period with computed progress.
     */
    public function getProgressForUserPeriod(User $user, int $year, int $month): Collection
    {
        $budgets = FinanceBudget::where('user_id', $user->id)
            ->active()
            ->forPeriod($year, $month)
            ->with('category')
            ->get();

        return $budgets->map(fn (FinanceBudget $b) => array_merge(
            $b->toArray(),
            $this->getProgress($b),
        ));
    }

    /**
     * Calculate total expense for a budget's period (and category, if set).
     */
    public function calculateSpent(FinanceBudget $budget): float
    {
        $start = Carbon::create($budget->period_year, $budget->period_month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $query = FinanceTransaction::where('user_id', $budget->user_id)
            ->where('tx_type', 'expense')
            ->whereBetween('occurred_at', [$start, $end]);

        if ($budget->category_id) {
            $query->where('category_id', $budget->category_id);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Check if budget is over its limit.
     */
    public function isOverBudget(FinanceBudget $budget): bool
    {
        return $this->getProgress($budget)['is_over'];
    }

    /**
     * Check if budget usage has crossed the alert threshold.
     */
    public function isApproachingLimit(FinanceBudget $budget): bool
    {
        return $this->getProgress($budget)['is_approaching'];
    }
}
