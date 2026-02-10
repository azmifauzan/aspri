<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\ChatUsageLog;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_page_requires_authentication(): void
    {
        $response = $this->get('/chat');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_chat_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/chat');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('chat/Index'));
    }

    public function test_user_can_view_their_chat_threads(): void
    {
        $user = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/chat');

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
                ->component('chat/Index')
                ->has('threads', 1)
        );
    }

    public function test_user_can_view_specific_thread(): void
    {
        $user = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);
        $message = ChatMessage::factory()->create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get("/chat/{$thread->id}");

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
                ->component('chat/Index')
                ->has('messages', 1)
                ->has('currentThread')
        );
    }

    public function test_user_cannot_view_other_users_thread(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get("/chat/{$thread->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_send_message_and_create_new_thread(): void
    {
        $user = User::factory()->create();
        $user->profile()->create([
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'asisten yang ramah',
        ]);

        // Mock the ChatOrchestrator response - skip actual API call for testing
        $this->mock(\App\Services\Ai\ChatOrchestrator::class, function ($mock) {
            $mock->shouldReceive('processMessage')
                ->once()
                ->andReturn([
                    'response' => 'Halo! Apa kabar?',
                    'action_executed' => null,
                    'pending_action' => null,
                ]);
        });

        $response = $this->actingAs($user)->postJson('/chat/message', [
            'message' => 'Halo!',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'thread' => ['id', 'title'],
            'userMessage' => ['id', 'role', 'content', 'createdAt'],
            'assistantMessage' => ['id', 'role', 'content', 'createdAt'],
        ]);

        $this->assertDatabaseHas('chat_threads', ['user_id' => $user->id]);
        $this->assertDatabaseHas('chat_messages', ['user_id' => $user->id, 'role' => 'user']);
        $this->assertDatabaseHas('chat_messages', ['user_id' => $user->id, 'role' => 'assistant']);
    }

    public function test_user_can_send_message_to_existing_thread(): void
    {
        $user = User::factory()->create();
        $user->profile()->create([
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'asisten yang ramah',
        ]);
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);

        $this->mock(\App\Services\Ai\ChatOrchestrator::class, function ($mock) {
            $mock->shouldReceive('processMessage')
                ->once()
                ->andReturn([
                    'response' => 'Baik, saya akan membantu.',
                    'action_executed' => null,
                    'pending_action' => null,
                ]);
        });

        $response = $this->actingAs($user)->postJson('/chat/message', [
            'message' => 'Bantu saya!',
            'thread_id' => $thread->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('chat_threads', 1);
    }

    public function test_user_can_delete_their_thread(): void
    {
        $user = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/chat/{$thread->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('chat_threads', ['id' => $thread->id]);
    }

    public function test_user_cannot_delete_other_users_thread(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->deleteJson("/chat/{$thread->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('chat_threads', ['id' => $thread->id]);
    }

    public function test_message_validation_requires_content(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/chat/message', [
            'message' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);
    }

    public function test_user_cannot_send_message_when_daily_limit_reached(): void
    {
        // Set up chat limit setting with type integer
        SystemSetting::setValue('free_trial_daily_chat_limit', 5, ['type' => 'integer']);

        $user = User::factory()->create();
        $user->profile()->create([
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'asisten yang ramah',
        ]);

        // Simulate user has reached the daily limit
        ChatUsageLog::create([
            'user_id' => $user->id,
            'usage_date' => now()->toDateString(),
            'response_count' => 5, // Already at limit
        ]);

        // Verify limit is reached (5 >= 5 = true)
        $this->assertEquals(5, $user->getDailyChatLimit());
        $this->assertEquals(5, ChatUsageLog::getTodayCount($user->id));
        $this->assertTrue($user->hasReachedChatLimit());

        // User should not be able to send more messages
        $response = $this->actingAs($user)->postJson('/chat/message', [
            'message' => 'Halo!',
        ]);

        $response->assertStatus(429);
        $response->assertJson([
            'limit_reached' => true,
            'remaining' => 0,
        ]);
    }

    public function test_user_can_send_message_when_limit_not_reached(): void
    {
        // Set up chat limit setting
        SystemSetting::setValue('free_trial_daily_chat_limit', 50);

        $user = User::factory()->create();
        $user->profile()->create([
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'asisten yang ramah',
        ]);

        // No previous usage - user should be able to send
        $this->assertFalse($user->hasReachedChatLimit());

        $this->mock(\App\Services\Ai\ChatOrchestrator::class, function ($mock) {
            $mock->shouldReceive('processMessage')
                ->once()
                ->andReturn([
                    'response' => 'Halo! Apa kabar?',
                    'action_executed' => null,
                    'pending_action' => null,
                ]);
        });

        $response = $this->actingAs($user)->postJson('/chat/message', [
            'message' => 'Halo!',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'thread' => ['id', 'title'],
            'userMessage' => ['id', 'role', 'content', 'createdAt'],
            'assistantMessage' => ['id', 'role', 'content', 'createdAt'],
        ]);

        // Verify usage was incremented
        $this->assertEquals(1, ChatUsageLog::getTodayCount($user->id));
    }

    public function test_paid_member_has_higher_chat_limit(): void
    {
        // Set up chat limits
        SystemSetting::setValue('free_trial_daily_chat_limit', 50);
        SystemSetting::setValue('full_member_daily_chat_limit', 500);

        $user = User::factory()->create();
        $user->profile()->create([
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'asisten yang ramah',
        ]);

        // Make user a paid member
        $user->subscriptions()->create([
            'plan' => 'monthly',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        // User has used 100 chats (over free trial limit but under paid limit)
        ChatUsageLog::create([
            'user_id' => $user->id,
            'usage_date' => now()->toDateString(),
            'response_count' => 100,
        ]);

        // Paid member should not have reached limit
        $this->assertTrue($user->isPaidMember());
        $this->assertFalse($user->hasReachedChatLimit());

        $this->mock(\App\Services\Ai\ChatOrchestrator::class, function ($mock) {
            $mock->shouldReceive('processMessage')
                ->once()
                ->andReturn([
                    'response' => 'Halo!',
                    'action_executed' => null,
                    'pending_action' => null,
                ]);
        });

        // Paid member should still be able to chat
        $response = $this->actingAs($user)->postJson('/chat/message', [
            'message' => 'Halo!',
        ]);

        $response->assertStatus(200);
    }

    public function test_aspri_responds_when_question_is_out_of_scope(): void
    {
        $user = User::factory()->create();
        $user->profile()->create([
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'asisten yang ramah',
        ]);

        $thread = ChatThread::factory()->create(['user_id' => $user->id]);

        // Mock the ChatOrchestrator to return out_of_scope response with LLM answer
        $this->mock(\App\Services\Ai\ChatOrchestrator::class, function ($mock) {
            $mock->shouldReceive('processMessage')
                ->once()
                ->andReturn([
                    'response' => 'Cuaca hari ini cerah, Kak! Tapi ingat, saya lebih jago bantu kamu catat keuangan atau atur jadwal lho~',
                    'action_taken' => false,
                    'pending_action' => null,
                ]);
        });

        $response = $this->actingAs($user)->postJson('/chat/message', [
            'message' => 'Bagaimana cuaca hari ini?',
            'thread_id' => $thread->id,
        ]);

        $response->assertStatus(200);
        // Verify that ASPRI actually tries to answer instead of refusing
        $content = $response->json('assistantMessage.content');
        $this->assertNotEmpty($content);
        // Response should be personalized (contains the answer or helpful response)
        $this->assertTrue(
            str_contains(strtolower($content), 'cuaca') ||
            str_contains(strtolower($content), 'kak') ||
            str_contains(strtolower($content), 'bantu')
        );
    }

    public function test_aspri_responds_when_message_is_unclear(): void
    {
        $user = User::factory()->create();
        $user->profile()->create([
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'asisten yang ramah',
        ]);

        $thread = ChatThread::factory()->create(['user_id' => $user->id]);

        // Mock the ChatOrchestrator to return unknown response
        $this->mock(\App\Services\Ai\ChatOrchestrator::class, function ($mock) {
            $mock->shouldReceive('processMessage')
                ->once()
                ->andReturn([
                    'response' => 'Maaf, saya tidak memahami maksud pesanmu. Bisa dijelaskan lebih detail?',
                    'action_taken' => false,
                    'pending_action' => null,
                ]);
        });

        $response = $this->actingAs($user)->postJson('/chat/message', [
            'message' => 'wkwkwk asdfgh',
            'thread_id' => $thread->id,
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('tidak memahami', $response->json('assistantMessage.content'));
    }
}
