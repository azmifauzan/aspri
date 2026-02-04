<?php

namespace Tests\Feature;

use App\Models\Plugin;
use App\Models\PluginSchedule;
use App\Models\User;
use App\Models\UserPlugin;
use App\Services\Plugin\PluginSchedulerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginSchedulerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Plugin $plugin;

    private UserPlugin $userPlugin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->plugin = Plugin::factory()->create([
            'slug' => 'scheduler-test-plugin',
            'name' => 'Scheduler Test Plugin',
        ]);
        $this->userPlugin = UserPlugin::factory()->create([
            'user_id' => $this->user->id,
            'plugin_id' => $this->plugin->id,
            'is_active' => true,
        ]);
    }

    public function test_user_can_create_schedule(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('plugins.schedule.update', $this->plugin), [
                'schedule_type' => 'daily',
                'schedule_value' => '08:00',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('plugin_schedules', [
            'user_plugin_id' => $this->userPlugin->id,
            'schedule_type' => 'daily',
            'schedule_value' => '08:00',
            'is_active' => true,
        ]);
    }

    public function test_user_can_update_schedule(): void
    {
        $schedule = PluginSchedule::factory()->create([
            'user_plugin_id' => $this->userPlugin->id,
            'schedule_type' => 'daily',
            'schedule_value' => '07:00',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('plugins.schedule.update', $this->plugin), [
                'schedule_type' => 'interval',
                'schedule_value' => '60',
            ]);

        $response->assertRedirect();

        // Old schedule should be deactivated
        $this->assertDatabaseHas('plugin_schedules', [
            'id' => $schedule->id,
            'is_active' => false,
        ]);

        // New schedule should exist
        $this->assertDatabaseHas('plugin_schedules', [
            'user_plugin_id' => $this->userPlugin->id,
            'schedule_type' => 'interval',
            'schedule_value' => '60',
            'is_active' => true,
        ]);
    }

    public function test_schedule_calculates_daily_next_run(): void
    {
        $schedule = PluginSchedule::factory()->create([
            'user_plugin_id' => $this->userPlugin->id,
            'schedule_type' => 'daily',
            'schedule_value' => '07:00',
            'next_run_at' => null,
        ]);

        $schedule->calculateNextRun();
        $schedule->refresh();

        $this->assertNotNull($schedule->next_run_at);
        $this->assertEquals(7, $schedule->next_run_at->hour);
        $this->assertEquals(0, $schedule->next_run_at->minute);
    }

    public function test_schedule_calculates_interval_next_run(): void
    {
        $schedule = PluginSchedule::factory()->create([
            'user_plugin_id' => $this->userPlugin->id,
            'schedule_type' => 'interval',
            'schedule_value' => '30', // 30 minutes
            'next_run_at' => null,
        ]);

        $schedule->calculateNextRun();
        $schedule->refresh();

        $this->assertNotNull($schedule->next_run_at);
        // Should be approximately 30 minutes from now (29-31 range to account for test execution time)
        $diff = now()->diffInMinutes($schedule->next_run_at, false);
        $this->assertTrue($diff >= 29 && $diff <= 31, "Expected diff to be between 29-31, got: {$diff}");
    }

    public function test_scheduler_service_finds_due_schedules(): void
    {
        // Schedule that is due
        PluginSchedule::factory()->create([
            'user_plugin_id' => $this->userPlugin->id,
            'schedule_type' => 'daily',
            'schedule_value' => '07:00',
            'next_run_at' => now()->subMinute(),
            'is_active' => true,
        ]);

        // Schedule that is not due
        PluginSchedule::factory()->create([
            'user_plugin_id' => $this->userPlugin->id,
            'schedule_type' => 'daily',
            'schedule_value' => '23:00',
            'next_run_at' => now()->addHours(5),
            'is_active' => true,
        ]);

        $scheduler = app(PluginSchedulerService::class);
        $dueSchedules = $scheduler->getDueSchedules();

        $this->assertCount(1, $dueSchedules);
    }

    public function test_schedule_is_marked_as_run(): void
    {
        $schedule = PluginSchedule::factory()->create([
            'user_plugin_id' => $this->userPlugin->id,
            'schedule_type' => 'daily',
            'schedule_value' => '07:00',
            'last_run_at' => null,
            'next_run_at' => now()->subMinute(),
        ]);

        $schedule->markAsRun();
        $schedule->refresh();

        $this->assertNotNull($schedule->last_run_at);
        $this->assertNotNull($schedule->next_run_at);
        $this->assertTrue($schedule->next_run_at->isFuture());
    }

    public function test_inactive_schedules_are_not_due(): void
    {
        PluginSchedule::factory()->create([
            'user_plugin_id' => $this->userPlugin->id,
            'schedule_type' => 'daily',
            'schedule_value' => '07:00',
            'next_run_at' => now()->subMinute(),
            'is_active' => false,
        ]);

        $scheduler = app(PluginSchedulerService::class);
        $dueSchedules = $scheduler->getDueSchedules();

        $this->assertCount(0, $dueSchedules);
    }
}
