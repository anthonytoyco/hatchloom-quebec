<?php

namespace App\Services;

use App\Models\FeedItem;
use App\Models\User;
use InvalidArgumentException;

class AnnouncementFactory extends PostFactory
{
    protected function create(array $data, User $author): FeedItem
    {
        if (empty($data['metadata']['announcementDate'])) {
            throw new InvalidArgumentException('announcementDate is required for announcement posts.');
        }

        return FeedItem::create([
            'user_id'  => $author->id,
            'type'     => 'announcement',
            'title'    => $data['title'] ?? null,
            'content'  => $data['content'],
            'metadata' => ['announcementDate' => $data['metadata']['announcementDate']],
        ]);
    }
}
