<?php

namespace Tests\Feature\Integration;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use App\Services\Ai\ChatOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->profile()->create([
            'call_preference' => 'Kak',
            'aspri_name' => 'Jarvis',
            'aspri_persona' => 'profesional dan formal',
        ]);

        // Mock ChatOrchestrator for all tests
        $this->mockChatOrchestrator();
    }

    /**
     * Mock the ChatOrchestrator service to avoid real AI calls.
     */
    protected function mockChatOrchestrator(): void
    {
        $mock = \Mockery::mock(ChatOrchestrator::class);

        $mock->shouldReceive('processMessage')
            ->andReturn([
                'response' => 'Halo, saya Jarvis, asisten pribadi Anda. Ada yang bisa saya bantu?',
                'intent' => 'greeting',
                'confidence' => 0.95,
            ]);

        $this->app->instance(ChatOrchestrator::class, $mock);
    }

    public function test_user_can_view_chat_index(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('chat.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('chat/Index')
        );
    }

    public function test_user_can_send_message_and_create_new_thread(): void
    {
        $this->actingAs($this->user);

        $messageData = [
            'message' => 'Hello, I need help with my schedule today.',
            'thread_id' => null, // New thread
        ];

        $response = $this->postJson(route('chat.send'), $messageData);

        $response->assertOk();
        $response->assertJsonStructure([
            'thread' => ['id', 'title'],
            'userMessage',
            'assistantMessage',
        ]);

        // Thread should be created
        $this->assertDatabaseHas('chat_threads', [
            'user_id' => $this->user->id,
        ]);

        // User message should be saved
        $this->assertDatabaseHas('chat_messages', [
            'role' => 'user',
            'content' => 'Hello, I need help with my schedule today.',
        ]);

        // Assistant response should be generated (mocked)
        $thread = ChatThread::where('user_id', $this->user->id)->first();
        $this->assertNotNull($thread);
        $this->assertGreaterThanOrEqual(2, $thread->messages()->count()); // user + assistant
    }

    public function test_user_can_send_message_to_existing_thread(): void
    {
        $this->actingAs($this->user);

        // Create existing thread with messages
        $thread = ChatThread::create([
            'user_id' => $this->user->id,
            'title' => 'Financial Help',
        ]);

        ChatMessage::create([
            'thread_id' => $thread->id,
            'user_id' => $this->user->id,
            'role' => 'user',
            'content' => 'First message',
        ]);

        ChatMessage::create([
            'thread_id' => $thread->id,
            'user_id' => $this->user->id,
            'role' => 'assistant',
            'content' => 'First response',
        ]);

        // Send new message to same thread
        $messageData = [
            'message' => 'What about my expenses this month?',
            'thread_id' => $thread->id,
        ];

        $response = $this->postJson(route('chat.send'), $messageData);

        $response->assertOk();

        // New message should be added to existing thread
        $this->assertDatabaseHas('chat_messages', [
            'thread_id' => $thread->id,
            'user_id' => $this->user->id,
            'role' => 'user',
            'content' => 'What about my expenses this month?',
        ]);

        // Thread should still be the same
        $this->assertEquals(1, ChatThread::where('user_id', $this->user->id)->count());
    }

    public function test_user_can_view_specific_chat_thread(): void
    {
        $this->actingAs($this->user);

        $thread = ChatThread::create([
            'user_id' => $this->user->id,
            'title' => 'Budget Planning',
        ]);

        ChatMessage::create([
            'thread_id' => $thread->id,
            'user_id' => $this->user->id,
            'role' => 'user',
            'content' => 'Help me plan budget',
        ]);

        ChatMessage::create([
            'thread_id' => $thread->id,
            'user_id' => $this->user->id,
            'role' => 'assistant',
            'content' => 'Sure, I can help you with that.',
        ]);

        $response = $this->get(route('chat.show', $thread));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('chat/Index')
            ->where('currentThread.id', $thread->id)
            ->has('messages', 2)
        );
    }

    public function test_user_can_delete_chat_thread(): void
    {
        $this->actingAs($this->user);

        $thread = ChatThread::create([
            'user_id' => $this->user->id,
            'title' => 'Thread to Delete',
        ]);

        ChatMessage::create([
            'thread_id' => $thread->id,
            'user_id' => $this->user->id,
            'role' => 'user',
            'content' => 'Test message',
        ]);

        $response = $this->deleteJson(route('chat.destroy', $thread));

        $response->assertOk();

        // Thread and messages should be deleted
        $this->assertDatabaseMissing('chat_threads', [
            'id' => $thread->id,
        ]);

        $this->assertDatabaseMissing('chat_messages', [
            'thread_id' => $thread->id,
        ]);
    }

    public function test_user_cannot_access_other_users_chat_thread(): void
    {
        $otherUser = User::factory()->create();
        $otherThread = ChatThread::create([
            'user_id' => $otherUser->id,
            'title' => 'Private Thread',
        ]);

        ChatMessage::create([
            'thread_id' => $otherThread->id,
            'user_id' => $otherUser->id,
            'role' => 'user',
            'content' => 'Private message',
        ]);

        $this->actingAs($this->user);

        // Try to view other user's thread
        $response = $this->get(route('chat.show', $otherThread));
        $response->assertStatus(403);

        // Try to delete other user's thread
        $response = $this->delete(route('chat.destroy', $otherThread));
        $response->assertStatus(403);
    }

    public function test_chat_message_cannot_be_empty(): void
    {
        $this->actingAs($this->user);

        $messageData = [
            'message' => '',
            'thread_id' => null,
        ];

        $response = $this->postJson(route('chat.send'), $messageData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);
    }

    public function test_chat_thread_generates_title_from_first_message(): void
    {
        $this->actingAs($this->user);

        $messageData = [
            'message' => 'What are my expenses for this month?',
            'thread_id' => null,
        ];

        $response = $this->postJson(route('chat.send'), $messageData);

        $response->assertOk();

        $thread = ChatThread::where('user_id', $this->user->id)->first();
        $this->assertNotNull($thread);
        // Title should be generated from first message (truncated)
        $this->assertNotEmpty($thread->title);
    }

    public function test_assistant_uses_user_persona_preferences(): void
    {
        $this->actingAs($this->user);

        // User's profile has 'Jarvis' as aspri_name and specific persona
        $messageData = [
            'message' => 'Hello',
            'thread_id' => null,
        ];

        $response = $this->postJson(route('chat.send'), $messageData);

        $response->assertOk();

        // Check that assistant response considers user's persona
        // (This would need mocking the AI service in actual implementation)
        $thread = ChatThread::where('user_id', $this->user->id)->first();
        $assistantMessage = $thread->messages()->where('role', 'assistant')->first();

        // In real implementation, this should verify the AI was called with correct persona
        $this->assertNotNull($assistantMessage);
    }

    public function test_chat_index_shows_all_user_threads(): void
    {
        $this->actingAs($this->user);

        // Create multiple threads
        $thread1 = ChatThread::create([
            'user_id' => $this->user->id,
            'title' => 'Thread 1',
            'created_at' => now()->subDays(2),
        ]);

        $thread2 = ChatThread::create([
            'user_id' => $this->user->id,
            'title' => 'Thread 2',
            'created_at' => now()->subDay(),
        ]);

        $thread3 = ChatThread::create([
            'user_id' => $this->user->id,
            'title' => 'Thread 3',
            'created_at' => now(),
        ]);

        $response = $this->get(route('chat.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('chat/Index')
            ->has('threads', 3)
        );
    }

    public function test_chat_messages_are_ordered_chronologically(): void
    {
        $this->actingAs($this->user);

        $thread = ChatThread::create([
            'user_id' => $this->user->id,
            'title' => 'Test Thread',
        ]);

        ChatMessage::create([
            'thread_id' => $thread->id,
            'user_id' => $this->user->id,
            'role' => 'user',
            'content' => 'First message',
            'created_at' => now()->subMinutes(3),
        ]);

        ChatMessage::create([
            'thread_id' => $thread->id,
            'user_id' => $this->user->id,
            'role' => 'assistant',
            'content' => 'First response',
            'created_at' => now()->subMinutes(2),
        ]);

        ChatMessage::create([
            'thread_id' => $thread->id,
            'user_id' => $this->user->id,
            'role' => 'user',
            'content' => 'Second message',
            'created_at' => now()->subMinute(),
        ]);

        $response = $this->get(route('chat.show', $thread));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('messages.0.content', 'First message')
            ->where('messages.1.content', 'First response')
            ->where('messages.2.content', 'Second message')
        );
    }
}
