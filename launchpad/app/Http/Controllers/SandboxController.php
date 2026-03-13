<?php

namespace App\Http\Controllers;

use App\Models\Sandbox;
use Illuminate\Http\Request;

class SandboxController extends Controller
{
    public function index(Request $request)
    {
        $studentId = (int) $request->query('student_id');
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

        if ($sandbox->student_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'title' => 'sometimes|required|string',
            'description' => 'sometimes|nullable|string',
        ]);

        $sandbox->update($data);
        return response()->json($sandbox);
    }

    public function destroy(Request $request, $id)
    {
        $sandbox = Sandbox::findOrFail($id);

        if ($sandbox->student_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $sandbox->delete();
        return response()->json(['message' => 'Sandbox deleted']);
    }
}