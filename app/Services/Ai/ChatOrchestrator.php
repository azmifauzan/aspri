<?php

namespace App\Services\Ai;

use App\Models\ChatThread;
use App\Models\PendingAction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ChatOrchestrator
{
    public function __construct(
        protected ChatService $chatService,
        protected IntentParserService $intentParser,
        protected ActionExecutorService $actionExecutor,
        protected AiProviderInterface $aiProvider
    ) {}

    /**
     * Process a user message and return the assistant response.
     *
     * @return array{response: string, action_taken: bool, pending_action: array|null}
     */
    public function processMessage(User $user, string $message, ChatThread $thread, array $conversationHistory = []): array
    {
        // First, check if there's a pending action for this thread
        $pendingAction = PendingAction::where('thread_id', $thread->id)
            ->pending()
            ->latest()
            ->first();

        // Parse the intent
        $intent = $this->intentParser->parse($user, $message, $conversationHistory);

        Log::debug('Parsed intent', ['intent' => $intent]);

        // Handle confirmation/cancellation of pending action
        if ($pendingAction && $intent['action'] === 'confirm') {
            return $this->handleConfirmation($user, $pendingAction);
        }

        if ($pendingAction && $intent['action'] === 'cancel') {
            return $this->handleCancellation($pendingAction);
        }

        // Cancel any existing pending action if user is doing something else
        if ($pendingAction) {
            $pendingAction->cancel();
        }

        // Handle different intents
        return match ($intent['module']) {
            'finance' => $this->handleFinanceIntent($user, $thread, $intent, $conversationHistory),
            'schedule' => $this->handleScheduleIntent($user, $thread, $intent, $conversationHistory),
            'notes' => $this->handleNotesIntent($user, $thread, $intent, $conversationHistory),
            default => $this->handleGeneralIntent($user, $intent, $conversationHistory),
        };
    }

    /**
     * Handle confirmation of a pending action.
     */
    protected function handleConfirmation(User $user, PendingAction $pendingAction): array
    {
        $pendingAction->confirm();

        $result = $this->actionExecutor->execute($pendingAction);

        $response = $result['success']
            ? $this->formatSuccessResponse($user, $result)
            : $this->formatErrorResponse($user, $result);

        return [
            'response' => $response,
            'action_taken' => $result['success'],
            'pending_action' => null,
        ];
    }

    /**
     * Handle cancellation of a pending action.
     */
    protected function handleCancellation(PendingAction $pendingAction): array
    {
        $pendingAction->cancel();

        return [
            'response' => 'Baik, aksi dibatalkan. Ada yang bisa saya bantu lainnya?',
            'action_taken' => false,
            'pending_action' => null,
        ];
    }

    /**
     * Handle finance-related intents.
     */
    protected function handleFinanceIntent(User $user, ChatThread $thread, array $intent, array $history): array
    {
        $action = $intent['action'];
        $entities = $intent['entities'];

        // View actions (no confirmation needed)
        if ($action === 'view_balance') {
            $summary = $this->actionExecutor->getFinanceSummary($user, $entities['period'] ?? null);

            return [
                'response' => $this->formatFinanceSummary($user, $summary),
                'action_taken' => false,
                'pending_action' => null,
            ];
        }

        if ($action === 'view_transactions') {
            $transactions = $this->actionExecutor->getTransactions(
                $user,
                $entities['period'] ?? null,
                $entities['tx_type'] ?? null,
                $entities['limit'] ?? 5
            );

            return [
                'response' => $this->formatTransactionsList($user, $transactions),
                'action_taken' => false,
                'pending_action' => null,
            ];
        }

        // Create/Delete actions (need confirmation)
        if ($action === 'create_transaction') {
            // Validate required fields
            if (! isset($entities['amount']) || $entities['amount'] <= 0) {
                return $this->askForMissingInfo($user, 'Berapa jumlah transaksinya?', $history);
            }

            $pendingAction = $this->createPendingAction($user, $thread, $intent);

            return [
                'response' => $this->formatTransactionConfirmation($user, $entities),
                'action_taken' => false,
                'pending_action' => $pendingAction->toArray(),
            ];
        }

        if ($action === 'delete_transaction') {
            $pendingAction = $this->createPendingAction($user, $thread, $intent);

            return [
                'response' => $this->formatDeleteConfirmation($user, 'transaksi', $entities),
                'action_taken' => false,
                'pending_action' => $pendingAction->toArray(),
            ];
        }

        return $this->generateAiResponse($user, 'Maaf, saya tidak mengerti permintaan keuangan tersebut.', $history);
    }

    /**
     * Handle schedule-related intents.
     */
    protected function handleScheduleIntent(User $user, ChatThread $thread, array $intent, array $history): array
    {
        $action = $intent['action'];
        $entities = $intent['entities'];

        if ($action === 'view_schedules') {
            $schedules = $this->actionExecutor->getSchedules($user, $entities['period'] ?? null);

            return [
                'response' => $this->formatSchedulesList($user, $schedules, $entities['period'] ?? null),
                'action_taken' => false,
                'pending_action' => null,
            ];
        }

        if ($action === 'create_schedule') {
            if (! isset($entities['title']) || empty($entities['title'])) {
                return $this->askForMissingInfo($user, 'Apa judul jadwalnya?', $history);
            }

            $pendingAction = $this->createPendingAction($user, $thread, $intent);

            return [
                'response' => $this->formatScheduleConfirmation($user, $entities),
                'action_taken' => false,
                'pending_action' => $pendingAction->toArray(),
            ];
        }

        if ($action === 'delete_schedule') {
            $pendingAction = $this->createPendingAction($user, $thread, $intent);

            return [
                'response' => $this->formatDeleteConfirmation($user, 'jadwal', $entities),
                'action_taken' => false,
                'pending_action' => $pendingAction->toArray(),
            ];
        }

        return $this->generateAiResponse($user, 'Maaf, saya tidak mengerti permintaan jadwal tersebut.', $history);
    }

    /**
     * Handle notes-related intents.
     */
    protected function handleNotesIntent(User $user, ChatThread $thread, array $intent, array $history): array
    {
        $action = $intent['action'];
        $entities = $intent['entities'];

        if ($action === 'view_notes') {
            $notes = $this->actionExecutor->getNotes(
                $user,
                $entities['search'] ?? null,
                $entities['tags'] ?? null,
                $entities['limit'] ?? 5
            );

            return [
                'response' => $this->formatNotesList($user, $notes),
                'action_taken' => false,
                'pending_action' => null,
            ];
        }

        if ($action === 'create_note') {
            if (! isset($entities['content']) || empty($entities['content'])) {
                return $this->askForMissingInfo($user, 'Apa isi catatannya?', $history);
            }

            $pendingAction = $this->createPendingAction($user, $thread, $intent);

            return [
                'response' => $this->formatNoteConfirmation($user, $entities),
                'action_taken' => false,
                'pending_action' => $pendingAction->toArray(),
            ];
        }

        if ($action === 'delete_note') {
            $pendingAction = $this->createPendingAction($user, $thread, $intent);

            return [
                'response' => $this->formatDeleteConfirmation($user, 'catatan', $entities),
                'action_taken' => false,
                'pending_action' => $pendingAction->toArray(),
            ];
        }

        return $this->generateAiResponse($user, 'Maaf, saya tidak mengerti permintaan catatan tersebut.', $history);
    }

    /**
     * Handle general intents (greeting, help, unknown).
     */
    protected function handleGeneralIntent(User $user, array $intent, array $history): array
    {
        if ($intent['action'] === 'greeting') {
            $profile = $user->profile;
            $callPref = $profile?->call_preference ?? 'Kak';
            $aspriName = $profile?->aspri_name ?? 'ASPRI';

            return [
                'response' => "Halo {$callPref} {$user->name}! Saya {$aspriName}, asisten pribadi kamu. Ada yang bisa saya bantu hari ini?\n\n"
                    ."Kamu bisa minta saya untuk:\n"
                    ."1. 💰 Mencatat transaksi keuangan\n"
                    ."2. 📅 Membuat jadwal atau pengingat\n"
                    ."3. 📝 Membuat catatan\n"
                    ."4. 📊 Melihat ringkasan keuangan\n\n"
                    .'Cukup ketik permintaan kamu dengan bahasa sehari-hari!',
                'action_taken' => false,
                'pending_action' => null,
            ];
        }

        if ($intent['action'] === 'help') {
            return [
                'response' => $this->getHelpMessage($user, $intent['entities']['topic'] ?? null),
                'action_taken' => false,
                'pending_action' => null,
            ];
        }

        // For unknown intents, use AI to generate a helpful response
        return $this->generateAiResponse($user, '', $history);
    }

    /**
     * Create a pending action for confirmation.
     */
    protected function createPendingAction(User $user, ChatThread $thread, array $intent): PendingAction
    {
        return PendingAction::create([
            'user_id' => $user->id,
            'thread_id' => $thread->id,
            'action_type' => $intent['action'],
            'module' => $intent['module'],
            'payload' => $intent['entities'],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    /**
     * Ask for missing information.
     */
    protected function askForMissingInfo(User $user, string $question, array $history): array
    {
        $profile = $user->profile;
        $callPref = $profile?->call_preference ?? 'Kak';

        return [
            'response' => "{$callPref}, {$question}",
            'action_taken' => false,
            'pending_action' => null,
        ];
    }

    /**
     * Generate AI response for complex queries.
     */
    protected function generateAiResponse(User $user, string $context, array $history): array
    {
        $response = $this->chatService->sendMessage($user, $context ?: 'Berikan respons yang membantu', $history);

        return [
            'response' => $response,
            'action_taken' => false,
            'pending_action' => null,
        ];
    }

    /**
     * Format success response.
     */
    protected function formatSuccessResponse(User $user, array $result): string
    {
        $profile = $user->profile;
        $callPref = $profile?->call_preference ?? 'Kak';

        return "✅ {$result['message']}\n\nAda lagi yang bisa saya bantu, {$callPref}?";
    }

    /**
     * Format error response.
     */
    protected function formatErrorResponse(User $user, array $result): string
    {
        return "❌ {$result['message']}\n\nSilakan coba lagi atau hubungi bantuan jika masalah berlanjut.";
    }

    /**
     * Format finance summary.
     */
    protected function formatFinanceSummary(User $user, array $summary): string
    {
        $profile = $user->profile;
        $callPref = $profile?->call_preference ?? 'Kak';

        $periodLabel = match ($summary['period']) {
            'today' => 'hari ini',
            'this_week' => 'minggu ini',
            'this_month' => 'bulan ini',
            default => 'bulan ini',
        };

        return "📊 **Ringkasan Keuangan {$callPref} {$user->name}** ({$periodLabel})\n\n"
            .'💵 Pemasukan: Rp'.number_format($summary['income'], 0, ',', '.')."\n"
            .'💸 Pengeluaran: Rp'.number_format($summary['expense'], 0, ',', '.')."\n"
            .'📈 Selisih: Rp'.number_format($summary['net'], 0, ',', '.')."\n\n"
            .'💰 Saldo Total: Rp'.number_format($summary['total_balance'], 0, ',', '.');
    }

    /**
     * Format transactions list.
     */
    protected function formatTransactionsList(User $user, array $transactions): string
    {
        if (empty($transactions)) {
            return 'Belum ada transaksi yang tercatat.';
        }

        $lines = ["📋 **Transaksi Terbaru:**\n"];

        foreach ($transactions as $t) {
            $icon = $t['type'] === 'income' ? '💵' : '💸';
            $sign = $t['type'] === 'income' ? '+' : '-';
            $lines[] = "{$icon} {$sign}Rp".number_format($t['amount'], 0, ',', '.')." - {$t['category']}".($t['note'] ? " ({$t['note']})" : '')." - {$t['date']}";
        }

        return implode("\n", $lines);
    }

    /**
     * Format schedules list.
     */
    protected function formatSchedulesList(User $user, array $schedules, ?string $period): string
    {
        if (empty($schedules)) {
            $periodLabel = match ($period) {
                'today' => 'hari ini',
                'tomorrow' => 'besok',
                'this_week' => 'minggu ini',
                default => 'bulan ini',
            };

            return "Tidak ada jadwal {$periodLabel}.";
        }

        $lines = ["📅 **Jadwal:**\n"];

        foreach ($schedules as $s) {
            $time = $s['end_time'] ? "{$s['start_time']} - {$s['end_time']}" : $s['start_time'];
            $location = $s['location'] ? " 📍 {$s['location']}" : '';
            $lines[] = "• {$s['title']} - {$time}{$location}";
        }

        return implode("\n", $lines);
    }

    /**
     * Format notes list.
     */
    protected function formatNotesList(User $user, array $notes): string
    {
        if (empty($notes)) {
            return 'Belum ada catatan yang tersimpan.';
        }

        $lines = ["📝 **Catatan Terbaru:**\n"];

        foreach ($notes as $n) {
            $tags = ! empty($n['tags']) ? ' ['.implode(', ', $n['tags']).']' : '';
            $lines[] = "• **{$n['title']}**{$tags}\n  {$n['content_preview']}";
        }

        return implode("\n", $lines);
    }

    /**
     * Format transaction confirmation.
     */
    protected function formatTransactionConfirmation(User $user, array $entities): string
    {
        $profile = $user->profile;
        $callPref = $profile?->call_preference ?? 'Kak';

        $txType = ($entities['tx_type'] ?? 'expense') === 'income' ? 'Pemasukan' : 'Pengeluaran';
        $amount = 'Rp'.number_format($entities['amount'] ?? 0, 0, ',', '.');
        $category = $entities['category'] ?? 'Belum ditentukan';
        $note = $entities['note'] ?? '-';
        $date = isset($entities['occurred_at']) ? $entities['occurred_at'] : 'Hari ini';

        return "📝 **Konfirmasi Transaksi**\n\n"
            ."Jenis: {$txType}\n"
            ."Jumlah: {$amount}\n"
            ."Kategori: {$category}\n"
            ."Keterangan: {$note}\n"
            ."Tanggal: {$date}\n\n"
            ."{$callPref}, apakah data di atas sudah benar? Balas **\"ya\"** untuk menyimpan atau **\"batal\"** untuk membatalkan.";
    }

    /**
     * Format schedule confirmation.
     */
    protected function formatScheduleConfirmation(User $user, array $entities): string
    {
        $profile = $user->profile;
        $callPref = $profile?->call_preference ?? 'Kak';

        $title = $entities['title'] ?? 'Tidak ada judul';
        $startTime = $entities['start_time'] ?? 'Belum ditentukan';
        $location = $entities['location'] ?? '-';

        return "📅 **Konfirmasi Jadwal Baru**\n\n"
            ."Judul: {$title}\n"
            ."Waktu: {$startTime}\n"
            ."Lokasi: {$location}\n\n"
            ."{$callPref}, apakah data di atas sudah benar? Balas **\"ya\"** untuk menyimpan atau **\"batal\"** untuk membatalkan.";
    }

    /**
     * Format note confirmation.
     */
    protected function formatNoteConfirmation(User $user, array $entities): string
    {
        $profile = $user->profile;
        $callPref = $profile?->call_preference ?? 'Kak';

        $title = $entities['title'] ?? 'Catatan Baru';
        $content = mb_substr($entities['content'] ?? '', 0, 100);
        if (mb_strlen($entities['content'] ?? '') > 100) {
            $content .= '...';
        }
        $tags = ! empty($entities['tags']) ? implode(', ', $entities['tags']) : '-';

        return "📝 **Konfirmasi Catatan Baru**\n\n"
            ."Judul: {$title}\n"
            ."Isi: {$content}\n"
            ."Tags: {$tags}\n\n"
            ."{$callPref}, apakah data di atas sudah benar? Balas **\"ya\"** untuk menyimpan atau **\"batal\"** untuk membatalkan.";
    }

    /**
     * Format delete confirmation.
     */
    protected function formatDeleteConfirmation(User $user, string $itemType, array $entities): string
    {
        $profile = $user->profile;
        $callPref = $profile?->call_preference ?? 'Kak';

        $identifier = $entities['title'] ?? $entities['description'] ?? $entities['note_id'] ?? $entities['schedule_id'] ?? $entities['transaction_id'] ?? 'item tersebut';

        return "⚠️ **Konfirmasi Hapus**\n\n"
            ."{$callPref}, apakah kamu yakin ingin menghapus {$itemType} \"{$identifier}\"?\n\n"
            .'Balas **"ya"** untuk menghapus atau **"batal"** untuk membatalkan.';
    }

    /**
     * Get help message.
     */
    protected function getHelpMessage(User $user, ?string $topic): string
    {
        $profile = $user->profile;
        $callPref = $profile?->call_preference ?? 'Kak';
        $aspriName = $profile?->aspri_name ?? 'ASPRI';

        if ($topic === 'finance' || $topic === 'keuangan') {
            return "💰 **Bantuan Keuangan**\n\n"
                ."Contoh perintah yang bisa {$callPref} gunakan:\n"
                ."• \"Catat pengeluaran 50rb untuk makan siang\"\n"
                ."• \"Catat pemasukan 5jt dari gaji\"\n"
                ."• \"Lihat ringkasan keuangan bulan ini\"\n"
                ."• \"Tampilkan transaksi hari ini\"\n"
                .'• "Berapa saldo saya?"';
        }

        if ($topic === 'schedule' || $topic === 'jadwal') {
            return "📅 **Bantuan Jadwal**\n\n"
                ."Contoh perintah yang bisa {$callPref} gunakan:\n"
                ."• \"Buat jadwal meeting besok jam 10 pagi\"\n"
                ."• \"Ingatkan saya bayar tagihan tanggal 15\"\n"
                ."• \"Lihat jadwal minggu ini\"\n"
                .'• "Apa agenda hari ini?"';
        }

        if ($topic === 'notes' || $topic === 'catatan') {
            return "📝 **Bantuan Catatan**\n\n"
                ."Contoh perintah yang bisa {$callPref} gunakan:\n"
                ."• \"Buat catatan: ide project baru untuk...\"\n"
                ."• \"Catat resep masakan favorit...\"\n"
                ."• \"Lihat catatan saya\"\n"
                .'• "Cari catatan tentang meeting"';
        }

        return "👋 **Halo {$callPref}! Saya {$aspriName}**\n\n"
            ."Saya bisa membantu {$callPref} untuk:\n\n"
            ."💰 **Keuangan**\n"
            ."   Catat pemasukan/pengeluaran, lihat saldo & ringkasan\n\n"
            ."📅 **Jadwal**\n"
            ."   Buat jadwal, lihat agenda, atur pengingat\n\n"
            ."📝 **Catatan**\n"
            ."   Simpan catatan, cari catatan lama\n\n"
            .'Ketik "bantuan keuangan", "bantuan jadwal", atau "bantuan catatan" untuk panduan lebih detail!';
    }
}
