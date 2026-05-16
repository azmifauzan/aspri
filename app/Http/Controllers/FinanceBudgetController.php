<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBudgetRequest;
use App\Http\Requests\UpdateBudgetRequest;
use App\Models\FinanceBudget;
use App\Models\FinanceCategory;
use App\Services\Finance\FinanceBudgetService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceBudgetController extends Controller
{
    public function __construct(protected FinanceBudgetService $budgetService) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $now = Carbon::now();
        $year = (int) $request->get('year', $now->year);
        $month = (int) $request->get('month', $now->month);

        $budgetsWithProgress = $this->budgetService->getProgressForUserPeriod($user, $year, $month);

        $categories = FinanceCategory::where('user_id', $user->id)
            ->expense()
            ->orderBy('name')
            ->get(['id', 'name', 'icon', 'color']);

        return Inertia::render('finance/Budgets', [
            'budgets' => $budgetsWithProgress,
            'categories' => $categories,
            'period' => ['year' => $year, 'month' => $month],
        ]);
    }

    public function store(StoreBudgetRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        FinanceBudget::create([
            'user_id' => $user->id,
            'category_id' => $data['category_id'] ?? null,
            'period_year' => $data['period_year'],
            'period_month' => $data['period_month'],
            'amount' => $data['amount'],
            'alert_threshold_pct' => $data['alert_threshold_pct'] ?? 80,
        ]);

        return back()->with('success', 'Budget berhasil dibuat.');
    }

    public function update(UpdateBudgetRequest $request, FinanceBudget $financeBudget): RedirectResponse
    {
        $this->authorizeOwner($financeBudget, $request);

        $data = $request->validated();

        $financeBudget->update([
            'category_id' => $data['category_id'] ?? $financeBudget->category_id,
            'amount' => $data['amount'],
            'alert_threshold_pct' => $data['alert_threshold_pct'] ?? $financeBudget->alert_threshold_pct,
        ]);

        return back()->with('success', 'Budget berhasil diperbarui.');
    }

    public function destroy(Request $request, FinanceBudget $financeBudget): RedirectResponse
    {
        $this->authorizeOwner($financeBudget, $request);
        $financeBudget->delete();

        return back()->with('success', 'Budget berhasil dihapus.');
    }

    private function authorizeOwner(FinanceBudget $budget, Request $request): void
    {
        abort_if($budget->user_id !== $request->user()->id, 403);
    }
}
