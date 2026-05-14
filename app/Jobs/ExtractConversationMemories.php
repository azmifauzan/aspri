<?php

namespace App\Jobs;

use App\Models\ChatThread;
use App\Services\Ai\ConversationMemoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExtractConversationMemories implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected ChatThread $thread,
        protected ?string $dispatchedAt = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ConversationMemoryService $memoryService): void
    {
        // Debounce check: only proceed if this is the latest dispatched job for this thread
        if ($this->dispatchedAt) {
            $latestDispatched = Cache::get("memory_extraction_last_dispatch_{$this->thread->id}");
            if ($latestDispatched && $latestDispatched !== $this->dispatchedAt) {
                Log::debug('Skipping memory extraction job as a newer one exists', ['thread_id' => $this->thread->id]);

                return;
            }
        }

        Log::info('Extracting memories for thread: '.$this->thread->id);

        $user = $this->thread->user;

        if (! $user) {
            Log::warning('User not found for thread memory extraction', ['thread_id' => $this->thread->id]);

            return;
        }

        $memoryService->extractMemoriesFromThread($this->thread, $user);
    }
}
