<?php

namespace App\Http\Controllers;

use App\Models\Sandbox;
use Illuminate\Http\Request;

class SandboxController extends Controller
{
    public function index(Request $request)
    {
        $studentId = $request->query('student_id');
        $sandboxes = Sandbox::when($studentId, fn($q) => $q->where('student_id', $studentId))->get();
        return response()->json($sandboxes);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'required|integer|exists:users,id',
            'title' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $sandbox = Sandbox::create($data);
        return response()->json($sandbox, 201);
    }

    public function show($id)
    {
        $sandbox = Sandbox::findOrFail($id);
        return response()->json($sandbox);
    }

    public function update(Request $request, $id)
    {
        $sandbox = Sandbox::findOrFail($id);

        $data = $request->validate([
            'title' => 'sometimes|required|string',
            'description' => 'sometimes|nullable|string',
        ]);

        $sandbox->update($data);
        return response()->json($sandbox);
    }

    public function destroy($id)
    {
        $sandbox = Sandbox::findOrFail($id);
        $sandbox->delete();
        return response()->json(['message' => 'Sandbox deleted']);
    }
}