<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'feed_item_id',
        'user_id',
        'action_type',
        'content',
    ];

    public function feedItem(): BelongsTo
    {
        return $this->belongsTo(FeedItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
