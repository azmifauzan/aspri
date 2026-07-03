<?php

namespace App\Services\Ai;

use App\Jobs\ExtractConversationMemories;
use App\Models\ChatThread;
use App\Models\PendingAction;
use App\Models\User;
use App\Services\Admin\SettingsService;
use App\Services\Plugin\PluginManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChatOrchestrator
{
    /**
     * Maximum number of agent-loop iterations (tool calls) per message.
     */
    protected const MAX_ITERATIONS = 3;

    public function __construct(
        protected ChatService $chatService,
        protected ActionExecutorService $actionExecutor,
        protected AiProviderInterface $aiProvider,
        protected PluginManager $pluginManager,
        protected ConversationMemoryService $memoryService,
        protected SettingsService $settingsService,
        protected ToolRegistry $toolRegistry
    ) {}

    /**
     * Process a user message and return the assistant response.
     *
     * @return array{response: string, action_taken: bool, pending_action: array|null}
     */
    public function processMessage(User $user, string $message, ChatThread $thread, array $conversationHistory = []): array
    {
        [$callName, $lang] = $this->templateContext($user, $message);

        // Fetch memory context
        $contextLength = (int) $this->settingsService->get('ai_context_length', 32000);
        $memoryBudget = (int) ($contextLength * 0.15); // 15% for memory
        $memoryContext = $this->memoryService->buildMemoryContext($user, $memoryBudget);

        // ------------------------------------------------------------------
        // Step 1: Check for a pending action. A confirmation/cancellation
        // keyword resolves it with ZERO LLM calls and returns immediately
        // (this path does NOT dispatch memory extraction).
        // ------------------------------------------------------------------
        $pendingAction = PendingAction::where('thread_id', $thread->id)
            ->pending()
            ->latest()
            ->first();

        if ($pendingAction) {
            $messageLower = strtolower(trim($message));

            $isConfirmation = (bool) preg_match(
                '/^(ya|iya|yep|yap|yes|ok|oke|okay|oks|setuju|benar|betul|bener|lanjut|simpan|konfirmasi|confirm|y)[\s\.,!?]*$/i',
                $messageLower
            );

            $isCancellation = (bool) preg_match(
                '/^(tidak|gak|ngak|nggak|ga|nope|no|batal|cancel|batalkan|jangan|stop|n)[\s\.,!?]*$/i',
                $messageLower
            );

            if ($isConfirmation) {
                Log::info('Confirmation detected by keyword match', [
                    'pending_action_id' => $pendingAction->id,
                ]);

                return $this->executeConfirmedAction($pendingAction, $callName, $lang);
            }

            if ($isCancellation) {
                Log::info('Cancellation detected by keyword match', [
                    'pending_action_id' => $pendingAction->id,
                ]);

                $pendingAction->cancel();

                return [
                    'response' => ResponseTemplates::cancellation($callName, $lang),
                    'action_taken' => false,
                    'pending_action' => null,
                ];
            }

            // A new, non-confirmation request supersedes the pending action.
            Log::info('Cancelling stale pending action due to new request', [
                'pending_action_id' => $pendingAction->id,
            ]);
            $pendingAction->cancel();
        }

        // ------------------------------------------------------------------
        // Step 2 & 3: Agent loop.
        // ------------------------------------------------------------------
        $result = $this->runAgentLoop($user, $thread, $message, $conversationHistory, $memoryContext, $callName, $lang);

        // Dispatch memory extraction job (with 15 min delay as per plan).
        // Debounce so only the last message after an idle window triggers it.
        $dispatchTime = now()->toDateTimeString();
        Cache::put("memory_extraction_last_dispatch_{$thread->id}", $dispatchTime, now()->addMinutes(30));

        $thread->update(['last_message_at' => now()]);

        ExtractConversationMemories::dispatch($thread, $dispatchTime)->delay(now()->addMinutes(15));

        return $result;
    }

    /**
     * Run the single native tool-use agent loop.
     *
     * @return array{response: string, action_taken: bool, pending_action: array|null}
     */
    protected function runAgentLoop(
        User $user,
        ChatThread $thread,
        string $message,
        array $conversationHistory,
        string $memoryContext,
        string $callName,
        string $lang
    ): array {
        $messages = $this->buildMessages($user, $message, $conversationHistory, $memoryContext);
        $functions = $this->toolRegistry->definitions($user);

        for ($iteration = 0; $iteration < self::MAX_ITERATIONS; $iteration++) {
            $response = $this->aiProvider->chat($messages, ['functions' => $functions]);

            // Plain text response → this is the final reply.
            if (is_string($response)) {
                // Check if the model wrote a fake confirmation without calling the tool.
                // Some models (e.g. DeepSeek) write "Mohon konfirmasi... Balas ya"
                // as plain text instead of calling create_transaction.
                if ($this->looksLikeFakeConfirmation($response, $message)) {
                    Log::warning('Model returned fake confirmation text, retrying with tool_choice=required', [
                        'thread_id' => $thread->id,
                        'message_snippet' => mb_substr($response, 0, 100),
                    ]);

                    // Retry with tool_choice forced to 'required' so the model MUST call a tool.
                    $response = $this->aiProvider->chat($messages, [
                        'functions' => $functions,
                        'tool_choice' => 'required',
                    ]);

                    // If still plain text after forced tool_choice, return as-is.
                    if (is_string($response)) {
                        return [
                            'response' => $response,
                            'action_taken' => false,
                            'pending_action' => null,
                        ];
                    }
                } else {
                    return [
                        'response' => $response,
                        'action_taken' => false,
                        'pending_action' => null,
                    ];
                }
            }

            // Provider now returns an array of tool calls: [{function_name, arguments}, ...]
            // Normalize legacy single-call format for backward compatibility.
            $toolCalls = $this->normalizeToolCalls($response);

            if (empty($toolCalls)) {
                return [
                    'response' => $this->loopFallback($callName, $lang),
                    'action_taken' => false,
                    'pending_action' => null,
                ];
            }

            // --- Process all tool calls in this turn ---
            $mutatingCalls = [];   // tool calls that need confirmation
            $readResults = [];     // messages to feed back for read tools

            foreach ($toolCalls as $call) {
                $toolName = $call['function_name'] ?? null;
                $arguments = $call['arguments'] ?? [];

                if (! $toolName) {
                    continue;
                }

                $tool = $this->toolRegistry->resolve($toolName);

                if ($tool === null) {
                    Log::warning('Unknown tool requested by model', ['tool' => $toolName]);
                    $readResults[] = [
                        'role' => 'user',
                        'content' => "[Hasil tool {$toolName}]: tool tidak dikenali. Sampaikan ke user bahwa permintaan itu belum bisa dilakukan dengan gaya bahasamu.",
                    ];
                    continue;
                }

                if ($tool['mutates'] === true) {
                    // Notes create/update are non-destructive: execute directly.
                    if ($tool['module'] === 'notes' && in_array($tool['action_type'], ['create_note', 'update_note'], true)) {
                        $result = $this->actionExecutor->executeDirectNotesAction($user, $tool['action_type'], $arguments);
                        $readResults[] = $this->toolResultMessage($toolName, $result['message'], $result['data'] ?? null);
                        continue;
                    }

                    // Mutating tools → collect for batch confirmation
                    $mutatingCalls[] = [
                        'tool' => $tool,
                        'tool_name' => $toolName,
                        'arguments' => $arguments,
                    ];
                    continue;
                }

                // --- Read tools ---
                if ($tool['module'] === 'plugin') {
                    $readResults[] = $this->executePluginTool($user, $tool, $arguments, $toolName);
                    continue;
                }

                $templated = $this->renderReadTool($user, $tool, $arguments, $callName, $lang);

                if ($templated !== null) {
                    // Read tool with a deterministic template → collect as final reply candidate
                    $readResults[] = $templated;
                    continue;
                }

                // Read tool without a template → feed result back to model
                $data = $this->executeReadTool($user, $tool, $arguments);
                $readResults[] = $this->toolResultMessage($toolName, null, $data);
            }

            // --- If we have mutating calls, create pending action(s) and stop ---
            if (! empty($mutatingCalls)) {
                // Single mutating call → existing behavior (backward compatible)
                if (count($mutatingCalls) === 1) {
                    $mc = $mutatingCalls[0];
                    $pendingAction = $this->createPendingAction($user, $thread, $mc['tool']['action_type'], $mc['tool']['module'], $mc['arguments']);

                    return [
                        'response' => $this->confirmationFor($mc['tool'], $mc['arguments'], $callName, $lang),
                        'action_taken' => false,
                        'pending_action' => $pendingAction->toArray(),
                    ];
                }

                // Multiple mutating calls → batch confirmation
                return $this->createBatchPendingAction($user, $thread, $mutatingCalls, $callName, $lang);
            }

            // --- No mutating calls: feed read results back and continue loop ---
            foreach ($readResults as $result) {
                if (is_string($result)) {
                    // Deterministic template → final reply
                    return [
                        'response' => $result,
                        'action_taken' => false,
                        'pending_action' => null,
                    ];
                }
                $messages[] = $result;
            }
        }

        Log::warning('Agent loop reached max iterations without a text response', [
            'thread_id' => $thread->id,
        ]);

        return [
            'response' => $this->loopFallback($callName, $lang),
            'action_taken' => false,
            'pending_action' => null,
        ];
    }

    /**
     * Detect whether a plain-text response looks like a fake confirmation
     * (model wrote "mohon konfirmasi... Balas ya" instead of calling the tool).
     *
     * Only triggers when the user's original message looks like a create/record
     * request — otherwise we'd wrongly retry normal conversational replies.
     */
    protected function looksLikeFakeConfirmation(string $response, string $userMessage): bool
    {
        // The user message must look like a create/record request
        $isActionRequest = (bool) preg_match(
            '/\b(catat|record|tambah|buat|simpan|create|save|bayar|beli|jual|terima|topup|transfer|jajan| isi|withdraw|tarik)\b/i',
            $userMessage
        );

        if (! $isActionRequest) {
            return false;
        }

        // The response must look like a confirmation prompt with amounts
        $hasConfirmKeyword = (bool) preg_match(
            '/(mohon konfirmasi|please confirm|konfirmasi transaksi|balas.*ya|reply.*yes|balas.*batal)/i',
            $response
        );

        $hasAmount = (bool) preg_match('/(rp\.?\s*\d|Rp\d)/i', $response);

        return $hasConfirmKeyword && $hasAmount;
    }

    /**
     * Normalize the provider response into a list of tool calls.
     *
     * Providers now return: [{function_name, arguments}, ...]
     * Legacy format: {function_name, arguments}
     *
     * @param  array<string, mixed>  $response
     * @return array<int, array{function_name: string, arguments: array}>
     */
    protected function normalizeToolCalls(array $response): array
    {
        // New format: array of calls (detected by sequential numeric keys)
        if (array_is_list($response) && isset($response[0]['function_name'])) {
            return $response;
        }

        // Legacy format: single call
        if (isset($response['function_name'])) {
            return [['function_name' => $response['function_name'], 'arguments' => $response['arguments'] ?? []]];
        }

        return [];
    }

    /**
     * Create a batch pending action for multiple mutating tool calls.
     *
     * @param  array<int, array{tool: array, tool_name: string, arguments: array}>  $mutatingCalls
     * @return array{response: string, action_taken: bool, pending_action: array|null}
     */
    protected function createBatchPendingAction(User $user, ChatThread $thread, array $mutatingCalls, string $callName, string $lang): array
    {
        $batchPayload = [];
        $confirmations = [];

        foreach ($mutatingCalls as $mc) {
            $tool = $mc['tool'];
            $arguments = $mc['arguments'];

            $batchPayload[] = [
                'action_type' => $tool['action_type'],
                'module' => $tool['module'],
                'payload' => $arguments,
            ];

            $confirmations[] = $this->confirmationFor($tool, $arguments, $callName, $lang);
        }

        $pendingAction = PendingAction::create([
            'user_id' => $user->id,
            'thread_id' => $thread->id,
            'action_type' => 'batch',
            'module' => 'batch',
            'payload' => ['actions' => $batchPayload],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(5),
        ]);

        Log::info('Batch pending action created', [
            'pending_action_id' => $pendingAction->id,
            'action_count' => count($batchPayload),
        ]);

        // Build a combined confirmation message listing all actions
        $response = $this->batchConfirmation($confirmations, $callName, $lang);

        return [
            'response' => $response,
            'action_taken' => false,
            'pending_action' => $pendingAction->toArray(),
        ];
    }

    /**
     * Build a combined confirmation message from multiple action confirmations.
     * Strips the per-item footer and call name so only the details are listed.
     *
     * @param  array<int, string>  $confirmations
     */
    protected function batchConfirmation(array $confirmations, string $callName, string $lang): string
    {
        $header = $lang === 'en'
            ? "{$callName}, please confirm the following transactions:"
            : "{$callName}, mohon konfirmasi transaksi berikut:";

        $lines = [$header, ''];

        foreach ($confirmations as $i => $conf) {
            // Strip the per-item footer ("Balas ya..." / "Reply yes...")
            $conf = preg_replace('/\n*Balas "ya"[^\n]*\.$/', '', $conf);
            $conf = preg_replace('/\n*Reply "yes"[^\n]*\.$/', '', $conf);

            // Strip the per-item header (first line containing call name + "mohon konfirmasi")
            $conf = preg_replace('/^[^\n]*mohon konfirmasi transaksi berikut:\n*/', '', $conf);
            $conf = preg_replace('/^[^\n]*please confirm this transaction:\n*/', '', $conf);

            $lines[] = ($i + 1).'. '.trim($conf);
            $lines[] = '';
        }

        $lines[] = ResponseTemplates::confirmFooter($lang);

        return implode("\n", $lines);
    }

    /**
     * Execute a confirmed pending action with no LLM call and build a
     * deterministic success/error response.
     *
     * @return array{response: string, action_taken: bool, pending_action: array|null}
     */
    protected function executeConfirmedAction(PendingAction $pendingAction, string $callName, string $lang): array
    {
        $pendingAction->confirm();

        $result = $this->actionExecutor->execute($pendingAction);

        Log::info('Confirmed action executed', [
            'pending_action_id' => $pendingAction->id,
            'success' => $result['success'],
        ]);

        return [
            'response' => ResponseTemplates::actionResult($result['message'], $callName, $lang),
            'action_taken' => (bool) $result['success'],
            'pending_action' => null,
        ];
    }

    /**
     * Build the confirmation message for a mutating tool call.
     *
     * @param  array<string, mixed>  $tool
     * @param  array<string, mixed>  $arguments
     */
    protected function confirmationFor(array $tool, array $arguments, string $callName, string $lang): string
    {
        return match ($tool['action_type']) {
            'create_transaction' => ResponseTemplates::transactionConfirmation($arguments, $callName, $lang),
            'create_schedule' => ResponseTemplates::scheduleConfirmation($arguments, $callName, $lang),
            'update_schedule' => ResponseTemplates::scheduleUpdateConfirmation($arguments, $callName, $lang),
            'delete_transaction' => ResponseTemplates::deleteConfirmation(
                $lang === 'en' ? 'transaction' : 'transaksi',
                $this->deleteIdentifier($arguments),
                $callName,
                $lang
            ),
            'delete_schedule' => ResponseTemplates::deleteConfirmation(
                $lang === 'en' ? 'schedule' : 'jadwal',
                $this->deleteIdentifier($arguments),
                $callName,
                $lang
            ),
            'delete_note' => ResponseTemplates::deleteConfirmation(
                $lang === 'en' ? 'note' : 'catatan',
                $this->deleteIdentifier($arguments),
                $callName,
                $lang
            ),
            default => ResponseTemplates::confirmFooter($lang),
        };
    }

    /**
     * Derive a human identifier for a delete confirmation.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function deleteIdentifier(array $arguments): string
    {
        return (string) (
            $arguments['title']
            ?? $arguments['note_id']
            ?? $arguments['schedule_id']
            ?? $arguments['transaction_id']
            ?? 'item tersebut'
        );
    }

    /**
     * Render a read tool result through its deterministic template, if one
     * exists. Returns null when the tool has no template (caller continues loop).
     *
     * @param  array<string, mixed>  $tool
     * @param  array<string, mixed>  $arguments
     */
    protected function renderReadTool(User $user, array $tool, array $arguments, string $callName, string $lang): ?string
    {
        return match ($tool['method'] ?? null) {
            'getFinanceSummary' => ResponseTemplates::financeSummary(
                $this->actionExecutor->getFinanceSummary($user, $arguments['period'] ?? null),
                $callName,
                $lang
            ),
            'getSchedules' => ResponseTemplates::schedulesList(
                $this->actionExecutor->getSchedules($user, $arguments['period'] ?? null),
                $arguments['period'] ?? null,
                $callName,
                $lang
            ),
            'getNotes' => ResponseTemplates::notesList(
                $this->actionExecutor->getNotes(
                    $user,
                    $arguments['search'] ?? null,
                    $arguments['tags'] ?? null,
                    $arguments['limit'] ?? 5
                ),
                $callName,
                $lang
            ),
            default => null, // e.g. getTransactions has no template
        };
    }

    /**
     * Execute a read tool that has no template and return its raw data.
     *
     * @param  array<string, mixed>  $tool
     * @param  array<string, mixed>  $arguments
     * @return array<mixed>
     */
    protected function executeReadTool(User $user, array $tool, array $arguments): array
    {
        return match ($tool['method'] ?? null) {
            'getTransactions' => $this->actionExecutor->getTransactions(
                $user,
                $arguments['period'] ?? null,
                $arguments['tx_type'] ?? null,
                $arguments['limit'] ?? 5
            ),
            default => [],
        };
    }

    /**
     * Execute a plugin tool and return the tool-result message to feed back.
     *
     * @param  array<string, mixed>  $tool
     * @param  array<string, mixed>  $arguments
     * @return array{role: string, content: string}
     */
    protected function executePluginTool(User $user, array $tool, array $arguments, string $toolName): array
    {
        try {
            $instance = $this->pluginManager->getPlugin($tool['plugin_slug']);

            if (! $instance) {
                return $this->toolResultMessage($toolName, 'Plugin tidak tersedia.', null);
            }

            $result = $instance->handleChatIntent($user->id, $tool['action'], $arguments);

            return $this->toolResultMessage($toolName, $result['message'] ?? null, $result['data'] ?? null);
        } catch (\Throwable $e) {
            Log::error('Plugin tool execution error', [
                'plugin' => $tool['plugin_slug'] ?? null,
                'action' => $tool['action'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return $this->toolResultMessage($toolName, 'Terjadi kesalahan saat menjalankan plugin.', null);
        }
    }

    /**
     * Build a tool-result message to append to the conversation so the model
     * can compose a natural reply. The provider abstraction does not support
     * native tool_use/tool_result blocks, so we use a plain user-role message.
     *
     * @return array{role: string, content: string}
     */
    protected function toolResultMessage(string $toolName, ?string $message, mixed $data): array
    {
        $parts = [];

        if ($message !== null && $message !== '') {
            $parts[] = $message;
        }

        if ($data !== null && $data !== []) {
            $parts[] = json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        $payload = implode("\n", $parts);

        return [
            'role' => 'user',
            'content' => "[Hasil tool {$toolName}]: {$payload}\n\nSampaikan hasil ini ke user dengan gaya bahasamu, jangan mengubah angka atau data apa pun.",
        ];
    }

    /**
     * Build the initial message array for the agent loop.
     *
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     * @return array<int, array{role: string, content: string}>
     */
    protected function buildMessages(User $user, string $message, array $conversationHistory, string $memoryContext): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->chatService->buildSystemPrompt($user, $memoryContext)],
        ];

        foreach (array_slice($conversationHistory, -10) as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        // Append the current message unless it is already the last history entry.
        if (empty($conversationHistory) || end($conversationHistory)['content'] !== $message) {
            $messages[] = ['role' => 'user', 'content' => $message];
        }

        // Inject the current date/time as a prefix on the LAST message (never the
        // system block at index 0) so the cached system prefix stays byte-stable
        // while the model still receives the current time for relative date parsing.
        $lastIndex = count($messages) - 1;
        $now = now();
        $messages[$lastIndex]['content'] = "[Konteks waktu saat ini: {$now->format('l, d F Y')}, {$now->format('H:i')}]\n\n".$messages[$lastIndex]['content'];

        return $messages;
    }

    /**
     * Create a pending action for confirmation.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function createPendingAction(User $user, ChatThread $thread, string $actionType, string $module, array $payload): PendingAction
    {
        $pendingAction = PendingAction::create([
            'user_id' => $user->id,
            'thread_id' => $thread->id,
            'action_type' => $actionType,
            'module' => $module,
            'payload' => $payload,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(5),
        ]);

        Log::info('Pending action created', [
            'pending_action_id' => $pendingAction->id,
            'action_type' => $actionType,
            'module' => $module,
        ]);

        return $pendingAction;
    }

    /**
     * Deterministic fallback message when the loop exhausts its iterations.
     */
    protected function loopFallback(string $callName, string $lang): string
    {
        return $lang === 'en'
            ? "{$callName}, sorry — I couldn't complete that just now. Could you try rephrasing?"
            : "{$callName}, maaf, aku belum bisa menyelesaikan itu sekarang. Boleh coba ulangi dengan kalimat lain?";
    }

    /**
     * Build the [callName, lang] context used by ResponseTemplates.
     *
     * @return array{0: string, 1: string}
     */
    protected function templateContext(User $user, string $message): array
    {
        $profile = $user->profile;
        $callPref = $profile?->call_preference ?? 'Kak';
        $callName = trim("{$callPref} {$user->name}");
        $lang = ResponseTemplates::detectLanguage($message);

        return [$callName, $lang];
    }
}
