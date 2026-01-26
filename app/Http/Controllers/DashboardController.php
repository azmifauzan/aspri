<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Monthly Summary
        $thisMonth = $user->financeTransactions()->thisMonth();
        $income = (float) (clone $thisMonth)->income()->sum('amount');
        $expense = (float) (clone $thisMonth)->expense()->sum('amount');
        
        $lastMonth = $user->financeTransactions()
            ->whereMonth('occurred_at', \Carbon\Carbon::now()->subMonth()->month)
            ->whereYear('occurred_at', \Carbon\Carbon::now()->subMonth()->year);
        $lastIncome = (float) (clone $lastMonth)->income()->sum('amount');
        $lastExpense = (float) (clone $lastMonth)->expense()->sum('amount');

        $monthlySummary = [
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
            'incomeChange' => $lastIncome > 0 ? round((($income - $lastIncome) / $lastIncome) * 100, 1) : 0,
            'expenseChange' => $lastExpense > 0 ? round((($expense - $lastExpense) / $lastExpense) * 100, 1) : 0,
        ];

        // Today's Events (Still dummy - Schedule module next)
        $todayEvents = [
            [
                'id' => '1',
                'title' => 'Meeting dengan Tim',
                'time' => '09:00',
                'endTime' => '10:00',
                'type' => 'meeting',
            ],
            // ... kept minimal as placeholder
        ];

        // Weekly Expenses
        $weeklyExpenses = [];
        $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        for ($i = 6; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subDays($i);
            $amount = $user->financeTransactions()
                ->expense()
                ->whereDate('occurred_at', $date)
                ->sum('amount');
            
            $weeklyExpenses[] = [
                'day' => $dayNames[$date->dayOfWeek],
                'amount' => (float) $amount,
            ];
        }

        // Recent Activities
        $recentTransactions = $user->financeTransactions()
            ->with(['category'])
            ->orderBy('occurred_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'type' => $tx->tx_type, // 'income' or 'expense'
                    'title' => $tx->category?->name ?? ($tx->tx_type === 'income' ? 'Pemasukan' : 'Pengeluaran'),
                    'description' => 'Rp ' . number_format($tx->amount, 0, ',', '.'),
                    'time' => $tx->occurred_at->diffForHumans(),
                    'icon' => $tx->category?->icon ?? ($tx->tx_type === 'income' ? 'trending-up' : 'wallet'),
                ];
            });
            
        // Merge with dummy events/notes until those modules exist
        $recentActivities = $recentTransactions->toArray();

        return Inertia::render('Dashboard', [
            'monthlySummary' => $monthlySummary,
            'todayEvents' => $todayEvents,
            'weeklyExpenses' => $weeklyExpenses,
            'recentActivities' => $recentActivities,
        ]);
    }
}
