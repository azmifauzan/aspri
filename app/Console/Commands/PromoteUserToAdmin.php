<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class PromoteUserToAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:promote {email?} {--role=super_admin : The role to assign (admin or super_admin)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Promote a user to admin or super admin role';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email') ?? text(
            label: 'Enter user email',
            required: true,
            validate: fn ($value) => Validator::make(['email' => $value], ['email' => 'email'])->fails()
                ? 'Please enter a valid email address'
                : null
        );

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email '{$email}' not found.");

            return self::FAILURE;
        }

        $role = $this->option('role');

        if (! in_array($role, ['admin', 'super_admin'])) {
            $role = select(
                label: 'Select role',
                options: [
                    'admin' => 'Admin - Limited admin access',
                    'super_admin' => 'Super Admin - Full admin access',
                ],
                default: 'super_admin'
            );
        }

        $user->update(['role' => $role]);

        $this->info("User '{$user->name}' ({$user->email}) has been promoted to {$role}.");

        return self::SUCCESS;
    }
}
