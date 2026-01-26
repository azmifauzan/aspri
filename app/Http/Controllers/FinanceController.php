<?php

namespace App\Http\Controllers;

use App\Models\FinanceAccount;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use Carbon\Carbon;
use Database\Seeders\FinanceCategorySeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    /**
     * Finance overview page
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Ensure user has default categories
        $this->ensureDefaultCategories($user);

        // Get monthly summary
        $monthlySummary = $this->getMonthlySummary($user);

        // Get recent transactions
        $recentTransactions = FinanceTransaction::where('user_id', $user->id)
            ->with(['category', 'account'])
            ->orderBy('occurred_at', 'desc')
            ->limit(10)
            ->get();

        // Get accounts with balances
        $accounts = FinanceAccount::where('user_id', $user->id)->get();

        // Get weekly expenses for chart
        $weeklyExpenses = $this->getWeeklyExpenses($user);

        // Get categories for add transaction modal
        $categories = FinanceCategory::where('user_id', $user->id)->get();

        return Inertia::render('finance/Index', [
            'monthlySummary' => $monthlySummary,
            'recentTransactions' => $recentTransactions,
            'accounts' => $accounts,
            'weeklyExpenses' => $weeklyExpenses,
            'categories' => $categories,
        ]);

    }

    /**
     * Transaction list page
     */
    public function transactions(Request $request): Response
    {
        $user = $request->user();

        $query = FinanceTransaction::where('user_id', $user->id)
            ->with(['category', 'account']);

        // Filter by type
        if ($request->has('type') && in_array($request->type, ['income', 'expense'])) {
            $query->where('tx_type', $request->type);
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category_id', $request->category);
        }

        // Filter by date range
        if ($request->has('from')) {
            $query->where('occurred_at', '>=', Carbon::parse($request->from)->startOfDay());
        }
        if ($request->has('to')) {
            $query->where('occurred_at', '<=', Carbon::parse($request->to)->endOfDay());
        }

        // Search
        if ($request->has('search') && $request->search) {
            $query->where('note', 'ilike', '%'.$request->search.'%');
        }

        $transactions = $query->orderBy('occurred_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $categories = FinanceCategory::where('user_id', $user->id)->get();
        $accounts = FinanceAccount::where('user_id', $user->id)->get();

        return Inertia::render('finance/Transactions', [
            'transactions' => $transactions,
            'categories' => $categories,
            'accounts' => $accounts,
            'filters' => $request->only(['type', 'category', 'from', 'to', 'search']),
        ]);
    }

    /**
     * Store a new transaction
     */
    public function storeTransaction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tx_type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0',
            'category_id' => 'nullable|uuid|exists:finance_categories,id',
            'account_id' => 'nullable|uuid|exists:finance_accounts,id',
            'occurred_at' => 'required|date',
            'note' => 'nullable|string|max:500',
        ]);

        $request->user()->financeTransactions()->create($validated);

        return back()->with('success', 'Transaksi berhasil ditambahkan');
    }

    /**
     * Update a transaction
     */
    public function updateTransaction(Request $request, FinanceTransaction $transaction): RedirectResponse
    {
        // Ensure user owns the transaction
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'tx_type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0',
            'category_id' => 'nullable|uuid|exists:finance_categories,id',
            'account_id' => 'nullable|uuid|exists:finance_accounts,id',
            'occurred_at' => 'required|date',
            'note' => 'nullable|string|max:500',
        ]);

        $transaction->update($validated);

        return back()->with('success', 'Transaksi berhasil diperbarui');
    }

    /**
     * Delete a transaction
     */
    public function destroyTransaction(Request $request, FinanceTransaction $transaction): RedirectResponse
    {
        // Ensure user owns the transaction
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        $transaction->delete();

        return back()->with('success', 'Transaksi berhasil dihapus');
    }

    /**
     * Categories management page
     */
    public function categories(Request $request): Response
    {
        $user = $request->user();

        $this->ensureDefaultCategories($user);

        $categories = FinanceCategory::where('user_id', $user->id)
            ->withCount('transactions')
            ->orderBy('tx_type')
            ->orderBy('name')
            ->get();

        return Inertia::render('finance/Categories', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a new category
     */
    /**
     * Store a new category
     */
    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'tx_type' => 'required|in:income,expense',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
        ]);

        // Check for duplicate
        $exists = FinanceCategory::where('user_id', $request->user()->id)
            ->where('tx_type', $validated['tx_type'])
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'Kategori dengan nama ini sudah ada']);
        }

        $request->user()->financeCategories()->create($validated);

        return back()->with('success', 'Kategori berhasil ditambahkan');
    }

    /**
     * Update a category
     */
    public function updateCategory(Request $request, FinanceCategory $category): RedirectResponse

    {
        if ($category->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'tx_type' => 'required|in:income,expense',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
        ]);

        // Check for duplicate excluding self
        $exists = FinanceCategory::where('user_id', $request->user()->id)
            ->where('tx_type', $validated['tx_type'])
            ->where('name', $validated['name'])
            ->where('id', '!=', $category->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'Kategori dengan nama ini sudah ada']);
        }

        $category->update($validated);

        return back()->with('success', 'Kategori berhasil diperbarui');
    }

    /**
     * Delete a category
     */
    public function destroyCategory(Request $request, FinanceCategory $category): RedirectResponse
    {
        if ($category->user_id !== $request->user()->id) {
            abort(403);
        }

        $category->delete();

        return back()->with('success', 'Kategori berhasil dihapus');
    }


    /**
     * Accounts management page
     */
    public function accounts(Request $request): Response
    {
        $user = $request->user();

        $accounts = FinanceAccount::where('user_id', $user->id)
            ->withSum(['transactions as income_total' => fn ($q) => $q->where('tx_type', 'income')], 'amount')
            ->withSum(['transactions as expense_total' => fn ($q) => $q->where('tx_type', 'expense')], 'amount')
            ->get()
            ->map(function ($account) {
                $account->current_balance = $account->initial_balance + ($account->income_total ?? 0) - ($account->expense_total ?? 0);

                return $account;
            });

        return Inertia::render('finance/Accounts', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * Store a new account
     */
    public function storeAccount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:cash,bank,e-wallet',
            'initial_balance' => 'nullable|numeric|min:0',
        ]);

        // Check for duplicate
        $exists = FinanceAccount::where('user_id', $request->user()->id)
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'Akun dengan nama ini sudah ada']);
        }

        $request->user()->financeAccounts()->create($validated);

        return back()->with('success', 'Akun berhasil ditambahkan');
    }

    /**
     * Get monthly summary for a user
     */
    protected function getMonthlySummary($user): array
    {
        $thisMonth = FinanceTransaction::where('user_id', $user->id)
            ->thisMonth();

        $income = (float) (clone $thisMonth)->income()->sum('amount');
        $expense = (float) (clone $thisMonth)->expense()->sum('amount');

        // Get last month for comparison
        $lastMonth = FinanceTransaction::where('user_id', $user->id)
            ->whereMonth('occurred_at', Carbon::now()->subMonth()->month)
            ->whereYear('occurred_at', Carbon::now()->subMonth()->year);

        $lastIncome = (float) (clone $lastMonth)->income()->sum('amount');
        $lastExpense = (float) (clone $lastMonth)->expense()->sum('amount');

        return [
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
            'incomeChange' => $lastIncome > 0 ? round((($income - $lastIncome) / $lastIncome) * 100, 1) : 0,
            'expenseChange' => $lastExpense > 0 ? round((($expense - $lastExpense) / $lastExpense) * 100, 1) : 0,
        ];
    }

    /**
     * Get weekly expenses for chart
     */
    protected function getWeeklyExpenses($user): array
    {
        $days = collect();
        $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $amount = FinanceTransaction::where('user_id', $user->id)
                ->expense()
                ->whereDate('occurred_at', $date)
                ->sum('amount');

            $days->push([
                'day' => $dayNames[$date->dayOfWeek],
                'amount' => (float) $amount,
            ]);
        }

        return $days->toArray();
    }

    /**
     * Ensure user has default categories
     */
    protected function ensureDefaultCategories($user): void
    {
        $hasCategories = FinanceCategory::where('user_id', $user->id)->exists();

        if (! $hasCategories) {
            $seeder = new FinanceCategorySeeder;
            $seeder->createCategoriesForUser($user);
        }
    }
}
