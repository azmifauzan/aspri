<?php

namespace App\Http\Controllers;

use App\Models\ChatUsageLog;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        $monthlyFinance = [
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
            'incomeChange' => $lastIncome > 0 ? round((($income - $lastIncome) / $lastIncome) * 100, 1) : 0,
            'expenseChange' => $lastExpense > 0 ? round((($expense - $lastExpense) / $lastExpense) * 100, 1) : 0,
        ];

        // Today's Events
        $todaySchedule = $user->schedules()
            ->whereDate('start_time', \Carbon\Carbon::today())
            ->orderBy('start_time')
            ->get()
            ->map(function ($schedule) {
                return [
                    'id' => (string) $schedule->id,
                    'title' => $schedule->title,
                    'time' => $schedule->start_time->format('H:i'),
                    'endTime' => $schedule->end_time->format('H:i'),
                    'type' => 'personal', // Default type or logic based on title
                ];
            });

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
                    'description' => 'Rp '.number_format($tx->amount, 0, ',', '.'),
                    'time' => $tx->occurred_at->diffForHumans(),
                    'icon' => $tx->category?->icon ?? ($tx->tx_type === 'income' ? 'trending-up' : 'wallet'),
                ];
            });

        // Merge with dummy events/notes until those modules exist
        $recentActivities = $recentTransactions->toArray();

        // Subscription info
        $subscriptionInfo = $user->getSubscriptionInfo();
        $chatLimit = [
            'used' => ChatUsageLog::getTodayCount($user->id),
            'limit' => $user->getDailyChatLimit(),
            'remaining' => $user->getRemainingChats(),
        ];

        // Telegram integration status
        $isLinked = ! empty($user->telegram_chat_id);
        $linkCode = null;
        if (! $isLinked) {
            // Check for existing unexpired link code
            if ($user->telegram_link_code && $user->telegram_link_expires_at && $user->telegram_link_expires_at > now()) {
                $linkCode = $user->telegram_link_code;
            } else {
                // Generate new code
                $linkCode = strtoupper(Str::random(8));
                $user->update([
                    'telegram_link_code' => $linkCode,
                    'telegram_link_expires_at' => now()->addHours(24),
                ]);
            }
        }

        $telegramInfo = [
            'isLinked' => $isLinked,
            'username' => $user->telegram_username,
            'linkCode' => $linkCode,
            'botUsername' => SystemSetting::getValue('telegram_bot_username'),
        ];

        // DEBUG
        logger()->info('Dashboard Data', [
            'user_id' => $user->id,
            'subscriptionInfo' => $subscriptionInfo,
            'chatLimit' => $chatLimit,
            'telegramInfo' => $telegramInfo,
        ]);

        return Inertia::render('Dashboard', [
            'monthlySummary' => $monthlyFinance,
            'todayEvents' => $todaySchedule,
            'weeklyExpenses' => $weeklyExpenses,
            'recentActivities' => $recentActivities,
            'subscriptionInfo' => $subscriptionInfo,
            'chatLimit' => $chatLimit,
            'telegramInfo' => $telegramInfo,
        ]);
    }
}
