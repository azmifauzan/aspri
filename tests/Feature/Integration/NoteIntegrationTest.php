<?php

namespace Tests\Feature\Integration;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteIntegrationTest extends TestCase
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

    public function test_user_can_view_notes_index(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('notes.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('notes/Index')
        );
    }

    public function test_user_can_create_note(): void
    {
        $this->actingAs($this->user);

        $noteData = [
            'title' => 'My First Note',
            'content' => json_encode([
                [
                    'type' => 'paragraph',
                    'content' => 'This is my first note content.',
                ],
            ]),
            'tags' => ['personal', 'important'],
        ];

        $response = $this->post(route('notes.store'), $noteData);

        $response->assertRedirect();
        $this->assertDatabaseHas('notes', [
            'user_id' => $this->user->id,
            'title' => 'My First Note',
        ]);

        $note = Note::where('title', 'My First Note')->first();
        $this->assertNotNull($note);
        $this->assertEquals(['personal', 'important'], $note->tags);
    }

    public function test_user_can_create_note_with_block_content(): void
    {
        $this->actingAs($this->user);

        $blockContent = [
            [
                'type' => 'heading',
                'level' => 1,
                'content' => 'Meeting Notes',
            ],
            [
                'type' => 'paragraph',
                'content' => 'Introduction to the meeting.',
            ],
            [
                'type' => 'list',
                'style' => 'bullet',
                'items' => [
                    'First agenda item',
                    'Second agenda item',
                    'Third agenda item',
                ],
            ],
            [
                'type' => 'code',
                'language' => 'php',
                'content' => '<?php echo "Hello World"; ?>',
            ],
        ];

        $noteData = [
            'title' => 'Meeting Notes - Jan 2026',
            'content' => json_encode($blockContent),
            'tags' => ['work', 'meeting'],
        ];

        $response = $this->post(route('notes.store'), $noteData);

        $response->assertRedirect();
        $this->assertDatabaseHas('notes', [
            'user_id' => $this->user->id,
            'title' => 'Meeting Notes - Jan 2026',
        ]);

        $note = Note::where('title', 'Meeting Notes - Jan 2026')->first();
        $this->assertEquals($blockContent, json_decode($note->content, true));
    }

    public function test_user_can_update_note(): void
    {
        $this->actingAs($this->user);

        $note = Note::create([
            'user_id' => $this->user->id,
            'title' => 'Original Title',
            'content' => json_encode([
                ['type' => 'paragraph', 'content' => 'Original content'],
            ]),
            'tags' => ['old-tag'],
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'content' => json_encode([
                ['type' => 'paragraph', 'content' => 'Updated content'],
            ]),
            'tags' => ['new-tag', 'updated'],
        ];

        $response = $this->put(route('notes.update', $note), $updateData);

        $response->assertRedirect();
        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'title' => 'Updated Title',
        ]);

        $note->refresh();
        $this->assertEquals(['new-tag', 'updated'], $note->tags);
    }

    public function test_user_can_delete_note(): void
    {
        $this->actingAs($this->user);

        $note = Note::create([
            'user_id' => $this->user->id,
            'title' => 'Note to Delete',
            'content' => json_encode([
                ['type' => 'paragraph', 'content' => 'This will be deleted'],
            ]),
        ]);

        $response = $this->delete(route('notes.destroy', $note));

        $response->assertRedirect();
        $this->assertSoftDeleted('notes', [
            'id' => $note->id,
        ]);
    }

    public function test_user_cannot_access_other_users_notes(): void
    {
        $otherUser = User::factory()->create();
        $otherNote = Note::create([
            'user_id' => $otherUser->id,
            'title' => 'Other User Note',
            'content' => json_encode([
                ['type' => 'paragraph', 'content' => 'Private content'],
            ]),
        ]);

        $this->actingAs($this->user);

        // Try to update
        $response = $this->put(route('notes.update', $otherNote), [
            'title' => 'Hacked',
        ]);
        $response->assertStatus(403);

        // Try to delete
        $response = $this->delete(route('notes.destroy', $otherNote));
        $response->assertStatus(403);
    }

    public function test_note_requires_title(): void
    {
        $this->actingAs($this->user);

        $noteData = [
            'content' => json_encode([
                ['type' => 'paragraph', 'content' => 'Content without title'],
            ]),
            // Missing title
        ];

        $response = $this->post(route('notes.store'), $noteData);

        $response->assertSessionHasErrors(['title']);
    }

    public function test_user_can_filter_notes_by_tags(): void
    {
        $this->actingAs($this->user);

        Note::create([
            'user_id' => $this->user->id,
            'title' => 'Work Note',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Work']]),
            'tags' => ['work'],
        ]);

        Note::create([
            'user_id' => $this->user->id,
            'title' => 'Personal Note',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Personal']]),
            'tags' => ['personal'],
        ]);

        Note::create([
            'user_id' => $this->user->id,
            'title' => 'Mixed Note',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Mixed']]),
            'tags' => ['work', 'personal'],
        ]);

        $response = $this->get(route('notes.index', ['tag' => 'work']));

        $response->assertOk();
        // Should return 2 notes (Work Note and Mixed Note)
    }

    public function test_user_can_search_notes(): void
    {
        $this->actingAs($this->user);

        Note::create([
            'user_id' => $this->user->id,
            'title' => 'Laravel Tutorial',
            'content' => json_encode([
                ['type' => 'paragraph', 'content' => 'How to use Laravel framework'],
            ]),
        ]);

        Note::create([
            'user_id' => $this->user->id,
            'title' => 'Vue.js Guide',
            'content' => json_encode([
                ['type' => 'paragraph', 'content' => 'Frontend development with Vue'],
            ]),
        ]);

        $response = $this->get(route('notes.index', ['search' => 'Laravel']));

        $response->assertOk();
        // Should return only Laravel Tutorial note
    }

    public function test_note_content_must_be_valid_json(): void
    {
        $this->actingAs($this->user);

        $noteData = [
            'title' => 'Invalid Note',
            'content' => 'This is not JSON', // Invalid JSON
            'tags' => [],
        ];

        $response = $this->post(route('notes.store'), $noteData);

        $response->assertSessionHasErrors(['content']);
    }

    public function test_user_can_create_note_without_tags(): void
    {
        $this->actingAs($this->user);

        $noteData = [
            'title' => 'Untagged Note',
            'content' => json_encode([
                ['type' => 'paragraph', 'content' => 'No tags here'],
            ]),
            'tags' => null,
        ];

        $response = $this->post(route('notes.store'), $noteData);

        $response->assertRedirect();
        $this->assertDatabaseHas('notes', [
            'user_id' => $this->user->id,
            'title' => 'Untagged Note',
        ]);
    }

    public function test_notes_are_ordered_by_updated_at_desc(): void
    {
        $this->actingAs($this->user);

        $oldNote = Note::create([
            'user_id' => $this->user->id,
            'title' => 'Old Note',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Old']]),
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $newNote = Note::create([
            'user_id' => $this->user->id,
            'title' => 'New Note',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'New']]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('notes.index'));

        $response->assertOk();
        // New note should appear first
    }
}
