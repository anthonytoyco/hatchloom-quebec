<?php

namespace App\Http\Controllers;

use App\Models\SideHustle;
use Illuminate\Http\Request;

class BusinessModelCanvasController extends Controller
{
    public function show($sideHustleId)
    {
        $bmc = SideHustle::findOrFail($sideHustleId)->bmc;
        return response()->json($bmc);
    }

    public function update(Request $request, $sideHustleId)
    {
        $sideHustle = SideHustle::findOrFail($sideHustleId);

        if ($sideHustle->student_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $bmc = $sideHustle->bmc;

        $data = $request->validate([
            'key_partners'           => 'nullable|string',
            'key_activities'         => 'nullable|string',
            'key_resources'          => 'nullable|string',
            'value_propositions'     => 'nullable|string',
            'customer_relationships' => 'nullable|string',
            'channels'               => 'nullable|string',
            'customer_segments'      => 'nullable|string',
            'cost_structure'         => 'nullable|string',
            'revenue_streams'        => 'nullable|string',
        ]);

        $bmc->update($data);
        return response()->json($bmc);
    }
}