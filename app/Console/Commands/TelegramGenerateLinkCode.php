<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TelegramGenerateLinkCode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:link
                            {--email= : Email of the user to generate link code for}
                            {--user= : ID of the user to generate link code for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a Telegram link code for a user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = $this->findUser();

        if (! $user) {
            return self::FAILURE;
        }

        if ($user->telegram_chat_id) {
            $this->warn("User {$user->email} already has Telegram linked.");

            if (! $this->confirm('Do you want to unlink and generate a new code?')) {
                return self::SUCCESS;
            }

            $user->update([
                'telegram_chat_id' => null,
                'telegram_username' => null,
            ]);
        }

        $code = strtoupper(Str::random(6));

        $user->update([
            'telegram_link_code' => $code,
            'telegram_link_expires_at' => now()->addHours(24),
        ]);

        $this->newLine();
        $this->info('🔗 Telegram Link Code Generated');
        $this->line('─────────────────────────────────');
        $this->line("User: {$user->name} ({$user->email})");
        $this->newLine();
        $this->line("Code: <fg=green;options=bold>{$code}</>");
        $this->newLine();
        $this->line('Send this to the Telegram bot:');
        $this->line("<fg=cyan>/link {$code}</>");
        $this->newLine();
        $this->line('⏰ Expires in 24 hours');

        return self::SUCCESS;
    }

    protected function findUser(): ?User
    {
        $email = $this->option('email');
        $userId = $this->option('user');

        if ($email) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                $this->error("User with email '{$email}' not found.");

                return null;
            }

            return $user;
        }

        if ($userId) {
            $user = User::find($userId);

            if (! $user) {
                $this->error("User with ID '{$userId}' not found.");

                return null;
            }

            return $user;
        }

        // Interactive mode - list users
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        if ($users->isEmpty()) {
            $this->error('No users found in the database.');

            return null;
        }

        $choices = $users->mapWithKeys(fn ($u) => [$u->id => "{$u->name} ({$u->email})"])->toArray();

        $selected = $this->choice('Select a user', $choices);

        $selectedId = array_search($selected, $choices);

        return User::find($selectedId);
    }
}
