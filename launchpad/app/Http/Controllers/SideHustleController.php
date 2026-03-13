<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SideHustle;
use App\Models\BusinessModelCanvas;
use App\Models\Team;
use App\Models\Sandbox;
use Illuminate\Validation\Rule;

class SideHustleController extends Controller
{

    public function index(Request $request)
    {
        $query = SideHustle::query();

        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        return response()->json($query->with(['bmc', 'team', 'positions'])->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $sideHustle = SideHustle::create(array_merge($validated, [
            'status' => 'IN_THE_LAB',
        ]));

        $sideHustle->bmc()->create([]);
        $sideHustle->team()->create([]);

        return response()->json($sideHustle->load(['bmc', 'team', 'positions']), 201);
    }

    public function createFromSandbox($sandboxId)
    {
        $sandbox = Sandbox::findOrFail($sandboxId);

        $sideHustle = SideHustle::create([
            'sandbox_id' => $sandbox->id,
            'student_id' => $sandbox->student_id,
            'title' => $sandbox->title,
            'description' => $sandbox->description,
            'status' => 'IN_THE_LAB',
        ]);

        $sideHustle->bmc()->create([]);
        $sideHustle->team()->create([]);

        return response()->json($sideHustle->load(['bmc', 'team', 'positions']), 201);
    }

    public function show($id)
    {
        $sideHustle = SideHustle::with(['bmc', 'team', 'team.members', 'positions'])->findOrFail($id);
        return response()->json($sideHustle);
    }

    public function update(Request $request, $id)
    {
        $sideHustle = SideHustle::findOrFail($id);

        if ($sideHustle->student_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => ['sometimes', Rule::in(['IN_THE_LAB', 'LIVE_VENTURE'])],
        ]);

        $sideHustle->update($validated);

        return response()->json($sideHustle->load(['bmc', 'team', 'positions']));
    }

    public function destroy(Request $request, $id)
    {
        $sideHustle = SideHustle::findOrFail($id);

        if ($sideHustle->student_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $sideHustle->delete();

        return response()->json(['message' => 'SideHustle deleted successfully']);
    }

    public function launchpadSummary(Request $request)
    {
        $studentId = $request->user()->id;

        $sandboxCount     = Sandbox::where('student_id', $studentId)->count();
        $inTheLabCount    = SideHustle::where('student_id', $studentId)->where('status', 'IN_THE_LAB')->count();
        $liveVentureCount = SideHustle::where('student_id', $studentId)->where('status', 'LIVE_VENTURE')->count();
        $sideHustles      = SideHustle::where('student_id', $studentId)
            ->with(['positions' => fn($q) => $q->where('status', 'OPEN')])
            ->get();

        return response()->json([
            'sandbox_count'       => $sandboxCount,
            'in_the_lab_count'    => $inTheLabCount,
            'live_venture_count'  => $liveVentureCount,
            'side_hustles'        => $sideHustles,
        ]);
    }
}