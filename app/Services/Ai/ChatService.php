<?php

namespace App\Services\Ai;

use App\Models\User;

class ChatService
{
    public function __construct(
        protected AiProviderInterface $provider,
        protected \App\Services\Admin\SettingsService $settingsService
    ) {}

    /**
     * Build the system prompt based on user's persona settings.
     * The AI will detect and respond in the same language as the user's input.
     */
    public function buildSystemPrompt(User $user, string $memoryContext = ''): string
    {
        $profile = $user->profile;

        $callPreference = $profile?->call_preference ?? 'Kak';
        $aspriName = $profile?->aspri_name ?? 'ASPRI';
        $aspriPersona = $profile?->aspri_persona ?? 'friendly and helpful assistant';

        $prompt = <<<PROMPT
You are {$aspriName}, {$aspriPersona}.
You are an AI-powered personal assistant helping manage daily schedules and finances.

User information:
- Name: {$user->name}
- Preferred address: {$callPreference} {$user->name}

Your capabilities:
1. Help record and manage financial transactions (income/expenses)
2. Help manage schedules and reminders
3. Provide monthly financial summaries
4. Answer general questions helpfully

IMPORTANT - Language rule:
- Always detect the language of the user's message and respond in that SAME language.
- If the user writes in Indonesian, respond in Indonesian.
- If the user writes in English, respond in English.
- Always address the user as "{$callPreference}" regardless of the language used.

Communication guidelines:
- Keep responses concise and clear
- Be friendly but polite
- If asked to do something beyond your capabilities, explain politely

🔴 CRITICAL: Always respond to the user's LATEST message. Do NOT get stuck on previous transactions mentioned in memory or conversation. If the user says "lanjut" / "anjut" / "catat lagi" — they want a NEW transaction, not to repeat the old one. Prioritize what the user JUST typed over anything from memory.

⚠️ CRITICAL TOOL USAGE RULES ⚠️
You have access to FUNCTION TOOLS (create_transaction, create_schedule, etc.). 
When the user wants you to PERFORM an action (record, save, create, etc.), you MUST call the appropriate tool function — NEVER just reply with plain text claiming you have done it.

MULTIPLE ACTIONS IN ONE MESSAGE:
- If the user mentions MULTIPLE transactions or actions in a single message, you MUST call the tool ONCE FOR EACH action.
- Example: "catat bensin 30rb sama jajan 10rb" → call create_transaction TWICE (one for bensin, one for jajan).
- Example: "catat gaji 5jt sama transfer bunda 2jt" → call create_transaction TWICE.
- Do NOT combine multiple transactions into one tool call. Each action gets its own tool call.

For financial transactions:
- When user says "catat", "record", "tambah", or mentions an amount AND type (income/expense) → IMMEDIATELY call create_transaction tool
- If category is missing, infer or use "Lainnya" — DO NOT ask in plain text first, use the tool
- After tool call, the system will handle confirmation — you do NOT confirm manually

For schedules:
- When user wants to create/change/delete schedule → ALWAYS call the corresponding tool
- Use create_schedule / update_schedule / delete_schedule — NEVER do it in plain text
PROMPT;

        if (! empty($memoryContext)) {
            $prompt .= "\n\nInformasi penting yang kamu ingat tentang user ini:\n".$memoryContext;
        }

        return $prompt;
    }

    /**
     * Format messages for AI provider.
     *
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     */
    public function formatMessages(User $user, string $userMessage, array $conversationHistory = [], string $memoryContext = ''): array
    {
        $systemPrompt = $this->buildSystemPrompt($user, $memoryContext);

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
        ];

        // Dynamic history pruning based on token budget
        $contextLength = (int) $this->settingsService->get('ai_context_length', 32000);

        // Calculate budget for history: Total - SystemPrompt - UserMessage - MemoryContext (already in SystemPrompt) - ResponseBuffer
        $usedTokens = $this->estimateTokenCount($systemPrompt) + $this->estimateTokenCount($userMessage);
        $availableHistoryTokens = $contextLength - $usedTokens - 2000; // 2000 tokens buffer for AI response

        // Limit to last 30 messages max, then prune by tokens
        $recentHistory = array_slice($conversationHistory, -30);

        $prunedHistory = [];
        $currentHistoryTokens = 0;

        // Iterate backwards to keep the most recent messages
        foreach (array_reverse($recentHistory) as $message) {
            $tokens = $this->estimateTokenCount($message['content']);

            if ($currentHistoryTokens + $tokens > $availableHistoryTokens) {
                break;
            }

            $prunedHistory[] = $message;
            $currentHistoryTokens += $tokens;
        }

        // Add pruned history in correct chronological order
        foreach (array_reverse($prunedHistory) as $message) {
            $messages[] = [
                'role' => $message['role'],
                'content' => $message['content'],
            ];
        }

        // Add current user message
        $messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        return $messages;
    }

    /**
     * Estimate token count for a string (heuristic: ~4 chars per token).
     */
    protected function estimateTokenCount(string $text): int
    {
        // Simple heuristic for estimation
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Send a message and get a response.
     *
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     */
    public function sendMessage(User $user, string $message, array $conversationHistory = [], string $memoryContext = ''): string
    {
        $messages = $this->formatMessages($user, $message, $conversationHistory, $memoryContext);

        return $this->provider->chat($messages);
    }

    /**
     * Send a message and stream the response.
     *
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     */
    public function streamMessage(User $user, string $message, callable $callback, array $conversationHistory = [], string $memoryContext = ''): string
    {
        $messages = $this->formatMessages($user, $message, $conversationHistory, $memoryContext);

        return $this->provider->chatStream($messages, $callback);
    }
}
