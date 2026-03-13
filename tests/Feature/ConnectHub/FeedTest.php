<?php

namespace Tests\Feature\ConnectHub;

use App\Events\FeedPostCreated;
use App\Models\FeedItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * ConnectHub — Feed Module tests.
 *
 * Required: TC-Q3-001 (HL-Post-Created-Success)
 * Additional: Factory pattern, Observer pattern, auth guard, validation.
 */
class FeedTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // TC-Q3-001  HL-Post-Created-Success
    // POST /api/feed with valid data → 201, FeedItem in DB with correct type/author
    // -------------------------------------------------------------------------

    public function test_authenticated_user_can_create_share_post(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/feed', [
                'type'    => 'share',
                'content' => 'Check out this link!',
                'metadata' => ['shareLink' => 'https://example.com'],
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['type' => 'share'])
            ->assertJsonPath('user_id', $user->id);

        $this->assertDatabaseHas('feed_items', [
            'user_id' => $user->id,
            'type'    => 'share',
        ]);
    }

    public function test_authenticated_user_can_create_announcement_post(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/feed', [
                'type'    => 'announcement',
                'content' => 'Big news coming soon.',
                'metadata' => ['announcementDate' => '2026-04-01'],
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['type' => 'announcement']);

        $this->assertDatabaseHas('feed_items', [
            'user_id' => $user->id,
            'type'    => 'announcement',
        ]);
    }

    public function test_authenticated_user_can_create_achievement_post(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/feed', [
                'type'    => 'achievement',
                'content' => 'Proud to share this milestone.',
                'metadata' => ['achievementName' => 'First Sale'],
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['type' => 'achievement']);

        $this->assertDatabaseHas('feed_items', [
            'user_id' => $user->id,
            'type'    => 'achievement',
        ]);
    }

    // -------------------------------------------------------------------------
    // Observer pattern — FeedPostCreated event must be dispatched on store
    // (design doc p. 37–38, WORKPACK: "Observer pattern for feed updates")
    // -------------------------------------------------------------------------

    public function test_feed_post_created_event_is_dispatched_on_store(): void
    {
        Event::fake([FeedPostCreated::class]);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/feed', [
                'type'    => 'achievement',
                'content' => 'Observer test.',
                'metadata' => ['achievementName' => 'Observer Badge'],
            ]);

        Event::assertDispatched(FeedPostCreated::class, function ($event) use ($user) {
            return $event->feedItem->user_id === $user->id;
        });
    }

    // -------------------------------------------------------------------------
    // Feed index — newest first
    // -------------------------------------------------------------------------

    public function test_feed_index_returns_posts_newest_first(): void
    {
        $user = User::factory()->create();

        $older = FeedItem::create([
            'user_id' => $user->id,
            'type'    => 'share',
            'content' => 'Older post',
            'metadata' => ['shareLink' => 'https://old.com'],
        ]);

        $newer = FeedItem::create([
            'user_id' => $user->id,
            'type'    => 'share',
            'content' => 'Newer post',
            'metadata' => ['shareLink' => 'https://new.com'],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/feed');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->first() === $newer->id);
    }

    // -------------------------------------------------------------------------
    // Like & comment
    // -------------------------------------------------------------------------

    public function test_user_can_like_a_post(): void
    {
        $user = User::factory()->create();
        $post = FeedItem::create([
            'user_id' => $user->id,
            'type'    => 'share',
            'content' => 'Like me.',
            'metadata' => ['shareLink' => 'https://example.com'],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/feed/{$post->id}/like");

        $response->assertStatus(201);
        $this->assertDatabaseHas('feed_actions', [
            'feed_item_id' => $post->id,
            'user_id'      => $user->id,
            'action_type'  => 'like',
        ]);
    }

    public function test_duplicate_like_returns_409(): void
    {
        $user = User::factory()->create();
        $post = FeedItem::create([
            'user_id' => $user->id,
            'type'    => 'share',
            'content' => 'Like me.',
            'metadata' => ['shareLink' => 'https://example.com'],
        ]);

        $this->actingAs($user, 'sanctum')->postJson("/api/feed/{$post->id}/like");

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/feed/{$post->id}/like");

        $response->assertStatus(409);
    }

    public function test_user_can_comment_on_a_post(): void
    {
        $user = User::factory()->create();
        $post = FeedItem::create([
            'user_id' => $user->id,
            'type'    => 'share',
            'content' => 'Comment on me.',
            'metadata' => ['shareLink' => 'https://example.com'],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/feed/{$post->id}/comment", [
                'content' => 'Great post!',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('feed_actions', [
            'feed_item_id' => $post->id,
            'user_id'      => $user->id,
            'action_type'  => 'comment',
            'content'      => 'Great post!',
        ]);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function test_store_returns_422_when_type_is_invalid(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/feed', [
                'type'    => 'invalid_type',
                'content' => 'Some content',
            ]);

        $response->assertStatus(422);
    }

    public function test_share_post_requires_share_link(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/feed', [
                'type'    => 'share',
                'content' => 'Missing the link.',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_to_feed_returns_401(): void
    {
        $response = $this->getJson('/api/feed');
        $response->assertStatus(401);
    }
}
