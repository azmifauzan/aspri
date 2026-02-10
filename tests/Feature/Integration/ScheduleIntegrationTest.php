<?php

namespace Tests\Feature\Integration;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->profile()->create([
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'pria',
        ]);
    }

    public function test_user_can_view_schedule_index(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('schedules.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Schedules/Index')
        );
    }

    public function test_user_can_create_schedule(): void
    {
        $this->actingAs($this->user);

        $scheduleData = [
            'title' => 'Team Meeting',
            'description' => 'Weekly sync with team',
            'start_time' => now()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'location' => 'Meeting Room A',
            'is_completed' => false,
        ];

        $response = $this->post(route('schedules.store'), $scheduleData);

        $response->assertRedirect();
        $this->assertDatabaseHas('schedules', [
            'user_id' => $this->user->id,
            'title' => 'Team Meeting',
            'description' => 'Weekly sync with team',
            'location' => 'Meeting Room A',
        ]);
    }

    public function test_user_can_create_all_day_event(): void
    {
        $this->actingAs($this->user);

        $scheduleData = [
            'title' => 'National Holiday',
            'description' => 'Independence Day',
            'start_time' => now()->addWeek()->startOfDay()->format('Y-m-d H:i:s'),
            'end_time' => now()->addWeek()->endOfDay()->format('Y-m-d H:i:s'),
            'is_all_day' => true,
            'is_completed' => false,
        ];

        $response = $this->post(route('schedules.store'), $scheduleData);

        $response->assertRedirect();
        $this->assertDatabaseHas('schedules', [
            'user_id' => $this->user->id,
            'title' => 'National Holiday',
        ]);
    }

    public function test_user_can_update_schedule(): void
    {
        $this->actingAs($this->user);

        $schedule = Schedule::create([
            'user_id' => $this->user->id,
            'title' => 'Original Title',
            'description' => 'Original Description',
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(11, 0),
            'is_completed' => false,
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'description' => 'Updated Description',
            'start_time' => now()->addDay()->setTime(14, 0)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->setTime(15, 0)->format('Y-m-d H:i:s'),
            'is_completed' => false,
        ];

        $response = $this->put(route('schedules.update', $schedule), $updateData);

        $response->assertRedirect();
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'title' => 'Updated Title',
            'description' => 'Updated Description',
        ]);
    }

    public function test_user_can_mark_schedule_as_completed(): void
    {
        $this->actingAs($this->user);

        $schedule = Schedule::create([
            'user_id' => $this->user->id,
            'title' => 'Task to Complete',
            'start_time' => now()->setTime(10, 0),
            'end_time' => now()->setTime(11, 0),
            'is_completed' => false,
        ]);

        $updateData = [
            'title' => 'Task to Complete',
            'start_time' => now()->setTime(10, 0)->format('Y-m-d H:i:s'),
            'end_time' => now()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'is_completed' => true,
        ];

        $response = $this->put(route('schedules.update', $schedule), $updateData);

        $response->assertRedirect();
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'is_completed' => 1, // Database stores boolean as 1/0
        ]);
    }

    public function test_user_can_delete_schedule(): void
    {
        $this->actingAs($this->user);

        $schedule = Schedule::create([
            'user_id' => $this->user->id,
            'title' => 'To Be Deleted',
            'start_time' => now()->setTime(10, 0),
            'end_time' => now()->setTime(11, 0),
            'is_completed' => false,
        ]);

        $response = $this->delete(route('schedules.destroy', $schedule));

        $response->assertRedirect();
        $this->assertDatabaseMissing('schedules', [
            'id' => $schedule->id,
        ]);
    }

    public function test_user_cannot_access_other_users_schedule(): void
    {
        $otherUser = User::factory()->create();
        $otherSchedule = Schedule::create([
            'user_id' => $otherUser->id,
            'title' => 'Other User Schedule',
            'start_time' => now()->setTime(10, 0),
            'end_time' => now()->setTime(11, 0),
            'is_completed' => false,
        ]);

        $this->actingAs($this->user);

        // Try to update other user's schedule
        $response = $this->put(route('schedules.update', $otherSchedule), [
            'title' => 'Hacked',
        ]);

        $response->assertStatus(403);

        // Try to delete other user's schedule
        $response = $this->delete(route('schedules.destroy', $otherSchedule));

        $response->assertStatus(403);
    }

    public function test_schedule_validates_start_time_before_end_time(): void
    {
        $this->actingAs($this->user);

        $scheduleData = [
            'title' => 'Invalid Schedule',
            'start_time' => now()->addDay()->setTime(15, 0)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s'), // Before start
            'is_completed' => false,
        ];

        $response = $this->post(route('schedules.store'), $scheduleData);

        $response->assertSessionHasErrors(['end_time']);
    }

    public function test_schedule_requires_title(): void
    {
        $this->actingAs($this->user);

        $scheduleData = [
            'start_time' => now()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            // Missing title
        ];

        $response = $this->post(route('schedules.store'), $scheduleData);

        $response->assertSessionHasErrors(['title']);
    }

    public function test_user_can_create_recurring_schedule(): void
    {
        $this->actingAs($this->user);

        $scheduleData = [
            'title' => 'Daily Standup',
            'description' => 'Team daily meeting',
            'start_time' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->setTime(9, 15)->format('Y-m-d H:i:s'),
            'is_recurring' => true,
            'recurrence_rule' => 'FREQ=DAILY;COUNT=30', // Daily for 30 days
            'is_completed' => false,
        ];

        $response = $this->post(route('schedules.store'), $scheduleData);

        $response->assertRedirect();
        $this->assertDatabaseHas('schedules', [
            'user_id' => $this->user->id,
            'title' => 'Daily Standup',
            'is_recurring' => 1, // Database stores boolean as 1/0
        ]);
    }

    public function test_user_can_view_monthly_schedule_events(): void
    {
        $this->actingAs($this->user);

        // Create events for this month
        Schedule::create([
            'user_id' => $this->user->id,
            'title' => 'Event 1',
            'start_time' => now()->startOfMonth()->setTime(10, 0),
            'end_time' => now()->startOfMonth()->setTime(11, 0),
            'is_completed' => false,
        ]);

        Schedule::create([
            'user_id' => $this->user->id,
            'title' => 'Event 2',
            'start_time' => now()->setTime(14, 0),
            'end_time' => now()->setTime(15, 0),
            'is_completed' => false,
        ]);

        // Create event for next month (should not appear)
        Schedule::create([
            'user_id' => $this->user->id,
            'title' => 'Next Month Event',
            'start_time' => now()->addMonth()->setTime(10, 0),
            'end_time' => now()->addMonth()->setTime(11, 0),
            'is_completed' => false,
        ]);

        $response = $this->get(route('schedules.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Schedules/Index')
            ->has('schedules')
        );
    }
}
