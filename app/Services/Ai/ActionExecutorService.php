<?php

namespace App\Services\Ai;

use App\Models\FinanceTransaction;
use App\Models\Note;
use App\Models\PendingAction;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;

class ActionExecutorService
{
    /**
     * Execute a confirmed action.
     *
     * @return array{success: bool, message: string, data: array|null}
     */
    public function execute(PendingAction $pendingAction): array
    {
        $user = $pendingAction->user;
        $payload = $pendingAction->payload;

        return match ($pendingAction->module) {
            'finance' => $this->executeFinanceAction($user, $pendingAction->action_type, $payload),
            'schedule' => $this->executeScheduleAction($user, $pendingAction->action_type, $payload),
            'notes' => $this->executeNotesAction($user, $pendingAction->action_type, $payload),
            default => ['success' => false, 'message' => 'Modul tidak dikenali', 'data' => null],
        };
    }

    /**
     * Execute finance-related actions.
     */
    protected function executeFinanceAction(User $user, string $action, array $payload): array
    {
        return match ($action) {
            'create_transaction' => $this->createTransaction($user, $payload),
            'delete_transaction' => $this->deleteTransaction($user, $payload),
            default => ['success' => false, 'message' => 'Aksi keuangan tidak dikenali', 'data' => null],
        };
    }

    /**
     * Execute schedule-related actions.
     */
    protected function executeScheduleAction(User $user, string $action, array $payload): array
    {
        return match ($action) {
            'create_schedule' => $this->createSchedule($user, $payload),
            'delete_schedule' => $this->deleteSchedule($user, $payload),
            default => ['success' => false, 'message' => 'Aksi jadwal tidak dikenali', 'data' => null],
        };
    }

    /**
     * Execute notes-related actions.
     */
    protected function executeNotesAction(User $user, string $action, array $payload): array
    {
        return match ($action) {
            'create_note' => $this->createNote($user, $payload),
            'delete_note' => $this->deleteNote($user, $payload),
            default => ['success' => false, 'message' => 'Aksi catatan tidak dikenali', 'data' => null],
        };
    }

    /**
     * Create a new transaction.
     */
    protected function createTransaction(User $user, array $payload): array
    {
        try {
            // Get or create default account
            $account = $user->financeAccounts()->first();
            if (! $account) {
                $account = $user->financeAccounts()->create([
                    'name' => 'Utama',
                    'type' => 'cash',
                    'currency' => 'IDR',
                    'initial_balance' => 0,
                ]);
            }

            // Find or create category
            $txType = $payload['tx_type'] ?? 'expense';
            $categoryName = $payload['category'] ?? ($txType === 'income' ? 'Pemasukan Lain' : 'Pengeluaran Lain');

            $category = $user->financeCategories()
                ->where('tx_type', $txType)
                ->where('name', 'like', "%{$categoryName}%")
                ->first();

            if (! $category) {
                $category = $user->financeCategories()->create([
                    'name' => $categoryName,
                    'tx_type' => $txType,
                    'icon' => $txType === 'income' ? 'wallet' : 'shopping-cart',
                    'color' => $txType === 'income' ? '#22c55e' : '#ef4444',
                ]);
            }

            // Parse date
            $occurredAt = isset($payload['occurred_at'])
                ? Carbon::parse($payload['occurred_at'])
                : now();

            $transaction = FinanceTransaction::create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => $category->id,
                'tx_type' => $txType,
                'amount' => $payload['amount'],
                'occurred_at' => $occurredAt,
                'note' => $payload['note'] ?? null,
            ]);

            $typeLabel = $txType === 'income' ? 'pemasukan' : 'pengeluaran';

            return [
                'success' => true,
                'message' => "Transaksi {$typeLabel} sebesar Rp".number_format($payload['amount'], 0, ',', '.').' berhasil dicatat!',
                'data' => [
                    'transaction_id' => $transaction->id,
                    'amount' => $payload['amount'],
                    'tx_type' => $txType,
                    'category' => $category->name,
                    'note' => $transaction->note,
                    'occurred_at' => $occurredAt->format('d M Y'),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Gagal mencatat transaksi: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Delete a transaction.
     */
    protected function deleteTransaction(User $user, array $payload): array
    {
        try {
            $query = $user->financeTransactions();

            if (isset($payload['transaction_id'])) {
                $query->where('id', $payload['transaction_id']);
            } elseif (isset($payload['description'])) {
                $query->where('note', 'like', "%{$payload['description']}%");
            }

            $transaction = $query->latest()->first();

            if (! $transaction) {
                return [
                    'success' => false,
                    'message' => 'Transaksi tidak ditemukan',
                    'data' => null,
                ];
            }

            $amount = $transaction->amount;
            $transaction->delete();

            return [
                'success' => true,
                'message' => 'Transaksi sebesar Rp'.number_format($amount, 0, ',', '.').' berhasil dihapus!',
                'data' => null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Gagal menghapus transaksi: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Create a new schedule.
     */
    protected function createSchedule(User $user, array $payload): array
    {
        try {
            $startTime = isset($payload['start_time'])
                ? Carbon::parse($payload['start_time'])
                : now()->addHour();

            $endTime = isset($payload['end_time'])
                ? Carbon::parse($payload['end_time'])
                : $startTime->copy()->addHour();

            $schedule = Schedule::create([
                'user_id' => $user->id,
                'title' => $payload['title'],
                'description' => $payload['description'] ?? null,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'location' => $payload['location'] ?? null,
            ]);

            return [
                'success' => true,
                'message' => "Jadwal \"{$payload['title']}\" berhasil dibuat untuk {$startTime->format('d M Y H:i')}!",
                'data' => [
                    'schedule_id' => $schedule->id,
                    'title' => $schedule->title,
                    'start_time' => $startTime->format('d M Y H:i'),
                    'end_time' => $endTime->format('d M Y H:i'),
                    'location' => $schedule->location,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Gagal membuat jadwal: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Delete a schedule.
     */
    protected function deleteSchedule(User $user, array $payload): array
    {
        try {
            $query = Schedule::where('user_id', $user->id);

            if (isset($payload['schedule_id'])) {
                $query->where('id', $payload['schedule_id']);
            } elseif (isset($payload['title'])) {
                $query->where('title', 'like', "%{$payload['title']}%");
            }

            $schedule = $query->latest()->first();

            if (! $schedule) {
                return [
                    'success' => false,
                    'message' => 'Jadwal tidak ditemukan',
                    'data' => null,
                ];
            }

            $title = $schedule->title;
            $schedule->delete();

            return [
                'success' => true,
                'message' => "Jadwal \"{$title}\" berhasil dihapus!",
                'data' => null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Gagal menghapus jadwal: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Create a new note.
     */
    protected function createNote(User $user, array $payload): array
    {
        try {
            $title = $payload['title'] ?? 'Catatan '.now()->format('d M Y H:i');

            $note = Note::create([
                'user_id' => $user->id,
                'title' => $title,
                'content' => $payload['content'],
                'tags' => $payload['tags'] ?? [],
                'is_pinned' => false,
            ]);

            return [
                'success' => true,
                'message' => "Catatan \"{$title}\" berhasil disimpan!",
                'data' => [
                    'note_id' => $note->id,
                    'title' => $note->title,
                    'content' => mb_substr($note->content, 0, 100).(mb_strlen($note->content) > 100 ? '...' : ''),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Gagal menyimpan catatan: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Delete a note.
     */
    protected function deleteNote(User $user, array $payload): array
    {
        try {
            $query = Note::where('user_id', $user->id);

            if (isset($payload['note_id'])) {
                $query->where('id', $payload['note_id']);
            } elseif (isset($payload['title'])) {
                $query->where('title', 'like', "%{$payload['title']}%");
            }

            $note = $query->latest()->first();

            if (! $note) {
                return [
                    'success' => false,
                    'message' => 'Catatan tidak ditemukan',
                    'data' => null,
                ];
            }

            $title = $note->title;
            $note->delete();

            return [
                'success' => true,
                'message' => "Catatan \"{$title}\" berhasil dihapus!",
                'data' => null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Gagal menghapus catatan: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Get finance summary for the user.
     */
    public function getFinanceSummary(User $user, ?string $period = null): array
    {
        $query = $user->financeTransactions();

        switch ($period) {
            case 'today':
                $query->whereDate('occurred_at', today());
                break;
            case 'this_week':
                $query->whereBetween('occurred_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'this_month':
            default:
                $query->whereMonth('occurred_at', now()->month)
                    ->whereYear('occurred_at', now()->year);
                break;
        }

        $income = (clone $query)->where('tx_type', 'income')->sum('amount');
        $expense = (clone $query)->where('tx_type', 'expense')->sum('amount');
        $balance = $income - $expense;

        // Get total balance from all accounts
        $totalBalance = $user->financeAccounts->sum(fn ($acc) => $acc->current_balance);

        return [
            'period' => $period ?? 'this_month',
            'income' => $income,
            'expense' => $expense,
            'net' => $balance,
            'total_balance' => $totalBalance,
        ];
    }

    /**
     * Get recent transactions.
     */
    public function getTransactions(User $user, ?string $period = null, ?string $txType = null, int $limit = 5): array
    {
        $query = $user->financeTransactions()->with('category');

        switch ($period) {
            case 'today':
                $query->whereDate('occurred_at', today());
                break;
            case 'this_week':
                $query->whereBetween('occurred_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'this_month':
                $query->whereMonth('occurred_at', now()->month)
                    ->whereYear('occurred_at', now()->year);
                break;
        }

        if ($txType) {
            $query->where('tx_type', $txType);
        }

        return $query->latest('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->tx_type,
                'amount' => $t->amount,
                'category' => $t->category?->name ?? 'Tidak ada kategori',
                'note' => $t->note,
                'date' => $t->occurred_at->format('d M Y'),
            ])
            ->toArray();
    }

    /**
     * Get schedules for the user.
     */
    public function getSchedules(User $user, ?string $period = null): array
    {
        $query = Schedule::where('user_id', $user->id);

        switch ($period) {
            case 'today':
                $query->whereDate('start_time', today());
                break;
            case 'tomorrow':
                $query->whereDate('start_time', today()->addDay());
                break;
            case 'this_week':
                $query->whereBetween('start_time', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'this_month':
            default:
                $query->whereMonth('start_time', now()->month)
                    ->whereYear('start_time', now()->year);
                break;
        }

        return $query->orderBy('start_time')
            ->limit(10)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'start_time' => $s->start_time->format('d M Y H:i'),
                'end_time' => $s->end_time?->format('H:i'),
                'location' => $s->location,
            ])
            ->toArray();
    }

    /**
     * Get notes for the user.
     */
    public function getNotes(User $user, ?string $search = null, ?array $tags = null, int $limit = 5): array
    {
        $query = Note::where('user_id', $user->id);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($tags) {
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        return $query->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->title,
                'content_preview' => mb_substr($n->content, 0, 100).(mb_strlen($n->content) > 100 ? '...' : ''),
                'tags' => $n->tags,
                'created_at' => $n->created_at->format('d M Y'),
            ])
            ->toArray();
    }
}
