<?php

namespace Tests\Feature;

use App\Models\ConversationMemory;
use App\Models\User;
use App\Services\Ai\ConversationMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class CompactMemoriesCommandTest extends TestCase
{
    use MockeryPHPUnitIntegration, RefreshDatabase;

    public function test_command_fails_for_unknown_user(): void
    {
        $this->artisan('aspri:compact-memories', ['--user' => 999999])
            ->expectsOutputToContain('not found')
            ->assertExitCode(1);
    }

    public function test_command_compacts_single_user_when_user_option_given(): void
    {
        $user = User::factory()->create();

        $service = Mockery::mock(ConversationMemoryService::class);
        $service->shouldReceive('compact')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $user->id));
        $this->app->instance(ConversationMemoryService::class, $service);

        $this->artisan('aspri:compact-memories', ['--user' => $user->id])
            ->assertExitCode(0);
    }

    public function test_command_only_compacts_users_over_threshold(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        ConversationMemory::factory()->create(['user_id' => $userA->id, 'is_active' => true]);
        ConversationMemory::factory()->create(['user_id' => $userB->id, 'is_active' => true]);

        $service = Mockery::mock(ConversationMemoryService::class);
        $service->shouldReceive('shouldCompact')
            ->with(Mockery::on(fn ($u) => $u->id === $userA->id), Mockery::any())
            ->andReturn(true);
        $service->shouldReceive('shouldCompact')
            ->with(Mockery::on(fn ($u) => $u->id === $userB->id), Mockery::any())
            ->andReturn(false);
        $service->shouldReceive('compact')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $userA->id));
        $this->app->instance(ConversationMemoryService::class, $service);

        $this->artisan('aspri:compact-memories')
            ->expectsOutputToContain('Compacted: 1, skipped: 1')
            ->assertExitCode(0);
    }

    public function test_command_reports_no_users_when_no_active_memories(): void
    {
        $service = Mockery::mock(ConversationMemoryService::class);
        $service->shouldNotReceive('compact');
        $this->app->instance(ConversationMemoryService::class, $service);

        $this->artisan('aspri:compact-memories')
            ->expectsOutputToContain('No users with active memories')
            ->assertExitCode(0);
    }
}
