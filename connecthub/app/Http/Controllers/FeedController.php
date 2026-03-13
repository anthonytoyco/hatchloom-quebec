<?php

namespace App\Http\Controllers;

use App\Events\FeedPostCreated;
use App\Models\FeedAction;
use App\Models\FeedItem;
use App\Services\PostFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeedController extends Controller
{
    /**
     * GET /api/feed
     * Returns the feed for the authenticated user, ordered newest first.
     */
    public function index(): JsonResponse
    {
        $feed = FeedItem::with(['user', 'actions'])
            ->latest()
            ->get();

        return response()->json($feed);
    }

    /**
     * POST /api/feed
     * Creates a new feed post via PostFactory (Factory pattern).
     * Fires FeedPostCreated event (Observer pattern).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type'    => ['required', Rule::in(['share', 'announcement', 'achievement'])],
            'content' => 'required|string',
            'title'   => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
            'metadata.shareLink'       => 'required_if:type,share|string',
            'metadata.announcementDate' => 'required_if:type,announcement|string',
            'metadata.achievementName' => 'required_if:type,achievement|string',
        ]);

        $feedItem = PostFactory::make(
            $request->input('type'),
            $request->only(['title', 'content', 'metadata']),
            $request->user()
        );

        FeedPostCreated::dispatch($feedItem);

        return response()->json($feedItem->load('user'), 201);
    }

    /**
     * POST /api/feed/{feedItem}/like
     * Records a like action. Returns 409 if the user already liked.
     */
    public function like(Request $request, FeedItem $feedItem): JsonResponse
    {
        $exists = FeedAction::where([
            'feed_item_id' => $feedItem->id,
            'user_id'      => $request->user()->id,
            'action_type'  => 'like',
        ])->exists();

        if ($exists) {
            return response()->json(['message' => 'Already liked.'], 409);
        }

        $action = FeedAction::create([
            'feed_item_id' => $feedItem->id,
            'user_id'      => $request->user()->id,
            'action_type'  => 'like',
        ]);

        return response()->json($action, 201);
    }

    /**
     * POST /api/feed/{feedItem}/comment
     * Appends a comment to a feed post.
     */
    public function comment(Request $request, FeedItem $feedItem): JsonResponse
    {
        $data = $request->validate([
            'content' => 'required|string',
        ]);

        $action = FeedAction::create([
            'feed_item_id' => $feedItem->id,
            'user_id'      => $request->user()->id,
            'action_type'  => 'comment',
            'content'      => $data['content'],
        ]);

        return response()->json($action->load('user'), 201);
    }
}
