<?php

namespace App\Services\Ai;

use App\Models\User;

class ChatService
{
    protected AiProviderInterface $provider;

    public function __construct(AiProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Build the system prompt based on user's persona settings.
     * The AI will detect and respond in the same language as the user's input.
     */
    public function buildSystemPrompt(User $user): string
    {
        $profile = $user->profile;

        $callPreference = $profile?->call_preference ?? 'Kak';
        $aspriName = $profile?->aspri_name ?? 'ASPRI';
        $aspriPersona = $profile?->aspri_persona ?? 'friendly and helpful assistant';

        $currentDate = now()->format('l, d F Y');
        $currentTime = now()->format('H:i');

        return <<<PROMPT
You are {$aspriName}, {$aspriPersona}.
You are an AI-powered personal assistant helping manage daily schedules and finances.

User information:
- Name: {$user->name}
- Preferred address: {$callPreference} {$user->name}

Current time:
- Date: {$currentDate}
- Time: {$currentTime}

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

For financial transactions, when the user wants to record:
- Ask for necessary details (amount, category, description) if not mentioned
- Confirm before saving data

For schedules, when the user wants to create:
- Ask for the time and event title if not mentioned
- Confirm before saving
PROMPT;
    }

    /**
     * Format messages for AI provider.
     *
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     */
    public function formatMessages(User $user, string $userMessage, array $conversationHistory = []): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt($user),
            ],
        ];

        // Add conversation history (limit to last 10 exchanges)
        $historyLimit = array_slice($conversationHistory, -20);
        foreach ($historyLimit as $message) {
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
     * Send a message and get a response.
     *
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     */
    public function sendMessage(User $user, string $message, array $conversationHistory = []): string
    {
        $messages = $this->formatMessages($user, $message, $conversationHistory);

        return $this->provider->chat($messages);
    }

    /**
     * Send a message and stream the response.
     *
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     */
    public function streamMessage(User $user, string $message, callable $callback, array $conversationHistory = []): string
    {
        $messages = $this->formatMessages($user, $message, $conversationHistory);

        return $this->provider->chatStream($messages, $callback);
    }
}
