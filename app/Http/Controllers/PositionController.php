<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Models\SideHustle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PositionController extends Controller
{
    public function index($sideHustleId)
    {
        $positions = Position::where('side_hustle_id', $sideHustleId)->get();
        return response()->json($positions);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'side_hustle_id' => 'required|integer|exists:side_hustles,id',
            'title'          => 'required|string',
            'description'    => 'nullable|string',
            'status'         => ['sometimes', Rule::in(['OPEN', 'FILLED', 'CLOSED'])],
        ]);

        $data['status'] = $data['status'] ?? 'OPEN';

        $position = Position::create($data);
        $this->syncOpenPositionsFlag($position->side_hustle_id);

        return response()->json($position, 201);
    }

    public function update(Request $request, $id)
    {
        $position = Position::findOrFail($id);

        $data = $request->validate([
            'title'       => 'sometimes|required|string',
            'description' => 'sometimes|nullable|string',
            'status'      => ['sometimes', 'required', Rule::in(['OPEN', 'FILLED', 'CLOSED'])],
        ]);

        $position->update($data);
        $this->syncOpenPositionsFlag($position->side_hustle_id);

        return response()->json($position);
    }

    public function destroy($id)
    {
        $position = Position::findOrFail($id);
        $sideHustleId = $position->side_hustle_id;
        $position->delete();
        $this->syncOpenPositionsFlag($sideHustleId);

        return response()->json(['message' => 'Position deleted']);
    }

    private function syncOpenPositionsFlag(int $sideHustleId): void
    {
        $hasOpen = Position::where('side_hustle_id', $sideHustleId)
            ->where('status', 'OPEN')
            ->exists();

        SideHustle::where('id', $sideHustleId)
            ->update(['has_open_positions' => $hasOpen]);
    }
}