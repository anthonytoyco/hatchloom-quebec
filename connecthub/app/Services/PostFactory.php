<?php

namespace App\Services;

use App\Models\FeedItem;
use App\Models\User;
use InvalidArgumentException;

abstract class PostFactory
{
    /**
     * Factory method — delegates to the appropriate concrete factory
     * based on post type. The controller must call this; it must never
     * call FeedItem::create() directly (Factory pattern requirement).
     */
    public static function make(string $type, array $data, User $author): FeedItem
    {
        return match ($type) {
            'share'        => (new ShareFactory())->create($data, $author),
            'announcement' => (new AnnouncementFactory())->create($data, $author),
            'achievement'  => (new AchievementFactory())->create($data, $author),
            default        => throw new InvalidArgumentException("Unknown post type: {$type}"),
        };
    }

    /**
     * Each concrete factory implements this to validate type-specific
     * fields and persist the FeedItem.
     */
    abstract protected function create(array $data, User $author): FeedItem;
}
