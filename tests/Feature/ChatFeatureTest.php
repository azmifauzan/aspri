<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatThread;
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
            'birth_day' => 1,
            'birth_month' => 1,
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'asisten yang ramah',
        ]);

        // Mock the AI response - skip actual API call for testing
        $this->mock(\App\Services\Ai\ChatService::class, function ($mock) {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->andReturn('Halo! Apa kabar?');
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
            'birth_day' => 1,
            'birth_month' => 1,
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'asisten yang ramah',
        ]);
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);

        $this->mock(\App\Services\Ai\ChatService::class, function ($mock) {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->andReturn('Baik, saya akan membantu.');
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
}
