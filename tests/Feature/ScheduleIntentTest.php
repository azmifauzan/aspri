<?php

namespace Tests\Feature;

use App\Models\ChatThread;
use App\Models\PendingAction;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Ai\ActionExecutorService;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\ChatOrchestrator;
use App\Services\Ai\IntentParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleIntentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ChatThread $thread;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->profile()->create([
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'pria',
        ]);

        $this->thread = ChatThread::create([
            'user_id' => $this->user->id,
            'title' => 'Test Thread',
        ]);
    }

    /**
     * Helper to create a confirmed PendingAction for schedule actions.
     */
    private function createConfirmedPendingAction(string $actionType, array $payload): PendingAction
    {
        return PendingAction::create([
            'user_id' => $this->user->id,
            'thread_id' => $this->thread->id,
            'action_type' => $actionType,
            'module' => 'schedule',
            'payload' => $payload,
            'status' => 'confirmed',
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    // ==========================================
    // ActionExecutorService: update_schedule
    // ==========================================

    public function test_update_schedule_changes_title(): void
    {
        $schedule = Schedule::create([
            'user_id' => $this->user->id,
            'title' => 'Morning Meeting',
            'start_time' => now()->addDay()->setTime(9, 0),
            'end_time' => now()->addDay()->setTime(10, 0),
        ]);

        $executor = app(ActionExecutorService::class);

        $pending = $this->createConfirmedPendingAction('update_schedule', [
            'title' => 'Morning Meeting',
            'new_title' => 'Afternoon Meeting',
        ]);

        $result = $executor->execute($pending);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'title' => 'Afternoon Meeting',
        ]);
    }

    public function test_update_schedule_changes_start_time(): void
    {
        $schedule = Schedule::create([
            'user_id' => $this->user->id,
            'title' => 'Team Sync',
            'start_time' => now()->addDay()->setTime(9, 0),
            'end_time' => now()->addDay()->setTime(10, 0),
        ]);

        $executor = app(ActionExecutorService::class);

        $newStartTime = now()->addDay()->setTime(14, 0)->format('Y-m-d H:i');

        $pending = $this->createConfirmedPendingAction('update_schedule', [
            'title' => 'Team Sync',
            'start_time' => $newStartTime,
        ]);

        $result = $executor->execute($pending);

        $this->assertTrue($result['success']);
        $schedule->refresh();
        $this->assertEquals(14, $schedule->start_time->hour);
    }

    public function test_update_schedule_changes_location(): void
    {
        $schedule = Schedule::create([
            'user_id' => $this->user->id,
            'title' => 'Client Meeting',
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(11, 0),
            'location' => 'Room A',
        ]);

        $executor = app(ActionExecutorService::class);

        $pending = $this->createConfirmedPendingAction('update_schedule', [
            'title' => 'Client Meeting',
            'location' => 'Room B',
        ]);

        $result = $executor->execute($pending);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'location' => 'Room B',
        ]);
    }

    public function test_update_schedule_by_id(): void
    {
        $schedule = Schedule::create([
            'user_id' => $this->user->id,
            'title' => 'Dentist Appointment',
            'start_time' => now()->addDay()->setTime(15, 0),
            'end_time' => now()->addDay()->setTime(16, 0),
        ]);

        $executor = app(ActionExecutorService::class);

        $pending = $this->createConfirmedPendingAction('update_schedule', [
            'schedule_id' => $schedule->id,
            'new_title' => 'Doctor Appointment',
            'description' => 'Annual checkup',
        ]);

        $result = $executor->execute($pending);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'title' => 'Doctor Appointment',
            'description' => 'Annual checkup',
        ]);
    }

    public function test_update_schedule_not_found(): void
    {
        $executor = app(ActionExecutorService::class);

        $pending = $this->createConfirmedPendingAction('update_schedule', [
            'title' => 'Nonexistent Meeting',
            'new_title' => 'New Title',
        ]);

        $result = $executor->execute($pending);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('tidak ditemukan', $result['message']);
    }

    public function test_update_schedule_no_changes(): void
    {
        Schedule::create([
            'user_id' => $this->user->id,
            'title' => 'Some Meeting',
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(11, 0),
        ]);

        $executor = app(ActionExecutorService::class);

        $pending = $this->createConfirmedPendingAction('update_schedule', [
            'title' => 'Some Meeting',
            // No update fields
        ]);

        $result = $executor->execute($pending);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Tidak ada data', $result['message']);
    }

    public function test_update_schedule_only_updates_own_schedule(): void
    {
        $otherUser = User::factory()->create();

        Schedule::create([
            'user_id' => $otherUser->id,
            'title' => 'Other User Meeting',
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(11, 0),
        ]);

        $executor = app(ActionExecutorService::class);

        $pending = $this->createConfirmedPendingAction('update_schedule', [
            'title' => 'Other User Meeting',
            'new_title' => 'Hacked',
        ]);

        $result = $executor->execute($pending);

        $this->assertFalse($result['success']);
        $this->assertDatabaseHas('schedules', [
            'user_id' => $otherUser->id,
            'title' => 'Other User Meeting',
        ]);
    }

    // ==========================================
    // ActionExecutorService: delete_schedule
    // ==========================================

    public function test_delete_schedule_by_title(): void
    {
        $schedule = Schedule::create([
            'user_id' => $this->user->id,
            'title' => 'Obsolete Meeting',
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(11, 0),
        ]);

        $executor = app(ActionExecutorService::class);

        $pending = $this->createConfirmedPendingAction('delete_schedule', [
            'title' => 'Obsolete Meeting',
        ]);

        $result = $executor->execute($pending);

        $this->assertTrue($result['success']);
        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }

    public function test_delete_schedule_by_id(): void
    {
        $schedule = Schedule::create([
            'user_id' => $this->user->id,
            'title' => 'Delete Me',
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(11, 0),
        ]);

        $executor = app(ActionExecutorService::class);

        $pending = $this->createConfirmedPendingAction('delete_schedule', [
            'schedule_id' => $schedule->id,
        ]);

        $result = $executor->execute($pending);

        $this->assertTrue($result['success']);
        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }

    public function test_delete_schedule_not_found(): void
    {
        $executor = app(ActionExecutorService::class);

        $pending = $this->createConfirmedPendingAction('delete_schedule', [
            'title' => 'Ghost Meeting',
        ]);

        $result = $executor->execute($pending);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('tidak ditemukan', $result['message']);
    }

    public function test_delete_schedule_only_deletes_own_schedule(): void
    {
        $otherUser = User::factory()->create();

        $otherSchedule = Schedule::create([
            'user_id' => $otherUser->id,
            'title' => 'Secret Meeting',
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(11, 0),
        ]);

        $executor = app(ActionExecutorService::class);

        $pending = $this->createConfirmedPendingAction('delete_schedule', [
            'title' => 'Secret Meeting',
        ]);

        $result = $executor->execute($pending);

        $this->assertFalse($result['success']);
        $this->assertDatabaseHas('schedules', ['id' => $otherSchedule->id]);
    }

    /**
     * Helper to mock AI provider and intent parser for orchestrator tests.
     */
    private function mockAiAndIntent(array $intentReturn): void
    {
        $mockIntentParser = \Mockery::mock(IntentParserService::class);
        $mockIntentParser->shouldReceive('parse')->andReturn($intentReturn);
        $this->app->instance(IntentParserService::class, $mockIntentParser);

        $mockAiProvider = \Mockery::mock(AiProviderInterface::class);
        $mockAiProvider->shouldReceive('chat')->andReturn('Konfirmasi: apakah Anda yakin?');
        $this->app->instance(AiProviderInterface::class, $mockAiProvider);
    }

    // ==========================================
    // ChatOrchestrator: update_schedule intent routing
    // ==========================================

    public function test_orchestrator_routes_update_schedule_to_pending_action(): void
    {
        $this->mockAiAndIntent([
            'action' => 'update_schedule',
            'module' => 'schedule',
            'entities' => [
                'title' => 'Team Sync',
                'new_title' => 'Team Standup',
                'start_time' => '2026-02-20 10:00',
            ],
            'confidence' => 0.9,
            'requires_confirmation' => true,
        ]);

        $orchestrator = app(ChatOrchestrator::class);

        $result = $orchestrator->processMessage($this->user, 'ubah jadwal Team Sync jadi Team Standup besok jam 10', $this->thread, []);

        $this->assertArrayHasKey('pending_action', $result);
        $this->assertNotNull($result['pending_action']);

        $this->assertDatabaseHas('pending_actions', [
            'user_id' => $this->user->id,
            'thread_id' => $this->thread->id,
            'action_type' => 'update_schedule',
            'module' => 'schedule',
        ]);
    }

    public function test_orchestrator_asks_for_schedule_identifier_on_update(): void
    {
        $this->mockAiAndIntent([
            'action' => 'update_schedule',
            'module' => 'schedule',
            'entities' => [
                'new_title' => 'Something New',
                // Missing 'title' and 'schedule_id'
            ],
            'confidence' => 0.9,
            'requires_confirmation' => true,
        ]);

        $orchestrator = app(ChatOrchestrator::class);

        $result = $orchestrator->processMessage($this->user, 'ubah jadwal', $this->thread, []);

        // Should NOT create a pending action (asks for more info instead)
        $this->assertNull($result['pending_action']);
        $this->assertNotEmpty($result['response']);
    }

    // ==========================================
    // ChatOrchestrator: delete_schedule intent routing
    // ==========================================

    public function test_orchestrator_routes_delete_schedule_to_pending_action(): void
    {
        $this->mockAiAndIntent([
            'action' => 'delete_schedule',
            'module' => 'schedule',
            'entities' => [
                'title' => 'Old Meeting',
            ],
            'confidence' => 0.9,
            'requires_confirmation' => true,
        ]);

        $orchestrator = app(ChatOrchestrator::class);

        $result = $orchestrator->processMessage($this->user, 'hapus jadwal Old Meeting', $this->thread, []);

        $this->assertArrayHasKey('pending_action', $result);
        $this->assertNotNull($result['pending_action']);

        $this->assertDatabaseHas('pending_actions', [
            'user_id' => $this->user->id,
            'thread_id' => $this->thread->id,
            'action_type' => 'delete_schedule',
            'module' => 'schedule',
        ]);
    }

    // ==========================================
    // Full confirmation flow
    // ==========================================

    public function test_update_schedule_full_confirmation_flow(): void
    {
        $schedule = Schedule::create([
            'user_id' => $this->user->id,
            'title' => 'Project Review',
            'start_time' => now()->addDay()->setTime(14, 0),
            'end_time' => now()->addDay()->setTime(15, 0),
            'location' => 'Room A',
        ]);

        // Simulate confirmed pending action
        $pending = $this->createConfirmedPendingAction('update_schedule', [
            'title' => 'Project Review',
            'new_title' => 'Sprint Review',
            'location' => 'Room C',
        ]);

        $executor = app(ActionExecutorService::class);
        $result = $executor->execute($pending);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'title' => 'Sprint Review',
            'location' => 'Room C',
        ]);
    }

    public function test_delete_schedule_full_confirmation_flow(): void
    {
        $schedule = Schedule::create([
            'user_id' => $this->user->id,
            'title' => 'Cancelled Event',
            'start_time' => now()->addDay()->setTime(16, 0),
            'end_time' => now()->addDay()->setTime(17, 0),
        ]);

        $pending = $this->createConfirmedPendingAction('delete_schedule', [
            'title' => 'Cancelled Event',
        ]);

        $executor = app(ActionExecutorService::class);
        $result = $executor->execute($pending);

        $this->assertTrue($result['success']);
        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }

    public function test_update_schedule_multiple_fields(): void
    {
        $schedule = Schedule::create([
            'user_id' => $this->user->id,
            'title' => 'Weekly Report',
            'start_time' => now()->addDay()->setTime(9, 0),
            'end_time' => now()->addDay()->setTime(10, 0),
            'location' => 'Office',
            'description' => 'Submit weekly report',
        ]);

        $newStart = now()->addDays(2)->setTime(11, 0)->format('Y-m-d H:i');
        $newEnd = now()->addDays(2)->setTime(12, 0)->format('Y-m-d H:i');

        $pending = $this->createConfirmedPendingAction('update_schedule', [
            'title' => 'Weekly Report',
            'new_title' => 'Monthly Report',
            'start_time' => $newStart,
            'end_time' => $newEnd,
            'location' => 'Conference Room',
            'description' => 'Submit monthly report',
        ]);

        $executor = app(ActionExecutorService::class);
        $result = $executor->execute($pending);

        $this->assertTrue($result['success']);
        $schedule->refresh();
        $this->assertEquals('Monthly Report', $schedule->title);
        $this->assertEquals('Conference Room', $schedule->location);
        $this->assertEquals('Submit monthly report', $schedule->description);
        $this->assertEquals(11, $schedule->start_time->hour);
        $this->assertEquals(12, $schedule->end_time->hour);
    }
}
