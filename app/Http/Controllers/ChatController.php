<?php

namespace App\Http\Controllers;

use App\Http\Requests\Chat\SendMessageRequest;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\ChatUsageLog;
use App\Services\Ai\ChatOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __construct(protected ChatOrchestrator $chatOrchestrator) {}

    /**
     * Display the chat interface.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $threads = $user->chatThreads()
            ->orderByDesc('last_message_at')
            ->limit(20)
            ->get()
            ->map(fn (ChatThread $thread) => [
                'id' => $thread->id,
                'title' => $thread->title ?? 'Chat Baru',
                'lastMessageAt' => $thread->last_message_at?->diffForHumans(),
            ]);

        return Inertia::render('chat/Index', [
            'threads' => $threads,
            'currentThread' => null,
            'messages' => [],
            'chatLimit' => [
                'daily_limit' => $user->getDailyChatLimit(),
                'used_today' => ChatUsageLog::getTodayCount($user->id),
                'remaining' => $user->getRemainingChats(),
                'is_limited' => $user->hasReachedChatLimit(),
            ],
            'subscriptionInfo' => $user->getSubscriptionInfo(),
        ]);
    }

    /**
     * Display a specific chat thread.
     */
    public function show(Request $request, ChatThread $thread): Response
    {
        $this->authorize('view', $thread);

        $user = $request->user();

        $threads = $user->chatThreads()
            ->orderByDesc('last_message_at')
            ->limit(20)
            ->get()
            ->map(fn (ChatThread $t) => [
                'id' => $t->id,
                'title' => $t->title ?? 'Chat Baru',
                'lastMessageAt' => $t->last_message_at?->diffForHumans(),
            ]);

        $messages = $thread->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn (ChatMessage $message) => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'createdAt' => $message->created_at->format('H:i'),
            ]);

        return Inertia::render('chat/Index', [
            'threads' => $threads,
            'currentThread' => [
                'id' => $thread->id,
                'title' => $thread->title ?? 'Chat Baru',
            ],
            'messages' => $messages,
            'chatLimit' => [
                'daily_limit' => $user->getDailyChatLimit(),
                'used_today' => ChatUsageLog::getTodayCount($user->id),
                'remaining' => $user->getRemainingChats(),
                'is_limited' => $user->hasReachedChatLimit(),
            ],
            'subscriptionInfo' => $user->getSubscriptionInfo(),
        ]);
    }

    /**
     * Send a message and get AI response.
     */
    public function sendMessage(SendMessageRequest $request): JsonResponse
    {
        $user = $request->user();
        $threadId = $request->input('thread_id');
        $messageContent = $request->input('message');

        // Check chat limit
        if ($user->hasReachedChatLimit()) {
            return response()->json([
                'error' => 'Anda telah mencapai batas chat harian. Upgrade ke Full Member untuk mendapatkan lebih banyak chat.',
                'limit_reached' => true,
                'remaining' => 0,
            ], 429);
        }

        // Get or create thread
        if ($threadId) {
            $thread = ChatThread::where('id', $threadId)
                ->where('user_id', $user->id)
                ->firstOrFail();
        } else {
            $thread = ChatThread::create([
                'user_id' => $user->id,
                'title' => $this->generateThreadTitle($messageContent),
                'last_message_at' => now(),
            ]);
        }

        // Save user message
        $userMessage = ChatMessage::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $messageContent,
        ]);

        // Get conversation history
        $history = $thread->messages()
            ->where('id', '!=', $userMessage->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        // Process message through ChatOrchestrator
        try {
            $result = $this->chatOrchestrator->processMessage($user, $messageContent, $thread, $history);
            $aiResponse = $result['response'];
        } catch (\Exception $e) {
            Log::error('Chat error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'error' => 'Maaf, terjadi kesalahan saat memproses pesan. Silakan coba lagi.',
            ], 500);
        }

        // Save assistant message
        $assistantMessage = ChatMessage::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => $aiResponse,
        ]);

        // Increment chat usage count
        ChatUsageLog::incrementForUser($user->id);

        // Update thread last message time
        $thread->update(['last_message_at' => now()]);

        return response()->json([
            'thread' => [
                'id' => $thread->id,
                'title' => $thread->title,
            ],
            'userMessage' => [
                'id' => $userMessage->id,
                'role' => 'user',
                'content' => $userMessage->content,
                'createdAt' => $userMessage->created_at->format('H:i'),
            ],
            'assistantMessage' => [
                'id' => $assistantMessage->id,
                'role' => 'assistant',
                'content' => $assistantMessage->content,
                'createdAt' => $assistantMessage->created_at->format('H:i'),
            ],
            'chatLimit' => [
                'daily_limit' => $user->getDailyChatLimit(),
                'used_today' => ChatUsageLog::getTodayCount($user->id),
                'remaining' => $user->getRemainingChats(),
                'is_limited' => $user->hasReachedChatLimit(),
            ],
        ]);
    }

    /**
     * Delete a chat thread.
     */
    public function destroy(ChatThread $thread): JsonResponse
    {
        $this->authorize('delete', $thread);

        $thread->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Generate a thread title from the first message.
     */
    protected function generateThreadTitle(string $message): string
    {
        $title = mb_substr($message, 0, 50);

        if (mb_strlen($message) > 50) {
            $title .= '...';
        }

        return $title;
    }
}
