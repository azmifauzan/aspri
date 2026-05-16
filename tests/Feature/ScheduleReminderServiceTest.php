<?php

namespace Tests\Feature;

use App\Models\EventReminder;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Schedule\ScheduleReminderService;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class ScheduleReminderServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration, RefreshDatabase;

    protected function makeService(?TelegramBotService $bot = null): ScheduleReminderService
    {
        return new ScheduleReminderService($bot ?? Mockery::mock(TelegramBotService::class));
    }

    public function test_create_for_schedule_inserts_reminders_with_correct_scheduled_for(): void
    {
        $user = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'user_id' => $user->id,
            'start_time' => now()->addHours(2),
        ]);

        $created = $this->makeService()->createForSchedule($schedule, [60, 15]);

        $this->assertCount(2, $created);
        $this->assertDatabaseCount('event_reminders', 2);

        $sixty = EventReminder::where('schedule_id', $schedule->id)->where('minutes_before', 60)->first();
        $this->assertNotNull($sixty);
        $this->assertEquals(
            $schedule->start_time->subMinutes(60)->timestamp,
            $sixty->scheduled_for->timestamp
        );
    }

    public function test_create_skips_past_reminders(): void
    {
        $user = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'user_id' => $user->id,
            'start_time' => now()->addMinutes(10),
        ]);

        // 60 min before = 50 min in the past, should be skipped. 5 min before = future.
        $created = $this->makeService()->createForSchedule($schedule, [60, 5]);

        $this->assertCount(1, $created);
        $this->assertDatabaseHas('event_reminders', ['minutes_before' => 5]);
        $this->assertDatabaseMissing('event_reminders', ['minutes_before' => 60]);
    }

    public function test_create_skips_negative_minutes(): void
    {
        $user = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'user_id' => $user->id,
            'start_time' => now()->addHour(),
        ]);

        $this->makeService()->createForSchedule($schedule, [-10, 15]);

        $this->assertDatabaseCount('event_reminders', 1);
    }

    public function test_replace_deletes_pending_and_creates_new(): void
    {
        $user = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'user_id' => $user->id,
            'start_time' => now()->addHours(3),
        ]);

        $this->makeService()->createForSchedule($schedule, [60, 30]);
        $this->assertDatabaseCount('event_reminders', 2);

        $this->makeService()->replaceForSchedule($schedule, [15]);

        $this->assertDatabaseCount('event_reminders', 1);
        $this->assertDatabaseHas('event_reminders', ['minutes_before' => 15]);
    }

    public function test_replace_does_not_touch_already_sent_reminders(): void
    {
        $user = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'user_id' => $user->id,
            'start_time' => now()->addHour(),
        ]);

        $sent = EventReminder::factory()->sent()->create([
            'schedule_id' => $schedule->id,
            'user_id' => $user->id,
            'minutes_before' => 60,
        ]);

        $this->makeService()->replaceForSchedule($schedule, [10]);

        $this->assertDatabaseHas('event_reminders', ['id' => $sent->id, 'is_sent' => true]);
    }

    public function test_send_due_only_processes_due_pending_reminders(): void
    {
        $user = User::factory()->create();
        $schedule = Schedule::factory()->create(['user_id' => $user->id]);

        $due = EventReminder::factory()->due()->create([
            'schedule_id' => $schedule->id,
            'user_id' => $user->id,
            'channel' => EventReminder::CHANNEL_APP,
        ]);
        EventReminder::factory()->create([
            'schedule_id' => $schedule->id,
            'user_id' => $user->id,
            'scheduled_for' => now()->addHour(),
        ]);
        EventReminder::factory()->due()->sent()->create([
            'schedule_id' => $schedule->id,
            'user_id' => $user->id,
        ]);

        $result = $this->makeService()->sendDue();

        $this->assertSame(['sent' => 1, 'failed' => 0], $result);
        $this->assertTrue($due->fresh()->is_sent);
    }

    public function test_send_due_via_telegram_calls_bot(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => 12345]);
        $schedule = Schedule::factory()->create([
            'user_id' => $user->id,
            'title' => 'Standup',
            'start_time' => now()->addMinutes(30),
        ]);

        $reminder = EventReminder::factory()->due()->telegram()->create([
            'schedule_id' => $schedule->id,
            'user_id' => $user->id,
        ]);

        $bot = Mockery::mock(TelegramBotService::class);
        $bot->shouldReceive('sendMessage')
            ->once()
            ->with(12345, Mockery::on(fn ($text) => str_contains($text, 'Standup')), Mockery::any());

        $result = $this->makeService($bot)->sendDue();

        $this->assertSame(1, $result['sent']);
        $this->assertTrue($reminder->fresh()->is_sent);
    }

    public function test_send_due_marks_failed_when_telegram_user_has_no_chat_id(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => null]);
        $schedule = Schedule::factory()->create(['user_id' => $user->id]);

        $reminder = EventReminder::factory()->due()->telegram()->create([
            'schedule_id' => $schedule->id,
            'user_id' => $user->id,
        ]);

        $bot = Mockery::mock(TelegramBotService::class);
        $bot->shouldNotReceive('sendMessage');

        $result = $this->makeService($bot)->sendDue();

        $this->assertSame(['sent' => 0, 'failed' => 1], $result);
        $this->assertFalse($reminder->fresh()->is_sent);
        $this->assertStringContainsString('Telegram', $reminder->fresh()->error);
    }

    public function test_send_due_marks_failed_when_bot_throws(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => 12345]);
        $schedule = Schedule::factory()->create(['user_id' => $user->id]);

        $reminder = EventReminder::factory()->due()->telegram()->create([
            'schedule_id' => $schedule->id,
            'user_id' => $user->id,
        ]);

        $bot = Mockery::mock(TelegramBotService::class);
        $bot->shouldReceive('sendMessage')->once()->andThrow(new \RuntimeException('API down'));

        $result = $this->makeService($bot)->sendDue();

        $this->assertSame(['sent' => 0, 'failed' => 1], $result);
        $this->assertEquals('API down', $reminder->fresh()->error);
    }

    public function test_command_invokes_service(): void
    {
        $service = Mockery::mock(ScheduleReminderService::class);
        $service->shouldReceive('sendDue')->once()->andReturn(['sent' => 3, 'failed' => 1]);
        $this->app->instance(ScheduleReminderService::class, $service);

        $this->artisan('aspri:send-reminders')
            ->expectsOutputToContain('Sent: 3, failed: 1')
            ->assertExitCode(0);
    }
}
