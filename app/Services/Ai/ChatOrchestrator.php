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
                return [
                    'response' => $response,
                    'action_taken' => false,
                    'pending_action' => null,
                ];
            }

            $toolName = $response['function_name'] ?? null;
            $arguments = $response['arguments'] ?? [];

            if (! $toolName) {
                // Malformed tool call without a function name.
                return [
                    'response' => $this->loopFallback($callName, $lang),
                    'action_taken' => false,
                    'pending_action' => null,
                ];
            }

            $tool = $this->toolRegistry->resolve($toolName);

            if ($tool === null) {
                Log::warning('Unknown tool requested by model', ['tool' => $toolName]);
                $messages[] = [
                    'role' => 'user',
                    'content' => "[Hasil tool {$toolName}]: tool tidak dikenali. Sampaikan ke user bahwa permintaan itu belum bisa dilakukan dengan gaya bahasamu.",
                ];

                continue;
            }

            // --- Mutating tools → confirmation via PendingAction (stop loop) ---
            if ($tool['mutates'] === true) {
                // Notes create/update are non-destructive: execute directly and
                // let the model frame the confirmation (continue the loop).
                if ($tool['module'] === 'notes' && in_array($tool['action_type'], ['create_note', 'update_note'], true)) {
                    $result = $this->actionExecutor->executeDirectNotesAction($user, $tool['action_type'], $arguments);
                    $messages[] = $this->toolResultMessage($toolName, $result['message'], $result['data'] ?? null);

                    continue;
                }

                $pendingAction = $this->createPendingAction($user, $thread, $tool['action_type'], $tool['module'], $arguments);

                return [
                    'response' => $this->confirmationFor($tool, $arguments, $callName, $lang),
                    'action_taken' => false,
                    'pending_action' => $pendingAction->toArray(),
                ];
            }

            // --- Read tools ---
            if ($tool['module'] === 'plugin') {
                $messages[] = $this->executePluginTool($user, $tool, $arguments, $toolName);

                continue;
            }

            $templated = $this->renderReadTool($user, $tool, $arguments, $callName, $lang);

            if ($templated !== null) {
                // Read tool with a deterministic template → final reply, stop loop.
                return [
                    'response' => $templated,
                    'action_taken' => false,
                    'pending_action' => null,
                ];
            }

            // Read tool without a template (e.g. view_transactions) → feed the
            // result back and let the model compose the reply.
            $data = $this->executeReadTool($user, $tool, $arguments);
            $messages[] = $this->toolResultMessage($toolName, null, $data);
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
