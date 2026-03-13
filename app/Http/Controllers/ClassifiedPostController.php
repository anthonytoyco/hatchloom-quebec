<?php

namespace App\Http\Controllers;

use App\Events\ClassifiedPostCreated;
use App\Models\ClassifiedPost;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassifiedPostController extends Controller
{
    /**
     * GET /api/classifieds
     * Lists classified posts. Supports optional ?status=OPEN|FILLED|CLOSED filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ClassifiedPost::with(['position', 'sideHustle', 'author']);

        if ($request->has('status')) {
            $request->validate([
                'status' => Rule::in(['OPEN', 'FILLED', 'CLOSED']),
            ]);
            $query->where('status', $request->input('status'));
        }

        return response()->json($query->latest()->get());
    }

    /**
     * POST /api/classifieds
     * Creates a classified post linked to a position.
     * Validates Position Status Interface: position must exist and belong
     * to the authenticated user's SideHustle.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'position_id' => 'required|integer|exists:positions,id',
            'title'       => 'required|string|max:255',
            'content'     => 'required|string',
        ]);

        // Position Status Interface check (design doc p. 19–20):
        // The position must belong to a SideHustle owned by the authenticated user.
        $position = Position::with('sideHustle')->findOrFail($data['position_id']);

        if ((string) $position->sideHustle->student_id !== (string) $request->user()->id) {
            return response()->json(['message' => 'Forbidden: position does not belong to your SideHustle.'], 403);
        }

        $classifiedPost = ClassifiedPost::create([
            'position_id'   => $position->id,
            'side_hustle_id' => $position->sideHustle->id,
            'author_id'     => $request->user()->id,
            'title'         => $data['title'],
            'content'       => $data['content'],
            'status'        => 'OPEN',
        ]);

        ClassifiedPostCreated::dispatch($classifiedPost);

        return response()->json($classifiedPost->load(['position', 'sideHustle', 'author']), 201);
    }

    /**
     * GET /api/classifieds/{classifiedPost}
     * Returns a single classified post by ID.
     */
    public function show(ClassifiedPost $classifiedPost): JsonResponse
    {
        return response()->json($classifiedPost->load(['position', 'sideHustle', 'author']));
    }

    /**
     * PATCH /api/classifieds/{classifiedPost}/status
     * Updates status. Only OPEN → FILLED or OPEN → CLOSED are valid transitions.
     * Only the owner of the classified post may perform this action.
     */
    public function updateStatus(Request $request, ClassifiedPost $classifiedPost): JsonResponse
    {
        // Ownership guard (Test ID 13, design doc p. 49)
        if ($classifiedPost->author_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden: only the owner may change this status.'], 403);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['FILLED', 'CLOSED'])],
        ]);

        // Lifecycle guard: only OPEN → FILLED or OPEN → CLOSED
        if (! $classifiedPost->canTransitionTo($data['status'])) {
            return response()->json([
                'message' => "Invalid transition: {$classifiedPost->status} → {$data['status']}.",
            ], 422);
        }

        $classifiedPost->update(['status' => $data['status']]);

        return response()->json($classifiedPost->load(['position', 'sideHustle', 'author']));
    }
}
