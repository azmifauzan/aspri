<?php

namespace App\Plugins\ExpenseAlert;

use App\Models\FinanceTransaction;
use App\Models\User;
use App\Services\Plugin\BasePlugin;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ExpenseAlertPlugin extends BasePlugin
{
    public function getSlug(): string
    {
        return 'expense-alert';
    }

    public function getName(): string
    {
        return 'Expense Alert';
    }

    public function getDescription(): string
    {
        return 'Plugin yang mengirimkan notifikasi otomatis ketika pengeluaran mendekati atau melebihi budget yang ditentukan.';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getIcon(): string
    {
        return 'bell-ring';
    }

    /**
     * @return array<string, array{type: string, label: string, description?: string, default?: mixed, required?: bool, options?: array<string>, multiple?: bool, min?: int, max?: int}>
     */
    public function getConfigSchema(): array
    {
        return [
            'monthly_budget' => [
                'type' => 'number',
                'label' => 'Budget Bulanan (Rp)',
                'description' => 'Total budget pengeluaran per bulan dalam Rupiah',
                'default' => 5000000,
                'required' => true,
                'min' => 100000,
            ],
            'alert_thresholds' => [
                'type' => 'multiselect',
                'label' => 'Threshold Alert',
                'description' => 'Kirim notifikasi saat mencapai persentase tertentu dari budget',
                'options' => ['50', '75', '90', '100'],
                'default' => ['75', '90', '100'],
                'required' => true,
            ],
            'daily_summary' => [
                'type' => 'boolean',
                'label' => 'Ringkasan Harian',
                'description' => 'Kirim ringkasan pengeluaran setiap hari',
                'default' => false,
            ],
            'summary_time' => [
                'type' => 'time',
                'label' => 'Waktu Ringkasan',
                'description' => 'Waktu untuk mengirim ringkasan harian',
                'default' => '20:00',
            ],
            'include_breakdown' => [
                'type' => 'boolean',
                'label' => 'Sertakan Breakdown Kategori',
                'description' => 'Tampilkan breakdown pengeluaran per kategori',
                'default' => true,
            ],
            'week_comparison' => [
                'type' => 'boolean',
                'label' => 'Perbandingan Minggu',
                'description' => 'Tampilkan perbandingan dengan minggu sebelumnya',
                'default' => true,
            ],
        ];
    }

    public function supportsScheduling(): bool
    {
        return true;
    }

    /**
     * @return array{type: string, value: string}
     */
    public function getDefaultSchedule(): ?array
    {
        return [
            'type' => 'daily',
            'value' => '20:00',
        ];
    }

    /**
     * Execute the plugin - check expenses and send alerts.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     */
    public function execute(int $userId, array $config, array $context = []): void
    {
        $user = $this->getUser($userId);

        if (! $user) {
            $this->logError('User not found', $userId);

            return;
        }

        if (! $user->telegram_chat_id) {
            $this->logWarning('User has no Telegram connected', $userId);

            return;
        }

        try {
            $expenses = $this->getMonthlyExpenses($userId);
            $budget = (float) ($config['monthly_budget'] ?? 5000000);
            $percentage = ($expenses / $budget) * 100;

            // Check if we need to send an alert
            $dailySummary = $config['daily_summary'] ?? false;

            if ($dailySummary) {
                $message = $this->formatDailySummary($userId, $config, $expenses, $budget, $percentage);
                $this->sendTelegramMessage($userId, $message);
                $this->logInfo('Daily expense summary sent', $userId, [
                    'expenses' => $expenses,
                    'budget' => $budget,
                    'percentage' => $percentage,
                ]);
            }

            // Check threshold alerts (only send if daily summary is not enabled or threshold just crossed)
            $thresholds = $config['alert_thresholds'] ?? ['75', '90', '100'];
            $this->checkAndSendThresholdAlert($user, $config, $expenses, $budget, $percentage, $thresholds);

        } catch (\Exception $e) {
            $this->logError('Failed to process expense alert: '.$e->getMessage(), $userId, [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get total expenses for current month.
     */
    protected function getMonthlyExpenses(int $userId): float
    {
        return (float) FinanceTransaction::where('user_id', $userId)
            ->expense()
            ->thisMonth()
            ->sum('amount');
    }

    /**
     * Get expenses grouped by category.
     *
     * @return array<array{category: string, total: float}>
     */
    protected function getExpensesByCategory(int $userId): array
    {
        return FinanceTransaction::where('user_id', $userId)
            ->expense()
            ->thisMonth()
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->with('category:id,name')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($item) => [
                'category' => $item->category?->name ?? 'Lainnya',
                'total' => (float) $item->total,
            ])
            ->toArray();
    }

    /**
     * Get this week's expenses.
     */
    protected function getWeeklyExpenses(int $userId): float
    {
        return (float) FinanceTransaction::where('user_id', $userId)
            ->expense()
            ->thisWeek()
            ->sum('amount');
    }

    /**
     * Get last week's expenses.
     */
    protected function getLastWeekExpenses(int $userId): float
    {
        return (float) FinanceTransaction::where('user_id', $userId)
            ->expense()
            ->whereBetween('occurred_at', [
                Carbon::now()->subWeek()->startOfWeek(),
                Carbon::now()->subWeek()->endOfWeek(),
            ])
            ->sum('amount');
    }

    /**
     * Check and send threshold alerts.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string>  $thresholds
     */
    protected function checkAndSendThresholdAlert(
        User $user,
        array $config,
        float $expenses,
        float $budget,
        float $percentage,
        array $thresholds
    ): void {
        // Get last alert percentage from user config
        $userPlugin = $this->getUserPlugin($user->id);
        $lastAlertPercentage = (float) ($userPlugin?->getConfig('last_alert_percentage') ?? 0);

        // Find the highest threshold that was just crossed
        $crossedThreshold = null;
        rsort($thresholds);

        foreach ($thresholds as $threshold) {
            $thresholdValue = (float) $threshold;
            if ($percentage >= $thresholdValue && $lastAlertPercentage < $thresholdValue) {
                $crossedThreshold = $thresholdValue;
                break;
            }
        }

        if ($crossedThreshold !== null) {
            $message = $this->formatThresholdAlert($user, $config, $expenses, $budget, $percentage, $crossedThreshold);
            $this->sendTelegramMessage($user->id, $message);

            // Update last alert percentage
            $userPlugin?->setConfig('last_alert_percentage', $percentage);

            $this->logInfo('Threshold alert sent', $user->id, [
                'threshold' => $crossedThreshold,
                'percentage' => $percentage,
            ]);
        }
    }

    /**
     * Format daily summary message.
     *
     * @param  array<string, mixed>  $config
     */
    protected function formatDailySummary(int $userId, array $config, float $expenses, float $budget, float $percentage): string
    {
        $user = $this->getUser($userId);
        $callPreference = $user?->profile?->call_preference ?? '';
        $aspriName = $user?->profile?->aspri_name ?? 'ASPRI';

        $message = "📊 *Ringkasan Pengeluaran Harian*\n\n";

        if ($callPreference) {
            $message .= "Hai {$callPreference}! ";
        }

        $message .= "Berikut ringkasan pengeluaran bulan ini:\n\n";

        $message .= '💰 *Total Pengeluaran:* Rp '.number_format($expenses, 0, ',', '.')." \n";
        $message .= '📈 *Budget Bulanan:* Rp '.number_format($budget, 0, ',', '.')."\n";
        $message .= '📍 *Terpakai:* '.number_format($percentage, 1).'%'."\n\n";

        $remaining = $budget - $expenses;
        if ($remaining > 0) {
            $daysLeft = Carbon::now()->daysInMonth - Carbon::now()->day + 1;
            $dailyBudget = $remaining / max(1, $daysLeft);
            $message .= '💵 *Sisa Budget:* Rp '.number_format($remaining, 0, ',', '.')."\n";
            $message .= '📅 *Budget Harian:* Rp '.number_format($dailyBudget, 0, ',', '.')." ({$daysLeft} hari lagi)\n\n";
        } else {
            $message .= "⚠️ *Budget sudah terlampaui!*\n\n";
        }

        // Category breakdown
        if ($config['include_breakdown'] ?? true) {
            $categories = $this->getExpensesByCategory($userId);
            if (! empty($categories)) {
                $message .= "📋 *Top Kategori:*\n";
                foreach (array_slice($categories, 0, 5) as $cat) {
                    $catPercentage = ($cat['total'] / max(1, $expenses)) * 100;
                    $message .= "• {$cat['category']}: Rp ".number_format($cat['total'], 0, ',', '.').' ('.number_format($catPercentage, 0).'%)'."\n";
                }
                $message .= "\n";
            }
        }

        // Week comparison
        if ($config['week_comparison'] ?? true) {
            $thisWeek = $this->getWeeklyExpenses($userId);
            $lastWeek = $this->getLastWeekExpenses($userId);

            if ($lastWeek > 0) {
                $weekChange = (($thisWeek - $lastWeek) / $lastWeek) * 100;
                $arrow = $weekChange > 0 ? '📈' : '📉';
                $changeText = $weekChange > 0 ? '+' : '';
                $message .= "{$arrow} *Minggu ini vs minggu lalu:* {$changeText}".number_format($weekChange, 1)."%\n\n";
            }
        }

        // Status message
        $message .= $this->getStatusMessage($percentage);

        $message .= "\n— {$aspriName}";

        return $message;
    }

    /**
     * Format threshold alert message.
     *
     * @param  array<string, mixed>  $config
     */
    protected function formatThresholdAlert(
        User $user,
        array $config,
        float $expenses,
        float $budget,
        float $percentage,
        float $threshold
    ): string {
        $callPreference = $user->profile?->call_preference ?? '';
        $aspriName = $user->profile?->aspri_name ?? 'ASPRI';

        $alertEmoji = match (true) {
            $threshold >= 100 => '🚨',
            $threshold >= 90 => '⚠️',
            $threshold >= 75 => '🔔',
            default => 'ℹ️',
        };

        $message = "{$alertEmoji} *Alert: Budget {$threshold}% Tercapai!*\n\n";

        if ($callPreference) {
            $message .= "Hai {$callPreference}! ";
        }

        $message .= 'Pengeluaran bulan ini sudah mencapai '.number_format($percentage, 1)."% dari budget.\n\n";
        $message .= '💰 *Pengeluaran:* Rp '.number_format($expenses, 0, ',', '.')."\n";
        $message .= '📈 *Budget:* Rp '.number_format($budget, 0, ',', '.')."\n";

        $remaining = $budget - $expenses;
        if ($remaining > 0) {
            $message .= '💵 *Sisa:* Rp '.number_format($remaining, 0, ',', '.')."\n\n";
            $message .= $this->getThresholdAdvice($threshold);
        } else {
            $overBudget = abs($remaining);
            $message .= '⛔ *Over budget:* Rp '.number_format($overBudget, 0, ',', '.')."\n\n";
            $message .= "💡 *Saran:* Pertimbangkan untuk mengurangi pengeluaran non-esensial untuk sisa bulan ini.\n";
        }

        $message .= "\n— {$aspriName}";

        return $message;
    }

    /**
     * Get status message based on percentage.
     */
    protected function getStatusMessage(float $percentage): string
    {
        return match (true) {
            $percentage < 50 => '✅ *Status:* Bagus! Pengeluaran masih terkendali.',
            $percentage < 75 => '✅ *Status:* Baik, terus jaga pengeluaran Anda.',
            $percentage < 90 => '⚠️ *Status:* Perhatian, budget hampir 75%.',
            $percentage < 100 => '⚠️ *Status:* Waspada! Budget hampir habis.',
            default => '🚨 *Status:* Budget sudah terlampaui!',
        };
    }

    /**
     * Get advice based on threshold.
     */
    protected function getThresholdAdvice(float $threshold): string
    {
        return match (true) {
            $threshold >= 100 => "💡 *Saran:* Budget sudah terlampaui. Evaluasi kembali pengeluaran bulan ini.\n",
            $threshold >= 90 => "💡 *Saran:* Kurangi pengeluaran non-esensial untuk menghindari over budget.\n",
            $threshold >= 75 => "💡 *Saran:* Tetap waspada dengan pengeluaran mendatang.\n",
            default => "💡 *Saran:* Terus pantau pengeluaran Anda.\n",
        };
    }

    public function supportsChatIntegration(): bool
    {
        return true;
    }

    public function getChatIntents(): array
    {
        $slugPrefix = str_replace('-', '_', $this->getSlug());

        return [
            [
                'action' => "plugin_{$slugPrefix}_summary",
                'description' => 'Ringkasan pengeluaran bulan ini',
                'entities' => [
                    'month' => 'string|null',
                ],
                'examples' => [
                    'ringkasan pengeluaran bulan ini',
                    'expense summary this month',
                    'berapa budget terpakai',
                ],
            ],
        ];
    }

    public function handleChatIntent(int $userId, string $action, array $entities): array
    {
        $slugPrefix = str_replace('-', '_', $this->getSlug());

        if ($action !== "plugin_{$slugPrefix}_summary") {
            return [
                'success' => false,
                'message' => 'Action not supported',
            ];
        }

        $expenses = $this->getMonthlyExpenses($userId);
        $config = $this->getUserConfig($userId);
        $budget = (float) ($config['monthly_budget'] ?? 5000000);
        $percentage = ($expenses / max(1, $budget)) * 100;

        return [
            'success' => true,
            'message' => $this->formatDailySummary($userId, $config, $expenses, $budget, $percentage),
            'data' => [
                'expenses' => $expenses,
                'budget' => $budget,
                'percentage' => $percentage,
            ],
        ];
    }

    /**
     * Reset alert tracking at the start of each month.
     */
    public function activate(int $userId): void
    {
        $config = $this->getUserConfig($userId);

        $this->createSchedule($userId, [
            'schedule_type' => 'daily',
            'schedule_value' => $config['summary_time'] ?? '20:00',
            'metadata' => [
                'type' => 'daily_summary',
            ],
        ]);

        // Reset last alert percentage when plugin is activated
        $userPlugin = $this->getUserPlugin($userId);
        $userPlugin?->setConfig('last_alert_percentage', 0);

        $this->logInfo('Expense Alert activated', $userId);
    }

    public function deactivate(int $userId): void
    {
        $this->deleteSchedules($userId);
        $this->logInfo('Expense Alert deactivated', $userId);
    }
}
