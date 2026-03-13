<?php

namespace App\Events;

use App\Models\FeedItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a FeedItem is persisted.
 * Maps to PostFeed::createPost() triggering notify() in the Observer UML (p. 24).
 */
class FeedPostCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly FeedItem $feedItem)
    {
    }
}
