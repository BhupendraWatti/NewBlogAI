<?php

namespace App\Modules\SystemSettings\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SystemSettings\Models\MasterOption;
use App\Modules\SystemSettings\Requests\StoreMasterOptionRequest;
use App\Modules\SystemSettings\Requests\UpdateMasterOptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterOptionController extends Controller
{
    /**
     * List all master options with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MasterOption::with('parent');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->input('parent_id'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $query->ordered();

        if ($request->boolean('all')) {
            $options = $query->get();
            return response()->json(['data' => $options]);
        }

        $limit = $request->integer('limit', 50);
        $options = $query->paginate($limit);

        return response()->json($options);
    }

    /**
     * Return grouped active options (topics, countries, states)
     * for instant client-side dropdown population.
     */
    public function grouped(): JsonResponse
    {
        $topics = MasterOption::ofType('topic')
            ->active()
            ->ordered()
            ->get(['id', 'type', 'name', 'code', 'sort_order']);

        $countries = MasterOption::ofType('country')
            ->active()
            ->ordered()
            ->get(['id', 'type', 'name', 'code', 'sort_order']);

        $states = MasterOption::ofType('state')
            ->with('parent:id,name,code')
            ->active()
            ->ordered()
            ->get(['id', 'type', 'name', 'code', 'parent_id', 'sort_order']);

        return response()->json([
            'data' => [
                'topics' => $topics,
                'countries' => $countries,
                'states' => $states,
            ],
        ]);
    }

    /**
     * Store a new master option.
     */
    public function store(StoreMasterOptionRequest $request): JsonResponse
    {
        $option = MasterOption::create($request->validated());

        return response()->json([
            'message' => 'Master option created successfully.',
            'data' => $option->load('parent'),
        ], 201);
    }

    /**
     * Retrieve a specific master option.
     */
    public function show(string $id): JsonResponse
    {
        $option = MasterOption::with(['parent', 'children'])->findOrFail($id);

        return response()->json([
            'data' => $option,
        ]);
    }

    /**
     * Update an existing master option.
     */
    public function update(UpdateMasterOptionRequest $request, string $id): JsonResponse
    {
        $option = MasterOption::findOrFail($id);
        $option->update($request->validated());

        return response()->json([
            'message' => 'Master option updated successfully.',
            'data' => $option->fresh(['parent']),
        ]);
    }

    /**
     * Delete a master option.
     */
    public function destroy(string $id): JsonResponse
    {
        $option = MasterOption::findOrFail($id);
        $option->delete();

        return response()->json([
            'message' => 'Master option deleted successfully.',
        ]);
    }
}
