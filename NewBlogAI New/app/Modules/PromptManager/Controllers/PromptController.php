<?php

namespace App\Modules\PromptManager\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\PromptManager\Requests\StorePromptRequest;
use App\Modules\PromptManager\Requests\UpdatePromptRequest;
use App\Modules\PromptManager\Resources\PromptResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PromptController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PromptResource::collection(Prompt::all());
    }

    public function store(StorePromptRequest $request): JsonResponse
    {
        $prompt = Prompt::create($request->validated());

        return (new PromptResource($prompt))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): PromptResource
    {
        return new PromptResource(Prompt::findOrFail($id));
    }

    public function update(UpdatePromptRequest $request, string $id): PromptResource
    {
        $prompt = Prompt::findOrFail($id);
        $prompt->update($request->validated());

        return new PromptResource($prompt);
    }

    public function destroy(string $id): JsonResponse
    {
        Prompt::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Prompt template deleted successfully.'
        ]);
    }
}
