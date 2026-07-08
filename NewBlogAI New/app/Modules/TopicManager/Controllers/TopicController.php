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

        if (\Illuminate\Support\Facades\Auth::user()->role !== 1) {
            $filters['customer_id'] = \Illuminate\Support\Facades\Auth::user()->customer_id;
        }

        $topics = $this->topicService->getPaginated($filters, $limit);

        return TopicResource::collection($topics);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTopicRequest $request): JsonResponse
    {
        if (\Illuminate\Support\Facades\Auth::user()->role !== 1) {
            if ($request->filled('subscription_id')) {
                $sub = \App\Modules\SubscriptionManager\Models\Subscription::findOrFail($request->input('subscription_id'));
                if ($sub->customer_id !== \Illuminate\Support\Facades\Auth::user()->customer_id) {
                    abort(403, 'Unauthorized subscription selection.');
                }
            }
        }

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
        $topic = $this->findTopicOrFail($id, ['parent', 'prompt']);

        return new TopicResource($topic);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTopicRequest $request, string $id): TopicResource
    {
        $topic = $this->findTopicOrFail($id);
        $validated = $request->validated();

        if (\Illuminate\Support\Facades\Auth::user()->role !== 1) {
            if (isset($validated['subscription_id'])) {
                $sub = \App\Modules\SubscriptionManager\Models\Subscription::findOrFail($validated['subscription_id']);
                if ($sub->customer_id !== \Illuminate\Support\Facades\Auth::user()->customer_id) {
                    abort(403, 'Unauthorized subscription selection.');
                }
            }
        }

        $updated = $this->topicService->updateTopic($topic, $validated);

        return new TopicResource($updated->load(['parent', 'prompt']));
    }

    /**
     * Remove the specified resource from storage (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $topic = $this->findTopicOrFail($id);
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
        $topic = $this->findTrashedTopicOrFail($id);
        $restored = $this->topicService->restoreTopic($topic->id);

        return response()->json([
            'message' => 'Topic restored successfully.',
            'data' => new TopicResource($restored->load(['parent', 'prompt'])),
        ]);
    }

    /**
     * Find a topic by ID while enforcing tenant isolation.
     */
    private function findTopicOrFail(string $id, array $relations = []): Topic
    {
        $query = Topic::query();
        if (! empty($relations)) {
            $query->with($relations);
        }

        if (\Illuminate\Support\Facades\Auth::user()->role !== 1) {
            $query->whereHas('subscription', function ($q) {
                $q->where('customer_id', \Illuminate\Support\Facades\Auth::user()->customer_id);
            });
        }

        return $query->findOrFail($id);
    }

    /**
     * Find a trashed topic by ID while enforcing tenant isolation.
     */
    private function findTrashedTopicOrFail(string $id): Topic
    {
        $query = Topic::onlyTrashed();

        if (\Illuminate\Support\Facades\Auth::user()->role !== 1) {
            $query->whereHas('subscription', function ($q) {
                $q->where('customer_id', \Illuminate\Support\Facades\Auth::user()->customer_id);
            });
        }

        return $query->findOrFail($id);
    }
}
