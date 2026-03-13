<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Thread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    /**
     * GET /api/threads
     * Lists all threads the authenticated user participates in.
     */
    public function indexThreads(Request $request): JsonResponse
    {
        $threads = $request->user()
            ->threads()
            ->with(['participants', 'messages'])
            ->latest()
            ->get();

        return response()->json($threads);
    }

    /**
     * POST /api/threads
     * Creates a new thread between the authenticated user and a recipient.
     * Avoids duplicate threads between the same two users.
     */
    public function storeThread(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recipient_id' => 'required|integer|exists:users,id',
            'context_type' => 'nullable|string',
            'context_id'   => 'nullable|integer',
        ]);

        $senderId    = $request->user()->id;
        $recipientId = $data['recipient_id'];

        // Check for an existing direct thread between these two users
        $existing = Thread::whereHas('participants', function ($q) use ($senderId) {
            $q->where('user_id', $senderId);
        })->whereHas('participants', function ($q) use ($recipientId) {
            $q->where('user_id', $recipientId);
        })->whereNull('context_type')->first();

        if ($existing) {
            return response()->json($existing->load(['participants', 'messages']));
        }

        $thread = Thread::create([
            'context_type' => $data['context_type'] ?? null,
            'context_id'   => $data['context_id'] ?? null,
        ]);

        $thread->participants()->attach([$senderId, $recipientId]);

        return response()->json($thread->load(['participants', 'messages']), 201);
    }

    /**
     * GET /api/threads/{thread}/messages
     * Returns all messages in a thread in chronological order.
     * Only participants may access the thread.
     */
    public function indexMessages(Request $request, Thread $thread): JsonResponse
    {
        if (! $thread->participants->contains($request->user()->id)) {
            return response()->json(['message' => 'Forbidden: you are not a participant of this thread.'], 403);
        }

        $messages = $thread->messages()
            ->with('sender')
            ->oldest()
            ->get();

        return response()->json($messages);
    }

    /**
     * POST /api/threads/{thread}/messages
     * Sends a message in a thread. Sender must be a participant.
     */
    public function storeMessage(Request $request, Thread $thread): JsonResponse
    {
        if (! $thread->participants->contains($request->user()->id)) {
            return response()->json(['message' => 'Forbidden: you are not a participant of this thread.'], 403);
        }

        $data = $request->validate([
            'content' => 'required|string',
        ]);

        $message = Message::create([
            'thread_id' => $thread->id,
            'sender_id' => $request->user()->id,
            'content'   => $data['content'],
        ]);

        MessageSent::dispatch($message);

        return response()->json($message->load('sender'), 201);
    }
}
