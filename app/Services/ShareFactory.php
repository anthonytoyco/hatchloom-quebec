<?php

namespace App\Services;

use App\Models\FeedItem;
use App\Models\User;
use InvalidArgumentException;

class ShareFactory extends PostFactory
{
    protected function create(array $data, User $author): FeedItem
    {
        if (empty($data['metadata']['shareLink'])) {
            throw new InvalidArgumentException('shareLink is required for share posts.');
        }

        return FeedItem::create([
            'user_id'  => $author->id,
            'type'     => 'share',
            'title'    => $data['title'] ?? null,
            'content'  => $data['content'],
            'metadata' => ['shareLink' => $data['metadata']['shareLink']],
        ]);
    }
}
