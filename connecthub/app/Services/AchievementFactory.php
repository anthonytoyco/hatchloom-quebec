<?php

namespace App\Services;

use App\Models\FeedItem;
use App\Models\User;
use InvalidArgumentException;

class AchievementFactory extends PostFactory
{
    protected function create(array $data, User $author): FeedItem
    {
        if (empty($data['metadata']['achievementName'])) {
            throw new InvalidArgumentException('achievementName is required for achievement posts.');
        }

        return FeedItem::create([
            'user_id'  => $author->id,
            'type'     => 'achievement',
            'title'    => $data['title'] ?? null,
            'content'  => $data['content'],
            'metadata' => ['achievementName' => $data['metadata']['achievementName']],
        ]);
    }
}
