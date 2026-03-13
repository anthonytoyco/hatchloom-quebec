<?php

namespace App\Listeners;

use App\Events\FeedPostCreated;
use Illuminate\Support\Facades\Log;

/**
 * Observer pattern implementation (design doc p. 24–25).
 * Maps to UserFeed + NotificationService observers calling update(post).
 *
 * Part 1 (CSSD2203): logs the notification to demonstrate the Observer is wired.
 * Part 2 (CSSD2211): replace the log call with real-time or queued delivery.
 */
class NotifyFeedObservers
{
    public function handle(FeedPostCreated $event): void
    {
        Log::info('FeedPostCreated observer triggered.', [
            'feed_item_id' => $event->feedItem->id,
            'type'         => $event->feedItem->type,
            'author_id'    => $event->feedItem->user_id,
        ]);
    }
}
