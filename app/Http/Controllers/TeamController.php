<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function show($sideHustleId)
    {
        $team = Team::where('side_hustle_id', $sideHustleId)->with('members')->firstOrFail();
        return response()->json($team);
    }

    public function addMember(Request $request, $teamId)
    {
        $team = Team::findOrFail($teamId);

        $data = $request->validate([
            'student_id' => 'required|integer|exists:users,id',
            'role' => 'required|string',
        ]);

        $member = $team->members()->create($data);
        return response()->json($member, 201);
    }

    public function removeMember($teamId, $memberId)
    {
        $member = TeamMember::where('team_id', $teamId)->findOrFail($memberId);
        $member->delete();
        return response()->json(['message' => 'Team member removed']);
    }
}