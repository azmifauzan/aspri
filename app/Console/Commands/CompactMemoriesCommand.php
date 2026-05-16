<?php

namespace App\Console\Commands;

use App\Models\ConversationMemory;
use App\Models\User;
use App\Services\Admin\SettingsService;
use App\Services\Ai\ConversationMemoryService;
use Illuminate\Console\Command;

class CompactMemoriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aspri:compact-memories {--user= : Specific user ID to compact (default: all users over threshold)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compact conversation memories for users whose memory size exceeds the configured threshold';

    /**
     * Execute the console command.
     */
    public function handle(ConversationMemoryService $memoryService, SettingsService $settingsService): int
    {
        $userId = $this->option('user');
        $contextLength = (int) $settingsService->get('ai_context_length', 32000);

        if ($userId !== null) {
            $user = User::find($userId);

            if (! $user) {
                $this->error("User #{$userId} not found.");

                return Command::FAILURE;
            }

            $this->info("Compacting memories for user {$user->id} ({$user->email})...");
            $memoryService->compact($user);
            $this->info('Done.');

            return Command::SUCCESS;
        }

        $userIds = ConversationMemory::query()
            ->where('is_active', true)
            ->distinct()
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            $this->info('No users with active memories found.');

            return Command::SUCCESS;
        }

        $this->info("Scanning {$userIds->count()} user(s) for compaction (context length: {$contextLength})...");

        $compacted = 0;
        $skipped = 0;

        foreach ($userIds as $uid) {
            $user = User::find($uid);

            if (! $user) {
                continue;
            }

            if ($memoryService->shouldCompact($user, $contextLength)) {
                $this->line("  Compacting user {$user->id} ({$user->email})...");
                $memoryService->compact($user);
                $compacted++;
            } else {
                $skipped++;
            }
        }

        $this->info("Done. Compacted: {$compacted}, skipped: {$skipped}.");

        return Command::SUCCESS;
    }
}
