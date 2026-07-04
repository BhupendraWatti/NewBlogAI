<?php

namespace App\Modules\TopicManager\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TopicManager\Models\Topic;
use App\Modules\TopicManager\Requests\StoreTopicRequest;
use App\Modules\TopicManager\Requests\UpdateTopicRequest;
use App\Modules\TopicManager\Resources\TopicResource;
use App\Modules\TopicManager\Services\TopicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TopicController extends Controller
{
    public function __construct(
        protected TopicService $topicService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['search', 'parent_id', 'status', 'language', 'sort_by', 'sort_order']);
        $limit = $request->input('limit', 15);

        $topics = $this->topicService->getPaginated($filters, $limit);

        return TopicResource::collection($topics);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTopicRequest $request): JsonResponse
    {
        $topic = $this->topicService->createTopic($request->validated());

        return (new TopicResource($topic->load(['parent', 'prompt'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): TopicResource
    {
        $topic = Topic::with(['parent', 'prompt'])->findOrFail($id);

        return new TopicResource($topic);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTopicRequest $request, string $id): TopicResource
    {
        $topic = Topic::findOrFail($id);
        $updated = $this->topicService->updateTopic($topic, $request->validated());

        return new TopicResource($updated->load(['parent', 'prompt']));
    }

    /**
     * Remove the specified resource from storage (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $topic = Topic::findOrFail($id);
        $this->topicService->deleteTopic($topic);

        return response()->json([
            'message' => 'Topic soft-deleted successfully.',
        ]);
    }

    /**
     * Restore a soft-deleted topic.
     */
    public function restore(string $id): JsonResponse
    {
        $restored = $this->topicService->restoreTopic($id);

        return response()->json([
            'message' => 'Topic restored successfully.',
            'data' => new TopicResource($restored->load(['parent', 'prompt'])),
        ]);
    }
}
