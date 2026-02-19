<?php

namespace App\Services\Ai;

use App\Models\ChatThread;
use App\Models\PendingAction;
use App\Models\User;
use App\Services\Plugin\PluginManager;
use Illuminate\Support\Facades\Log;

class ChatOrchestrator
{
    public function __construct(
        protected ChatService $chatService,
        protected IntentParserService $intentParser,
        protected ActionExecutorService $actionExecutor,
        protected AiProviderInterface $aiProvider,
        protected PluginManager $pluginManager
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

        Log::debug('Checking for pending action', [
            'thread_id' => $thread->id,
            'has_pending_action' => $pendingAction !== null,
            'pending_action_id' => $pendingAction?->id,
        ]);

        // Parse the intent
        $intent = $this->intentParser->parse($user, $message, $conversationHistory);

        Log::debug('Parsed intent', ['intent' => $intent]);

        // Handle confirmation/cancellation of pending action
        if ($pendingAction && $intent['action'] === 'confirm') {
            Log::info('Confirm action detected, calling handleConfirmation');

            return $this->handleConfirmation($user, $pendingAction);
        }

        if ($pendingAction && $intent['action'] === 'cancel') {
            Log::info('Cancel action detected, calling handleCancellation');

            return $this->handleCancellation($pendingAction);
        }

        // Cancel any existing pending action if user is doing something else
        if ($pendingAction) {
            Log::info('Canceling existing pending action due to new request', [
                'pending_action_id' => $pendingAction->id,
                'action_type' => $pendingAction->action_type,
                'new_intent_action' => $intent['action'],
            ]);
            $pendingAction->cancel();
        }

        // Handle different intents
        return match ($intent['module']) {
            'finance' => $this->handleFinanceIntent($user, $thread, $intent, $conversationHistory),
            'schedule' => $this->handleScheduleIntent($user, $thread, $intent, $conversationHistory),
            'notes' => $this->handleNotesIntent($user, $thread, $intent, $conversationHistory),
            'plugin' => $this->handlePluginIntent($user, $thread, $intent, $conversationHistory),
            default => $this->handleGeneralIntent($user, $intent, $conversationHistory, $message),
        };
    }

    /**
     * Handle confirmation of a pending action.
     */
    protected function handleConfirmation(User $user, PendingAction $pendingAction): array
    {
        Log::info('Handling confirmation', [
            'pending_action_id' => $pendingAction->id,
            'action_type' => $pendingAction->action_type,
            'module' => $pendingAction->module,
            'payload' => $pendingAction->payload,
        ]);

        $pendingAction->confirm();
        Log::debug('Pending action confirmed', ['pending_action_id' => $pendingAction->id]);

        $result = $this->actionExecutor->execute($pendingAction);
        Log::info('Action executed', [
            'pending_action_id' => $pendingAction->id,
            'success' => $result['success'],
            'message' => $result['message'] ?? null,
        ]);

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
            'response' => $this->personalizeResponse(
                $pendingAction->user,
                'success',
                [],
                'Aksi dibatalkan. Tanyakan apakah ada yang bisa dibantu lainnya.'
            ),
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

        if ($action === 'update_schedule') {
            if (! isset($entities['schedule_id']) && ! isset($entities['title'])) {
                return $this->askForMissingInfo($user, 'Jadwal mana yang ingin diubah?', $history);
            }

            $pendingAction = $this->createPendingAction($user, $thread, $intent);

            return [
                'response' => $this->formatScheduleUpdateConfirmation($user, $entities),
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

            // Execute directly without confirmation (non-destructive operation)
            $result = $this->actionExecutor->executeDirectNotesAction($user, 'create_note', $entities);

            return [
                'response' => $result['success']
                    ? $this->formatSuccessResponse($user, $result)
                    : $this->formatErrorResponse($user, $result),
                'action_taken' => $result['success'],
                'pending_action' => null,
            ];
        }

        if ($action === 'update_note') {
            if (! isset($entities['note_id']) && ! isset($entities['title'])) {
                return $this->askForMissingInfo($user, 'Catatan mana yang ingin diperbarui?', $history);
            }

            // Execute directly without confirmation (non-destructive operation)
            $result = $this->actionExecutor->executeDirectNotesAction($user, 'update_note', $entities);

            return [
                'response' => $result['success']
                    ? $this->formatSuccessResponse($user, $result)
                    : $this->formatErrorResponse($user, $result),
                'action_taken' => $result['success'],
                'pending_action' => null,
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
     * Handle plugin-related intents.
     */
    protected function handlePluginIntent(User $user, ChatThread $thread, array $intent, array $history): array
    {
        $action = $intent['action'];
        $entities = $intent['entities'];

        // Get plugin slug from entities
        $pluginSlug = $entities['plugin_slug'] ?? null;

        if (! $pluginSlug) {
            return $this->generateAiResponse($user, 'Maaf, plugin tidak ditemukan.', $history);
        }

        // Get plugin instance
        $pluginInstance = $this->pluginManager->getPlugin($pluginSlug);

        if (! $pluginInstance) {
            return $this->generateAiResponse($user, 'Maaf, plugin tidak tersedia.', $history);
        }

        // Check if user has plugin activated
        $userPlugin = $this->pluginManager->getActivePluginsForUser($user->id)
            ->firstWhere('plugin.slug', $pluginSlug);

        if (! $userPlugin) {
            $pluginName = $pluginInstance->getName();

            return [
                'response' => $this->personalizeResponse(
                    $user,
                    'error',
                    [],
                    "Plugin {$pluginName} belum diaktifkan. Infokan user untuk mengaktifkannya di halaman Plugin."
                ),
                'action_taken' => false,
                'pending_action' => null,
            ];
        }

        // Execute plugin chat intent
        try {
            $result = $pluginInstance->handleChatIntent($user->id, $action, $entities);

            if ($result['success']) {
                return [
                    'response' => $this->personalizeResponse(
                        $user,
                        'success',
                        [],
                        $result['message']
                    ),
                    'action_taken' => true,
                    'pending_action' => null,
                    'data' => $result['data'] ?? null,
                ];
            }

            return [
                'response' => $this->personalizeResponse(
                    $user,
                    'error',
                    [],
                    $result['message'] ?? 'Terjadi kesalahan saat menjalankan plugin.'
                ),
                'action_taken' => false,
                'pending_action' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Plugin execution error', [
                'plugin' => $pluginSlug,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return [
                'response' => $this->personalizeResponse(
                    $user,
                    'error',
                    [],
                    'Terjadi kesalahan saat menjalankan plugin. Minta user coba lagi.'
                ),
                'action_taken' => false,
                'pending_action' => null,
            ];
        }
    }

    /**
     * Handle general intents (greeting, help, unknown).
     */
    protected function handleGeneralIntent(User $user, array $intent, array $history, string $currentMessage): array
    {
        if ($intent['action'] === 'greeting') {
            return [
                'response' => $this->personalizeResponse($user, 'greeting', ['user_name' => $user->name]),
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

        // Handle out of scope questions - forward to LLM with persona
        if ($intent['action'] === 'out_of_scope') {
            return $this->handleOutOfScopeQuestion($user, $intent, $history, $currentMessage);
        }

        // Handle unknown intents - use contextual AI response instead of generic template
        if ($intent['action'] === 'unknown') {
            return $this->handleCasualConversation($user, $intent, $history, $currentMessage);
        }

        // For any other unknown intents, use AI to generate a helpful response
        return $this->handleCasualConversation($user, $intent, $history, $currentMessage);
    }

    /**
     * Handle out of scope questions by forwarding to LLM with persona.
     */
    protected function handleOutOfScopeQuestion(User $user, array $intent, array $history, string $currentMessage): array
    {
        $topic = $intent['entities']['topic'] ?? 'pertanyaan tersebut';
        $questionType = $intent['entities']['question_type'] ?? null;

        // Build context for the LLM to answer with persona
        $profile = $user->profile;
        $callPref = $profile?->call_preference ?? 'Kak';
        $userName = $user->name;
        $aspriName = $profile?->aspri_name ?? 'ASPRI';
        $aspriPersona = $profile?->aspri_persona ?? 'asisten yang ramah dan membantu';

        $systemPrompt = <<<PROMPT
Kamu adalah {$aspriName}, {$aspriPersona}.
Kamu adalah asisten pribadi yang membantu user mengelola keuangan, jadwal, dan catatan.
Kamu harus memanggil user dengan "{$callPref} {$userName}".

Jawab dengan gaya komunikasimu yang khas. Tetap ramah dan membantu.
PROMPT;

        // Get the last user message from history to get the actual question
        $lastUserMessage = trim($currentMessage);
        if ($lastUserMessage === '') {
            foreach (array_reverse($history) as $msg) {
                if ($msg['role'] === 'user') {
                    $lastUserMessage = $msg['content'];
                    break;
                }
            }
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Add recent conversation history for context (last 3 exchanges)
        foreach (array_slice($history, -6) as $msg) {
            $messages[] = $msg;
        }

        // Add current question if not already in history
        if ($lastUserMessage) {
            if (empty($history) || end($history)['content'] !== $lastUserMessage) {
                $messages[] = ['role' => 'user', 'content' => $lastUserMessage];
            }
        } else {
            // Fallback: use topic from intent
            $messages[] = ['role' => 'user', 'content' => "Pertanyaan tentang: {$topic}"];
        }

        $response = $this->aiProvider->chat($messages, [
            'temperature' => 0.8, // Slightly higher for more natural responses
            'max_tokens' => 1500, // Increased to allow longer responses
        ]);

        return [
            'response' => $response,
            'action_taken' => false,
            'pending_action' => null,
        ];
    }

    /**
     * Handle casual conversation or unknown intents by using AI with full context.
     * This ensures ASPRI can respond naturally to any message, even when intent is unclear.
     */
    protected function handleCasualConversation(User $user, array $intent, array $history, string $currentMessage): array
    {
        $profile = $user->profile;
        $callPref = $profile?->call_preference ?? 'Kak';
        $userName = $user->name;
        $aspriName = $profile?->aspri_name ?? 'ASPRI';
        $aspriPersona = $profile?->aspri_persona ?? 'asisten yang ramah dan membantu';

        $unclearReason = $intent['entities']['unclear_reason'] ?? null;

        $systemPrompt = <<<PROMPT
Kamu adalah {$aspriName}, {$aspriPersona}.
Kamu adalah asisten pribadi yang membantu user mengelola keuangan, jadwal, dan catatan.
Kamu harus memanggil user dengan "{$callPref} {$userName}".

KONTEKS: User mengirim pesan yang tidak termasuk perintah spesifik aplikasi.

TUGAS:
1. Jika pesan user adalah pertanyaan atau pernyataan yang bisa kamu jawab/tanggapi dengan pengetahuanmu, jawab dengan natural sesuai kepribadianmu.
2. Jika pesan user tidak jelas atau ambigu, minta klarifikasi dengan ramah. Jelaskan bahwa kamu tidak yakin apa yang dimaksud, dan berikan contoh perintah yang bisa kamu bantu (misalnya: "catat pengeluaran", "lihat jadwal", "buat catatan").
3. Jika user bertanya tentang data pribadi mereka (saldo, transaksi, jadwal, catatan), arahkan untuk menggunakan perintah yang tepat (misalnya: "coba tanya 'lihat saldo bulan ini'").
4. Untuk pertanyaan umum atau casual chat, respon dengan natural dan ramah sesuai kepribadianmu.

PENTING: Selalu tetap dalam karakter kepribadianmu. Jangan kaku atau terlalu formal.
PROMPT;

        // Get the last user message from history to get the actual question
        $lastUserMessage = trim($currentMessage);
        if ($lastUserMessage === '') {
            foreach (array_reverse($history) as $msg) {
                if ($msg['role'] === 'user') {
                    $lastUserMessage = $msg['content'];
                    break;
                }
            }
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Add recent conversation history for context (last 5 exchanges)
        foreach (array_slice($history, -10) as $msg) {
            $messages[] = $msg;
        }

        // Add current message if not already in history
        if ($lastUserMessage) {
            if (empty($history) || end($history)['content'] !== $lastUserMessage) {
                $messages[] = ['role' => 'user', 'content' => $lastUserMessage];
            }
        }

        $response = $this->aiProvider->chat($messages, [
            'temperature' => 0.8, // Higher temperature for more natural, conversational responses
            'max_tokens' => 1500,
        ]);

        return [
            'response' => $response,
            'action_taken' => false,
            'pending_action' => null,
        ];
    }

    /**
     * Create a pending action for confirmation.
     */
    protected function createPendingAction(User $user, ChatThread $thread, array $intent): PendingAction
    {
        $pendingAction = PendingAction::create([
            'user_id' => $user->id,
            'thread_id' => $thread->id,
            'action_type' => $intent['action'],
            'module' => $intent['module'],
            'payload' => $intent['entities'],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(5),
        ]);

        Log::info('Pending action created', [
            'pending_action_id' => $pendingAction->id,
            'action_type' => $pendingAction->action_type,
            'module' => $pendingAction->module,
        ]);

        return $pendingAction;
    }

    /**
     * Ask for missing information.
     */
    protected function askForMissingInfo(User $user, string $question, array $history): array
    {
        return [
            'response' => $this->personalizeResponse($user, 'success', [], "Tanyakan ke user: {$question}"),
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
     * Personalize any response through LLM with user's ASPRI persona.
     * This ensures all responses are consistent with the user's registered persona settings.
     */
    protected function personalizeResponse(User $user, string $responseType, array $data, ?string $rawContent = null): string
    {
        $profile = $user->profile;
        $callPref = $profile?->call_preference ?? 'Kak';
        $userName = $user->name;
        $aspriName = $profile?->aspri_name ?? 'ASPRI';
        $aspriPersona = $profile?->aspri_persona ?? 'asisten yang ramah dan membantu';

        // Build context based on response type
        $context = $this->buildResponseContext($responseType, $data, $rawContent);

        $prompt = <<<PROMPT
Kamu adalah {$aspriName}, {$aspriPersona}.
Kamu harus memanggil user dengan "{$callPref} {$userName}".

Tugas: Sampaikan informasi berikut dengan gaya komunikasi yang sesuai dengan kepribadianmu.
Jangan mengubah data/angka, hanya sampaikan dengan caramu sendiri.

Tipe Respons: {$responseType}

Informasi yang harus disampaikan:
{$context}

Sampaikan dengan natural, sesuai kepribadianmu, dan tetap informatif.
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $this->chatService->buildSystemPrompt($user)],
            ['role' => 'user', 'content' => $prompt],
        ];

        return $this->aiProvider->chat($messages, ['temperature' => 0.7]);
    }

    /**
     * Build response context for personalization.
     */
    protected function buildResponseContext(string $responseType, array $data, ?string $rawContent): string
    {
        if ($rawContent) {
            return $rawContent;
        }

        return match ($responseType) {
            'finance_summary' => $this->buildFinanceSummaryContext($data),
            'transactions_list' => $this->buildTransactionsContext($data),
            'schedules_list' => $this->buildSchedulesContext($data),
            'notes_list' => $this->buildNotesContext($data),
            'transaction_confirmation' => $this->buildTransactionConfirmationContext($data),
            'schedule_confirmation' => $this->buildScheduleConfirmationContext($data),
            'schedule_update_confirmation' => $this->buildScheduleUpdateConfirmationContext($data),
            'note_confirmation' => $this->buildNoteConfirmationContext($data),
            'delete_confirmation' => $this->buildDeleteConfirmationContext($data),
            'success' => "Aksi berhasil: {$data['message']}",
            'error' => "Terjadi kesalahan: {$data['message']}",
            'greeting' => 'Sapa user dan tawarkan bantuan. User bisa mencatat transaksi keuangan, membuat jadwal, membuat catatan, atau melihat ringkasan.',
            'help' => $this->buildHelpContext($data),
            'out_of_scope' => $this->buildOutOfScopeContext($data),
            'unknown' => $this->buildUnknownContext($data),
            default => $rawContent ?? 'Berikan respons yang membantu',
        };
    }

    protected function buildFinanceSummaryContext(array $data): string
    {
        $period = match ($data['period'] ?? 'this_month') {
            'today' => 'hari ini',
            'this_week' => 'minggu ini',
            'this_month' => 'bulan ini',
            default => 'bulan ini',
        };

        return "Tampilkan ringkasan keuangan {$period}:\n"
            .'- Pemasukan: Rp '.number_format($data['income'], 0, ',', '.')."\n"
            .'- Pengeluaran: Rp '.number_format($data['expense'], 0, ',', '.')."\n"
            .'- Selisih: Rp '.number_format($data['net'], 0, ',', '.')."\n"
            .'- Saldo Total: Rp '.number_format($data['total_balance'], 0, ',', '.');
    }

    protected function buildTransactionsContext(array $data): string
    {
        if (empty($data['transactions'])) {
            return 'Belum ada transaksi yang tercatat.';
        }

        $context = "Tampilkan daftar transaksi berikut:\n";
        foreach ($data['transactions'] as $t) {
            $sign = $t['type'] === 'income' ? '+' : '-';
            $context .= "- {$sign}Rp".number_format($t['amount'], 0, ',', '.').
                       " untuk {$t['category']}".
                       ($t['note'] ? " ({$t['note']})" : '').
                       " pada {$t['date']}\n";
        }

        return $context;
    }

    protected function buildSchedulesContext(array $data): string
    {
        if (empty($data['schedules'])) {
            $period = match ($data['period'] ?? null) {
                'today' => 'hari ini',
                'tomorrow' => 'besok',
                'this_week' => 'minggu ini',
                default => '',
            };

            return $period ? "Tidak ada jadwal {$period}." : 'Tidak ada jadwal.';
        }

        $context = "Tampilkan jadwal berikut:\n";
        foreach ($data['schedules'] as $s) {
            $time = $s['end_time'] ? "{$s['start_time']} - {$s['end_time']}" : $s['start_time'];
            $location = $s['location'] ? " di {$s['location']}" : '';
            $context .= "- {$s['title']} pada {$time}{$location}\n";
        }

        return $context;
    }

    protected function buildNotesContext(array $data): string
    {
        if (empty($data['notes'])) {
            return 'Belum ada catatan yang tersimpan.';
        }

        $context = "Tampilkan catatan berikut:\n";
        foreach ($data['notes'] as $n) {
            $tags = ! empty($n['tags']) ? ' dengan tags: '.implode(', ', $n['tags']) : '';
            $context .= "- Judul: {$n['title']}{$tags}\n  Preview: {$n['content_preview']}\n";
        }

        return $context;
    }

    protected function buildTransactionConfirmationContext(array $data): string
    {
        $txType = ($data['tx_type'] ?? 'expense') === 'income' ? 'Pemasukan' : 'Pengeluaran';
        $amount = 'Rp'.number_format($data['amount'] ?? 0, 0, ',', '.');
        $category = $data['category'] ?? 'Belum ditentukan';
        $note = $data['note'] ?? '-';
        $date = $data['occurred_at'] ?? 'Hari ini';

        return "Minta konfirmasi untuk menyimpan transaksi:\n"
            ."- Jenis: {$txType}\n"
            ."- Jumlah: {$amount}\n"
            ."- Kategori: {$category}\n"
            ."- Keterangan: {$note}\n"
            ."- Tanggal: {$date}\n\n"
            .'User harus balas "ya" untuk menyimpan atau "batal" untuk membatalkan.';
    }

    protected function buildScheduleConfirmationContext(array $data): string
    {
        $title = $data['title'] ?? 'Tidak ada judul';
        $startTime = $data['start_time'] ?? 'Belum ditentukan';
        $location = $data['location'] ?? '-';

        return "Minta konfirmasi untuk membuat jadwal:\n"
            ."- Judul: {$title}\n"
            ."- Waktu: {$startTime}\n"
            ."- Lokasi: {$location}\n\n"
            .'User harus balas "ya" untuk menyimpan atau "batal" untuk membatalkan.';
    }

    protected function buildScheduleUpdateConfirmationContext(array $data): string
    {
        $identifier = $data['title'] ?? $data['schedule_id'] ?? 'jadwal tersebut';

        $changes = [];
        if (isset($data['new_title'])) {
            $changes[] = "- Judul baru: {$data['new_title']}";
        }
        if (isset($data['start_time'])) {
            $changes[] = "- Waktu mulai baru: {$data['start_time']}";
        }
        if (isset($data['end_time'])) {
            $changes[] = "- Waktu selesai baru: {$data['end_time']}";
        }
        if (isset($data['location'])) {
            $changes[] = "- Lokasi baru: {$data['location']}";
        }
        if (isset($data['description'])) {
            $changes[] = "- Deskripsi baru: {$data['description']}";
        }

        $changesText = ! empty($changes) ? implode("\n", $changes) : '- Tidak ada detail perubahan';

        return "Minta konfirmasi untuk mengubah jadwal: \"{$identifier}\"\n"
            ."Perubahan:\n{$changesText}\n\n"
            .'User harus balas "ya" untuk menyimpan perubahan atau "batal" untuk membatalkan.';
    }

    protected function buildNoteConfirmationContext(array $data): string
    {
        $title = $data['title'] ?? 'Catatan Baru';
        $content = mb_substr($data['content'] ?? '', 0, 100);
        if (mb_strlen($data['content'] ?? '') > 100) {
            $content .= '...';
        }
        $tags = ! empty($data['tags']) ? implode(', ', $data['tags']) : '-';

        return "Minta konfirmasi untuk membuat catatan:\n"
            ."- Judul: {$title}\n"
            ."- Isi: {$content}\n"
            ."- Tags: {$tags}\n\n"
            .'User harus balas "ya" untuk menyimpan atau "batal" untuk membatalkan.';
    }

    protected function buildDeleteConfirmationContext(array $data): string
    {
        $itemType = $data['item_type'] ?? 'item';
        $identifier = $data['identifier'] ?? 'item tersebut';

        return "Minta konfirmasi untuk MENGHAPUS {$itemType}: \"{$identifier}\".\n"
            .'User harus balas "ya" untuk menghapus atau "batal" untuk membatalkan.';
    }

    protected function buildHelpContext(array $data): string
    {
        $topic = $data['topic'] ?? null;

        if ($topic === 'finance' || $topic === 'keuangan') {
            return 'Berikan bantuan tentang fitur keuangan. Contoh perintah: catat pengeluaran, catat pemasukan, lihat ringkasan keuangan, lihat transaksi, cek saldo.';
        }

        if ($topic === 'schedule' || $topic === 'jadwal') {
            return 'Berikan bantuan tentang fitur jadwal. Contoh perintah: buat jadwal meeting, buat pengingat, lihat jadwal, cek agenda.';
        }

        if ($topic === 'notes' || $topic === 'catatan') {
            return 'Berikan bantuan tentang fitur catatan. Contoh perintah: buat catatan, simpan catatan, lihat catatan, cari catatan.';
        }

        return 'Berikan bantuan umum tentang fitur yang tersedia: keuangan (catat transaksi, lihat ringkasan), jadwal (buat jadwal, pengingat), dan catatan (simpan catatan).';
    }

    protected function buildOutOfScopeContext(array $data): string
    {
        // This method is no longer used for out_of_scope, but kept for backward compatibility
        // Out of scope questions are now handled by handleOutOfScopeQuestion()
        $topic = $data['topic'] ?? 'topik tersebut';
        $questionType = $data['question_type'] ?? null;

        $context = "User bertanya tentang \"{$topic}\"";
        if ($questionType) {
            $context .= " (tipe: {$questionType})";
        }
        $context .= ".\n\n";

        $context .= 'INSTRUKSI: Coba jawab pertanyaan user dengan pengetahuanmu jika bisa. ';
        $context .= 'Jika tidak bisa, sampaikan dengan sopan. ';
        $context .= 'Sampaikan dengan gaya komunikasi yang sesuai kepribadianmu.';

        return $context;
    }

    protected function buildUnknownContext(array $data): string
    {
        $unclearReason = $data['unclear_reason'] ?? null;

        $context = 'Pesan user tidak jelas atau tidak bisa dipahami';
        if ($unclearReason) {
            $context .= " karena: {$unclearReason}";
        }
        $context .= ".\n\n";

        $context .= 'INSTRUKSI: Sampaikan dengan sopan bahwa kamu tidak memahami maksud pesannya. ';
        $context .= 'Minta user untuk menjelaskan lebih jelas atau memberikan detail lebih lanjut. ';
        $context .= 'Tawarkan bantuan dan berikan contoh perintah yang bisa dimengerti seperti: ';
        $context .= '"catat pengeluaran 50rb untuk makan", "lihat jadwal hari ini", "buat catatan meeting". ';
        $context .= 'Sampaikan dengan ramah sesuai kepribadianmu.';

        return $context;
    }

    /**
     * Format success response.
     */
    protected function formatSuccessResponse(User $user, array $result): string
    {
        return $this->personalizeResponse($user, 'success', $result);
    }

    /**
     * Format error response.
     */
    protected function formatErrorResponse(User $user, array $result): string
    {
        return $this->personalizeResponse($user, 'error', $result);
    }

    /**
     * Format finance summary.
     */
    protected function formatFinanceSummary(User $user, array $summary): string
    {
        return $this->personalizeResponse($user, 'finance_summary', $summary);
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
        return $this->personalizeResponse($user, 'schedules_list', [
            'schedules' => $schedules,
            'period' => $period,
        ]);
    }

    /**
     * Format notes list.
     */
    protected function formatNotesList(User $user, array $notes): string
    {
        return $this->personalizeResponse($user, 'notes_list', ['notes' => $notes]);
    }

    /**
     * Format transaction confirmation.
     */
    protected function formatTransactionConfirmation(User $user, array $entities): string
    {
        return $this->personalizeResponse($user, 'transaction_confirmation', $entities);
    }

    /**
     * Format schedule confirmation.
     */
    protected function formatScheduleConfirmation(User $user, array $entities): string
    {
        return $this->personalizeResponse($user, 'schedule_confirmation', $entities);
    }

    /**
     * Format schedule update confirmation.
     */
    protected function formatScheduleUpdateConfirmation(User $user, array $entities): string
    {
        return $this->personalizeResponse($user, 'schedule_update_confirmation', $entities);
    }

    /**
     * Format note confirmation.
     */
    protected function formatNoteConfirmation(User $user, array $entities): string
    {
        return $this->personalizeResponse($user, 'note_confirmation', $entities);
    }

    /**
     * Format delete confirmation.
     */
    protected function formatDeleteConfirmation(User $user, string $itemType, array $entities): string
    {
        $identifier = $entities['title'] ?? $entities['description'] ?? $entities['note_id'] ?? $entities['schedule_id'] ?? $entities['transaction_id'] ?? 'item tersebut';

        return $this->personalizeResponse($user, 'delete_confirmation', [
            'item_type' => $itemType,
            'identifier' => $identifier,
        ]);
    }

    /**
     * Get help message.
     */
    protected function getHelpMessage(User $user, ?string $topic): string
    {
        return $this->personalizeResponse($user, 'help', ['topic' => $topic]);
    }

    /**
     * Parse user intent from message.
     *
     * @return array{module: string, action: string, entities: array, confidence: float, raw_intent: string}
     */
    public function parseIntent(User $user, string $message, array $conversationHistory = []): array
    {
        return $this->intentParser->parse($user, $message, $conversationHistory);
    }

    /**
     * Get ChatService instance.
     */
    public function getChatService(): ChatService
    {
        return $this->chatService;
    }

    /**
     * Get AiProvider instance.
     */
    public function getAiProvider(): AiProviderInterface
    {
        return $this->aiProvider;
    }
}
