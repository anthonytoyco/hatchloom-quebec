<?php

namespace Tests\Feature\ConnectHub;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * ConnectHub — Messaging Module tests.
 *
 * Covers: thread creation, deduplication, message send, chronological ordering,
 * participant guard, and auth guard.
 */
class MessageTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Thread creation
    // -------------------------------------------------------------------------

    public function test_user_can_create_a_thread_with_a_recipient(): void
    {
        $sender    = User::factory()->create();
        $recipient = User::factory()->create();

        $response = $this->actingAs($sender, 'sanctum')
            ->postJson('/api/threads', [
                'recipient_id' => $recipient->id,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('thread_participants', ['user_id' => $sender->id]);
        $this->assertDatabaseHas('thread_participants', ['user_id' => $recipient->id]);
    }

    public function test_creating_duplicate_thread_returns_existing_thread(): void
    {
        $sender    = User::factory()->create();
        $recipient = User::factory()->create();

        $first = $this->actingAs($sender, 'sanctum')
            ->postJson('/api/threads', ['recipient_id' => $recipient->id]);

        $second = $this->actingAs($sender, 'sanctum')
            ->postJson('/api/threads', ['recipient_id' => $recipient->id]);

        // Both return the same thread (second returns 200, not 201)
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertCount(1, Thread::all());
    }

    public function test_thread_can_have_optional_context(): void
    {
        $sender    = User::factory()->create();
        $recipient = User::factory()->create();

        $response = $this->actingAs($sender, 'sanctum')
            ->postJson('/api/threads', [
                'recipient_id' => $recipient->id,
                'context_type' => 'side_hustle',
                'context_id'   => 42,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('threads', [
            'context_type' => 'side_hustle',
            'context_id'   => 42,
        ]);
    }

    // -------------------------------------------------------------------------
    // Thread index
    // -------------------------------------------------------------------------

    public function test_user_only_sees_their_own_threads(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        // Thread between A and B
        $this->actingAs($userA, 'sanctum')
            ->postJson('/api/threads', ['recipient_id' => $userB->id]);

        // Thread between B and C (userA is not involved)
        $this->actingAs($userB, 'sanctum')
            ->postJson('/api/threads', ['recipient_id' => $userC->id]);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/threads');

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    // -------------------------------------------------------------------------
    // Message send
    // -------------------------------------------------------------------------

    public function test_participant_can_send_a_message_in_a_thread(): void
    {
        $sender    = User::factory()->create();
        $recipient = User::factory()->create();

        $thread = Thread::create([]);
        $thread->participants()->attach([$sender->id, $recipient->id]);

        $response = $this->actingAs($sender, 'sanctum')
            ->postJson("/api/threads/{$thread->id}/messages", [
                'content' => 'Hello!',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('content', 'Hello!')
            ->assertJsonPath('sender_id', $sender->id);

        $this->assertDatabaseHas('messages', [
            'thread_id' => $thread->id,
            'sender_id' => $sender->id,
            'content'   => 'Hello!',
        ]);
    }

    // -------------------------------------------------------------------------
    // Chronological ordering (design doc p. 19: getMessages returns ordered list)
    // -------------------------------------------------------------------------

    public function test_messages_are_returned_in_chronological_order(): void
    {
        $sender    = User::factory()->create();
        $recipient = User::factory()->create();

        $thread = Thread::create([]);
        $thread->participants()->attach([$sender->id, $recipient->id]);

        $first  = Message::create(['thread_id' => $thread->id, 'sender_id' => $sender->id, 'content' => 'First']);
        $second = Message::create(['thread_id' => $thread->id, 'sender_id' => $sender->id, 'content' => 'Second']);
        $third  = Message::create(['thread_id' => $thread->id, 'sender_id' => $sender->id, 'content' => 'Third']);

        $response = $this->actingAs($sender, 'sanctum')
            ->getJson("/api/threads/{$thread->id}/messages");

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->values();

        $this->assertEquals([$first->id, $second->id, $third->id], $ids->all());
    }

    // -------------------------------------------------------------------------
    // Participant guard — non-participants are blocked
    // -------------------------------------------------------------------------

    public function test_non_participant_cannot_read_thread_messages(): void
    {
        $userA    = User::factory()->create();
        $userB    = User::factory()->create();
        $intruder = User::factory()->create();

        $thread = Thread::create([]);
        $thread->participants()->attach([$userA->id, $userB->id]);

        $response = $this->actingAs($intruder, 'sanctum')
            ->getJson("/api/threads/{$thread->id}/messages");

        $response->assertStatus(403);
    }

    public function test_non_participant_cannot_send_message_to_thread(): void
    {
        $userA    = User::factory()->create();
        $userB    = User::factory()->create();
        $intruder = User::factory()->create();

        $thread = Thread::create([]);
        $thread->participants()->attach([$userA->id, $userB->id]);

        $response = $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/threads/{$thread->id}/messages", [
                'content' => 'I should not be here.',
            ]);

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Observer pattern — MessageSent event must be dispatched on storeMessage
    // (design doc p. 15–16: Event Notifier dispatches on message send)
    // -------------------------------------------------------------------------

    public function test_message_sent_event_is_dispatched_on_store(): void
    {
        Event::fake([MessageSent::class]);

        $sender    = User::factory()->create();
        $recipient = User::factory()->create();

        $thread = Thread::create([]);
        $thread->participants()->attach([$sender->id, $recipient->id]);

        $this->actingAs($sender, 'sanctum')
            ->postJson("/api/threads/{$thread->id}/messages", [
                'content' => 'Event dispatch test.',
            ]);

        Event::assertDispatched(MessageSent::class, function ($event) use ($sender, $thread) {
            return $event->message->sender_id === $sender->id
                && $event->message->thread_id === $thread->id;
        });
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function test_store_thread_returns_422_when_recipient_id_missing(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/threads', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_id']);
    }

    public function test_store_message_returns_422_when_content_missing(): void
    {
        $sender    = User::factory()->create();
        $recipient = User::factory()->create();

        $thread = Thread::create([]);
        $thread->participants()->attach([$sender->id, $recipient->id]);

        $response = $this->actingAs($sender, 'sanctum')
            ->postJson("/api/threads/{$thread->id}/messages", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_to_threads_returns_401(): void
    {
        $response = $this->getJson('/api/threads');
        $response->assertStatus(401);
    }
}
