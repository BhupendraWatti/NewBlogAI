<?php

namespace App\Modules\PromptManager\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\PromptManager\Requests\StorePromptRequest;
use App\Modules\PromptManager\Requests\UpdatePromptRequest;
use App\Modules\PromptManager\Resources\PromptResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PromptController extends Controller
{
    /**
     * Display a listing of prompts with filters, sorting, and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Prompt::query()->latest();

        // Filtering by category
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        // Filtering by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Search in name or prompt content
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('promt', 'like', "%{$search}%");
            });
        }

        $limitInput = $request->input('limit', 15);
        if ($limitInput !== null && $limitInput !== '' && filter_var($limitInput, FILTER_VALIDATE_INT) === false) {
            abort(422, 'The limit parameter must be a positive integer.');
        }

        $limit = max(1, min((int) $limitInput, 100));

        return PromptResource::collection($query->paginate($limit));
    }

    /**
     * Store a newly created prompt template.
     */
    public function store(StorePromptRequest $request): JsonResponse
    {
        $prompt = Prompt::create($request->validated());

        return (new PromptResource($prompt))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified prompt.
     */
    public function show(string $id): PromptResource
    {
        return new PromptResource(Prompt::findOrFail($id));
    }

    /**
     * Update the specified prompt in database.
     */
    public function update(UpdatePromptRequest $request, string $id): PromptResource
    {
        $prompt = Prompt::findOrFail($id);
        $prompt->update($request->validated());

        return new PromptResource($prompt);
    }

    /**
     * Remove the specified prompt from database.
     */
    public function destroy(string $id): JsonResponse
    {
        Prompt::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Prompt template deleted successfully.'
        ]);
    }
}
