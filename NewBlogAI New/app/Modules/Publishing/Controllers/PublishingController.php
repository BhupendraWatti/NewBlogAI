<?php

namespace App\Modules\Publishing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Publishing\Models\PublishingLog;
use App\Modules\Publishing\Requests\BulkPublishRequest;
use App\Modules\Publishing\Requests\PublishArticleRequest;
use App\Modules\Publishing\Resources\PublishingLogResource;
use App\Modules\Publishing\Services\PublishingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class PublishingController extends Controller
{
    public function __construct(
        protected PublishingService $publishingService
    ) {}

    /**
     * Display a listing of publishing history/logs.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['status', 'site_id']);
        $limit = $request->input('limit', 15);

        $logs = $this->publishingService->getPaginated($filters, $limit);

        return PublishingLogResource::collection($logs);
    }

    /**
     * Display the specified publishing log.
     */
    public function show(string $id): PublishingLogResource
    {
        $log = PublishingLog::with(['content', 'site', 'author'])->findOrFail($id);

        return new PublishingLogResource($log);
    }

    /**
     * Trigger queue publishing for a single article.
     */
    public function publish(PublishArticleRequest $request, string $articleId): JsonResponse
    {
        try {
            $log = $this->publishingService->queuePublish(
                (int) $articleId,
                $request->validated(),
                Auth::id()
            );

            return response()->json([
                'message' => 'Publishing operation queued successfully.',
                'log' => new PublishingLogResource($log->load(['content', 'site'])),
            ], 202);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Trigger bulk queue publishing for multiple articles.
     */
    public function bulkPublish(BulkPublishRequest $request): JsonResponse
    {
        try {
            $logs = $this->publishingService->bulkQueuePublish(
                $request->input('article_ids'),
                $request->only(['site_id', 'wp_status']),
                Auth::id()
            );

            return response()->json([
                'message' => count($logs).' articles queued for bulk publication.',
                'logs' => PublishingLogResource::collection(collect($logs)->load(['content', 'site'])),
            ], 202);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Retry a failed publishing run.
     */
    public function retry(string $id): JsonResponse
    {
        $log = PublishingLog::findOrFail($id);
        $this->publishingService->retryPublish($log);

        return response()->json([
            'message' => 'Publishing retry queued successfully.',
        ], 202);
    }

    /**
     * Cancel a queued/pending publishing run.
     */
    public function cancel(string $id): JsonResponse
    {
        $log = PublishingLog::findOrFail($id);
        $this->publishingService->cancelPublish($log);

        return response()->json([
            'message' => 'Publishing queue cancelled successfully.',
        ]);
    }

    /**
     * Manually pull and sync post status from WordPress.
     */
    public function sync(string $id): JsonResponse
    {
        $log = PublishingLog::with('site')->findOrFail($id);
        $this->publishingService->syncPostStatus($log);

        return response()->json([
            'message' => 'WordPress status synchronized successfully.',
            'log' => new PublishingLogResource($log->fresh(['content', 'site'])),
        ]);
    }
}
