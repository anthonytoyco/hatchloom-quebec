<?php

namespace Tests\Unit;

use App\Models\FeedItem;
use App\Models\User;
use App\Services\PostFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Unit tests for the PostFactory design pattern (design doc p. 21, 37).
 *
 * The Factory pattern is required by the design doc:
 * "The factory method was used for the creation of different post types
 * because it allows the program to decide during runtime which class/post
 * type to create based on the type that will be submitted to the Factory."
 *
 * Rule: PostFactory is the sole creator of FeedItem records —
 * controllers must never call FeedItem::create() directly.
 */
class PostFactoryTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // ShareFactory (design doc p. 22: SharePost.shareLink)
    // -------------------------------------------------------------------------

    public function test_make_share_returns_feed_item_with_type_share(): void
    {
        $user = User::factory()->create();

        $item = PostFactory::make('share', [
            'content'  => 'Check this out.',
            'metadata' => ['shareLink' => 'https://example.com'],
        ], $user);

        $this->assertInstanceOf(FeedItem::class, $item);
        $this->assertSame('share', $item->type);
        $this->assertSame($user->id, $item->user_id);
        $this->assertSame('https://example.com', $item->metadata['shareLink']);
        $this->assertDatabaseHas('feed_items', ['id' => $item->id, 'type' => 'share']);
    }

    public function test_share_factory_throws_when_share_link_is_missing(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        PostFactory::make('share', [
            'content'  => 'Missing link.',
            'metadata' => [],
        ], $user);
    }

    // -------------------------------------------------------------------------
    // AnnouncementFactory (design doc p. 22: AnnouncementPost.announcementDate)
    // -------------------------------------------------------------------------

    public function test_make_announcement_returns_feed_item_with_type_announcement(): void
    {
        $user = User::factory()->create();

        $item = PostFactory::make('announcement', [
            'content'  => 'Big news coming.',
            'metadata' => ['announcementDate' => '2026-04-01'],
        ], $user);

        $this->assertInstanceOf(FeedItem::class, $item);
        $this->assertSame('announcement', $item->type);
        $this->assertSame($user->id, $item->user_id);
        $this->assertSame('2026-04-01', $item->metadata['announcementDate']);
        $this->assertDatabaseHas('feed_items', ['id' => $item->id, 'type' => 'announcement']);
    }

    public function test_announcement_factory_throws_when_announcement_date_is_missing(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        PostFactory::make('announcement', [
            'content'  => 'Missing date.',
            'metadata' => [],
        ], $user);
    }

    // -------------------------------------------------------------------------
    // AchievementFactory (design doc p. 22: AchievementPost.achievementName)
    // -------------------------------------------------------------------------

    public function test_make_achievement_returns_feed_item_with_type_achievement(): void
    {
        $user = User::factory()->create();

        $item = PostFactory::make('achievement', [
            'content'  => 'Proud of this milestone.',
            'metadata' => ['achievementName' => 'First Sale'],
        ], $user);

        $this->assertInstanceOf(FeedItem::class, $item);
        $this->assertSame('achievement', $item->type);
        $this->assertSame($user->id, $item->user_id);
        $this->assertSame('First Sale', $item->metadata['achievementName']);
        $this->assertDatabaseHas('feed_items', ['id' => $item->id, 'type' => 'achievement']);
    }

    public function test_achievement_factory_throws_when_achievement_name_is_missing(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        PostFactory::make('achievement', [
            'content'  => 'Missing achievement name.',
            'metadata' => [],
        ], $user);
    }

    // -------------------------------------------------------------------------
    // Unknown type — must throw (design doc p. 37: only known types are valid)
    // -------------------------------------------------------------------------

    public function test_make_throws_for_unknown_post_type(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown post type/');

        PostFactory::make('video', [
            'content'  => 'Should fail.',
            'metadata' => [],
        ], $user);
    }

    // -------------------------------------------------------------------------
    // Optional title field — present on all post types
    // -------------------------------------------------------------------------

    public function test_optional_title_is_stored_when_provided(): void
    {
        $user = User::factory()->create();

        $item = PostFactory::make('achievement', [
            'title'    => 'My big win',
            'content'  => 'Details here.',
            'metadata' => ['achievementName' => 'Entrepreneur Award'],
        ], $user);

        $this->assertSame('My big win', $item->title);
    }

    public function test_title_is_null_when_not_provided(): void
    {
        $user = User::factory()->create();

        $item = PostFactory::make('share', [
            'content'  => 'No title.',
            'metadata' => ['shareLink' => 'https://example.com'],
        ], $user);

        $this->assertNull($item->title);
    }
}
