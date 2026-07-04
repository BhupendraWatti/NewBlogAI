<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promt;
use Illuminate\Http\Request;

class PromtController extends Controller
{
    public function index()
    {
        return response()->json(Promt::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'promt' => 'required|string',
        ]);

        $prompt = Promt::create($validated);

        return response()->json([
            'message' => 'Prompt template created successfully.',
            'data' => $prompt,
        ], 210); // Laravel standard
    }

    public function show($id)
    {
        $prompt = Promt::findOrFail($id);

        return response()->json($prompt);
    }

    public function update(Request $request, $id)
    {
        $prompt = Promt::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'promt' => 'sometimes|required|string',
        ]);

        $prompt->update($validated);

        return response()->json([
            'message' => 'Prompt template updated successfully.',
            'data' => $prompt,
        ]);
    }

    public function destroy($id)
    {
        $prompt = Promt::findOrFail($id);
        $prompt->delete();

        return response()->json([
            'message' => 'Prompt template deleted successfully.',
        ]);
    }
}
