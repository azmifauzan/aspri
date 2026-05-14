<?php

namespace App\Services\Ai;

use App\Models\ChatThread;
use App\Models\ConversationMemory;
use App\Models\User;
use App\Services\Admin\SettingsService;
use Illuminate\Support\Facades\Log;

class ConversationMemoryService
{
    public function __construct(
        protected AiProviderInterface $provider,
        protected SettingsService $settingsService
    ) {}

    /**
     * Extract memories from a chat thread and save them for the user.
     */
    public function extractMemoriesFromThread(ChatThread $thread, User $user): void
    {
        // 1. Get thread messages
        $messages = $thread->messages()
            ->orderBy('created_at', 'asc')
            ->get();

        if ($messages->isEmpty()) {
            return;
        }

        // 2. Format conversation text for the AI
        $conversationText = $messages->map(fn ($m) => "{$m->role}: {$m->content}")->join("\n");

        // 3. Prepare extraction prompt
        $prompt = <<<PROMPT
Kamu adalah sistem memory untuk asisten AI. Analisis percakapan berikut dan ekstrak
informasi penting yang harus diingat tentang user ini untuk percakapan mendatang.

Percakapan:
{$conversationText}

Ekstrak informasi dalam format JSON array. Setiap item harus memiliki:
- "type": "fact" | "preference" | "event" | "pattern"
- "content": Ringkasan singkat dalam 1-2 kalimat (bahasa yang sama dengan user)
- "importance": 1-5 (5 = sangat penting, 1 = mungkin berguna)
- "metadata": {"module": "finance|schedule|general", "tags": []}

Panduan kepentingan:
- 5: Informasi keuangan kritis, preferensi utama, data yang sering digunakan
- 4: Kebiasaan rutin, preferensi yang dinyatakan eksplisit
- 3: Informasi berguna tapi tidak kritis
- 2: Konteks tambahan
- 1: Informasi yang mungkin sudah usang

Jangan ekstrak: salam biasa, konfirmasi pendek, pertanyaan teknis satu-kali.

Kembalikan HANYA JSON array, tanpa penjelasan tambahan.
PROMPT;

        try {
            $response = $this->provider->chat([
                ['role' => 'system', 'content' => 'You are a memory extraction system. Return ONLY a JSON array.'],
                ['role' => 'user', 'content' => $prompt],
            ], [
                'temperature' => 0.2,
                'max_tokens' => 2048,
            ]);

            $memories = $this->parseJsonResponse($response);

            if (! is_array($memories)) {
                Log::warning('Failed to parse memory extraction response', ['response' => $response]);

                return;
            }

            foreach ($memories as $memoryData) {
                // Skip if content is missing
                if (empty($memoryData['content'])) {
                    continue;
                }

                // Simple deduplication: skip if exact same content exists for this user
                $exists = ConversationMemory::where('user_id', $user->id)
                    ->where('content', $memoryData['content'])
                    ->exists();

                if (! $exists) {
                    ConversationMemory::create([
                        'user_id' => $user->id,
                        'memory_type' => $memoryData['type'] ?? 'fact',
                        'content' => $memoryData['content'],
                        'importance' => $memoryData['importance'] ?? 3,
                        'metadata' => $memoryData['metadata'] ?? [],
                        'source_thread_id' => (string) $thread->id,
                        'is_active' => true,
                    ]);
                }
            }

            // Check if compaction is needed after extraction
            $contextLength = $this->settingsService->get('ai_context_length', 32000);
            if ($this->shouldCompact($user, (int) $contextLength)) {
                $this->compact($user);
            }

        } catch (\Exception $e) {
            Log::error('Memory extraction failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Build a formatted string of memories to be injected into the system prompt.
     */
    public function buildMemoryContext(User $user, int $tokenBudget): string
    {
        $memories = ConversationMemory::where('user_id', $user->id)
            ->active()
            ->mostImportant()
            ->get();

        $context = '';
        $usedTokens = 0;

        foreach ($memories as $memory) {
            $text = "- {$memory->content}\n";
            $tokens = $this->estimateTokenCount($text);

            if ($usedTokens + $tokens > $tokenBudget) {
                break;
            }

            $context .= $text;
            $usedTokens += $tokens;

            // Record access for aging/importance tracking
            $memory->recordAccess();
        }

        return trim($context);
    }

    /**
     * Check if the user's memories should be compacted.
     */
    public function shouldCompact(User $user, int $contextLength): bool
    {
        // Default threshold: memory tokens > 15% of context length
        $memoryRatio = 0.15;
        $threshold = (int) ($contextLength * $memoryRatio);

        // Get total tokens of active memories
        $activeMemories = ConversationMemory::where('user_id', $user->id)
            ->active()
            ->get();

        $totalTokens = 0;
        foreach ($activeMemories as $memory) {
            $totalTokens += $this->estimateTokenCount($memory->content);
        }

        // Also trigger if there are too many items
        if ($activeMemories->count() > 50) {
            return true;
        }

        return $totalTokens > $threshold;
    }

    /**
     * Compact user memories by summarizing them via AI.
     */
    public function compact(User $user): void
    {
        $memories = ConversationMemory::where('user_id', $user->id)
            ->active()
            ->where('importance', '<', 4) // Only compact less critical memories
            ->get();

        if ($memories->count() < 10) {
            return;
        }

        $memoriesText = $memories->map(fn ($m) => "- [{$m->memory_type}, importance: {$m->importance}] {$m->content}")->join("\n");
        $maxCount = 10;

        $prompt = <<<PROMPT
Kamu adalah sistem memory. Ringkas kumpulan memories berikut menjadi set yang lebih kompak.
Pertahankan semua informasi penting, hilangkan duplikat, gabungkan yang tumpang tindih.

Memories saat ini:
{$memoriesText}

Kembalikan JSON array dari memories yang sudah dipadatkan, format sama seperti input (type, content, importance, metadata).
Maksimal {$maxCount} memories. Prioritaskan importance tinggi dan informasi terbaru.
PROMPT;

        try {
            $response = $this->provider->chat([
                ['role' => 'system', 'content' => 'You are a memory compaction system. Return ONLY a JSON array.'],
                ['role' => 'user', 'content' => $prompt],
            ], [
                'temperature' => 0.2,
                'max_tokens' => 2048,
            ]);

            $newMemories = $this->parseJsonResponse($response);

            if (is_array($newMemories)) {
                // Deactivate the old memories that were compacted
                ConversationMemory::whereIn('id', $memories->pluck('id'))->update(['is_active' => false]);

                // Save the new summarized memories
                foreach ($newMemories as $mData) {
                    ConversationMemory::create([
                        'user_id' => $user->id,
                        'memory_type' => 'summary',
                        'content' => $mData['content'],
                        'importance' => $mData['importance'] ?? 3,
                        'metadata' => array_merge($mData['metadata'] ?? [], ['compacted_at' => now()->toDateTimeString()]),
                        'is_active' => true,
                    ]);
                }

                Log::info('Memory compaction completed for user '.$user->id);
            }
        } catch (\Exception $e) {
            Log::error('Memory compaction failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Estimate token count using a simple heuristic.
     */
    public function estimateTokenCount(string $text): int
    {
        // Heuristic: ~3 characters per token on average for mix of English/Indonesian
        return (int) ceil(mb_strlen($text) / 3.0);
    }

    /**
     * Parse JSON response from AI, cleaning markdown blocks if necessary.
     */
    protected function parseJsonResponse(string $response): ?array
    {
        $cleaned = trim(preg_replace('/```json\s*|```\s*/', '', $response));

        if (preg_match('/\[[\s\S]*\]/m', $cleaned, $matches)) {
            $cleaned = $matches[0];
        }

        $data = json_decode($cleaned, true);

        return (json_last_error() === JSON_ERROR_NONE) ? $data : null;
    }
}
